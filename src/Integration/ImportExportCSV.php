<?php namespace Seiger\sCommerce\Integration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sSeo\Models\sSeoModel;
use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Contracts\TaskInterface;

/**
 * ImportExportCSV - Integration for CSV import/export operations
 *
 * This class implements the CSV import/export integration for sCommerce products.
 * It extends BaseWorker (sTask) and provides comprehensive functionality for exporting
 * product data to CSV format and importing product data from CSV files.
 *
 * Features:
 * - Export products to CSV with customizable field selection
 * - Support for product attributes and translations
 * - Dynamic header generation with localized field names
 * - Progress tracking and ETA calculation
 * - File cleanup and garbage collection
 * - Error handling and validation
 * - Support for multiple languages and currencies
 *
 * Export Process:
 * 1. Generates dynamic headers based on product fields and attributes
 * 2. Processes products in batches with progress updates
 * 3. Calculates ETA based on processing speed
 * 4. Creates downloadable CSV files with proper formatting
 * 5. Handles cleanup of old export files
 *
 * @package Seiger\sCommerce\Integration
 * @author Seiger IT Team
 * @since 1.0.0
 */
class ImportExportCSV extends BaseWorker
{
    const ALLOWED_EXTENSIONS = ['csv'];
    private const CSV_DELIMITER = ';';
    private const TEXT_FIELDS = ['pagetitle', 'longtitle', 'introtext', 'content'];
    private const SEO_FIELDS = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url'];
    /** Directory where this integration puts its CSV exports. */
    private const EXPORT_DIR = 'stask/uploads';
    /** Directory where uploaded CSV files are stored for import. */
    private const IMPORT_DIR = 'stask/uploads';
    /** Keep export files for N days. */
    private const EXPORT_TTL_DAYS = 7;
    /** Run export GC at most once per hour. */
    private const EXPORT_GC_MIN_INTERVAL = 3600;

    /**
     * Get the unique identifier for this worker.
     *
     * @return string The worker identifier
     */
    public function identifier(): string
    {
        return 'sImportExportCSV';
    }

    /**
     * Get the scope/module this worker belongs to.
     *
     * @return string The module scope
     */
    public function scope(): string
    {
        return 'sCommerce';
    }

    /**
     * Get the admin display icon for the ImportExportCSV integration.
     *
     * Retrieves the icon for the integration to be displayed in the admin panel.
     *
     * @return string The formatted icon for admin display.
     */
    public function icon(): string
    {
        return '<i class="fas fa-file-csv"></i>';
    }

    /**
     * Get the admin display title for the ImportExportCSV integration.
     *
     * Retrieves the title for the integration to be displayed in the admin panel.
     *
     * @return string The formatted title for admin display.
     */
    public function title(): string
    {
        return __('sCommerce::global.import') . ' / ' . __('sCommerce::global.export') . ' CSV';
    }

    /**
     * Get the description for this worker.
     *
     * @return string The worker description
     */
    public function description(): string
    {
        return __('sCommerce::global.csv_import_export_desc');
    }

    /**
     * Get the widget for the integration.
     *
     * Retrieves the widget of the integration.
     *
     * @return string The widget of the integration.
     */
    public function renderWidget(): string
    {
        return view('sCommerce::partials.widgetImportExportCSV', ['identifier' => $this->identifier()])->render();
    }

    /**
     * Get validation rules for the integration.
     *
     * This method defines specific validation rules for fields related to the current integration.
     * The rules ensure that all required fields are filled and properly formatted.
     *
     * @return array An associative array of validation rules, where the key is the field name,
     *               and the value is the validation rule.
     *
     * Example Output:
     * [
     *     'delivery.address' => 'string|max:255',
     * ]
     */
    public function getValidationRules(): array
    {
        return [];
    }

    /**
     * Define the fields configuration for the integration.
     *
     * Specifies the configurable fields for the "ImportExportCSV" integration.
     *
     * @return array Configuration of fields grouped by sections or tabs.
     */
    public function defineFields(): array
    {
        return [];
    }

    /**
     * Prepare the settings data for storage.
     *
     * Validates and formats the settings data provided by the admin panel.
     * Converts the settings into a JSON-compatible format for database storage.
     *
     * @param array $data The input data to validate and prepare.
     * @return string A JSON string of the validated and prepared settings data.
     * @throws ValidationException If validation fails.
     */
    public function prepareSettings(array $data): string
    {
        $preparedData = [];
        $fieldNames =  $this->extractFieldNames($this->defineFields());

        foreach ($fieldNames as $fieldName) {
            $key = preg_split('/\]\[|\[|\]/', rtrim($fieldName, ']'))[0];
            if (isset($data[$key])) {
                $preparedData[$key] = $data[$key];
            }
        }

        return json_encode($preparedData, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Run 'export' action using ID-window batching with 1% granular progress.
     *
     * - Emits a snapshot on every new percent (0..99), plus a gentle heartbeat
     *   every ~0.5s to refresh the "processed/total" counters in the UI.
     * - Final snapshot sets 100% and download URL.
     *
     * @param sTaskModel $task  Task model.
     * @param array            $opt   Action options (CSV formatting, etc.)
     * @return void
     */
    public function taskExport(sTaskModel $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');

        try {
            // CSV defaults
            $opt = array_merge([
                'delimiter' => self::CSV_DELIMITER,
                'enclosure' => '"',
                'escape'    => '\\',
                'add_bom'   => true,
            ], $opt);

            // Preparing
            $task->update([
                'status'  => sTaskModel::TASK_STATUS_PREPARING,
                'message' => __('sCommerce::global.task_running') . '...',
            ]);
            $this->pushProgress($task);

            // Get total count first
            $total = sProduct::count();
            if ($total === 0) {
                throw new \RuntimeException('No products found to export.');
            }

            // Ensure export dir e.g. storage/scommerce/exports
            $dirAbs = storage_path(self::EXPORT_DIR);
            if (!is_dir($dirAbs)) {
                @mkdir($dirAbs, 0775, true);
            }

            $name = 'sCommerce_products_' . date('Y-m-d_H-i-s') . '.csv';
            $path = $dirAbs . '/' . $name;

            // Open CSV
            $fh = fopen($path, 'w');
            if ($fh === false) {
                throw new \RuntimeException('Cannot open file for writing: ' . $path);
            }

            if (!empty($opt['add_bom'])) {
                // UTF-8 BOM for Excel
                fwrite($fh, "\xEF\xBB\xBF");
            }

            // Header
            $this->putCsv($fh, $this->headerRow(), $opt);

            $processed = 0;
            $headerRow = array_keys($this->headerRow());
            $useMultilang = $this->shouldUseMultilangCsv();

            $task->update([
                'status'  => sTaskModel::TASK_STATUS_RUNNING,
                'message' => __('sCommerce::global.exporting') . '...',
            ]);
            $this->pushProgress($task, [
                'progress'  => 0,
                'processed' => 0,
                'total'     => $total,
            ]);

            // --- 1% progress emitter + gentle heartbeat + ETA calculation ---
            // Doc: emit snapshot each time percent increases; also heartbeat every 0.5s
            $lastPct  = -1;              // last emitted percent
            $lastBeat = microtime(true); // last heartbeat time
            $startTime = microtime(true); // start time for ETA calculation
            $maxExecutionTime = 600; // 10 minutes max execution time
            $eta = '—'; // default ETA display

            // Process products in chunks to avoid memory issues
            $with = ['categories'];
            if ($useMultilang) {
                $with[] = 'texts';
            }
            sProduct::with($with)->orderBy('id')->chunk(50, function ($products) use ($fh, $opt, $headerRow, &$processed, $total, $task, &$lastPct, &$lastBeat, $startTime, $maxExecutionTime, &$eta, $useMultilang) {
                foreach ($products as $p) {
                    // Check execution time limit
                    if ((microtime(true) - $startTime) > $maxExecutionTime) {
                        Log::warning("Export timeout reached after {$maxExecutionTime} seconds. Processed {$processed}/{$total} products.");
                        throw new \RuntimeException('Export timeout reached. Processed ' . $processed . ' out of ' . $total . ' products.');
                    }

                    try {
                        $a = [];
                        foreach ($headerRow as $field) {
                            try {
                                if ($useMultilang) {
                                    $parsed = $this->parseLangIndexedTextKey($field);
                                    if ($parsed) {
                                        $value = $this->getProductTranslateField($p, $parsed['lang'], $parsed['field']);
                                        $a[$field] = $this->sanitizeCsvValue($value);
                                        continue;
                                    }

                                    $parsedSeo = $this->parseLangIndexedSeoKey($field);
                                    if ($parsedSeo) {
                                        $value = $this->getProductSeoField($p, $parsedSeo['lang'], $parsedSeo['field']);
                                        $a[$field] = $this->sanitizeCsvValue($value);
                                        continue;
                                    }
                                }
                                switch ($field) {
                                    case 'published':
                                    case 'availability':
                                    case 'inventory':
                                        $a[$field] = intval($p?->{$field});
                                        break;
                                    case 'price_regular':
                                    case 'price_special':
                                        if ((float)$p?->{$field} !== null && (float)$p?->{$field} > 0) {
                                            $a[$field] = number_format((float)$p?->{$field}, 2, '.', '');
                                        } else {
                                            $a[$field] = '';
                                        }
                                        break;
                                    case str_starts_with($field, 'attribute:'):
                                        $alias = substr($field, strlen('attribute:'));
                                        $value = $this->exportProductAttributeValue($p, $alias);
                                        $a[$field] = $this->sanitizeCsvValue($value);
                                        break;
                                    case 'category':
                                        if (evo()->getConfig('check_sMultisite', false)) {
                                            // Multisite mode: return domain:id format
                                            $categories = [];
                                            foreach ($p?->categories ?? [] as $category) {
                                                $scope = $category->pivot->scope ?? 'primary';

                                                if ($scope === 'primary') {
                                                    $domain = 'default';
                                                } else {
                                                    $domain = str_replace('primary_', '', $scope);
                                                }
                                                $categories[] = $domain . ':' . $category->id;
                                            }
                                            $a[$field] = implode('||', $categories);
                                        } else {
                                            // Single site mode: return category IDs
                                            $categoryIds = $p?->categories->pluck('id')->toArray();
                                            $a[$field] = implode('||', $categoryIds);
                                        }
                                        break;
                                    case 'cover':
                                        $a[$field] = $this->sanitizeCsvValue($this->normalizeCsvMediaPath($p?->{$field} ?? ''));
                                        break;
                                    case 'categories':
                                        // Cross-domain category list: always return simple IDs
                                        $categoryIds = $p?->categories->pluck('id')->toArray();
                                        $a[$field] = implode('||', $categoryIds);
                                        break;
                                    case 'gallery':
                                        $a[$field] = $this->sanitizeCsvValue($this->exportProductGallery($p));
                                        break;
                                    case 'meta_title':
                                    case 'meta_description':
                                    case 'meta_keywords':
                                    case 'canonical_url':
                                        $a[$field] = $this->sanitizeCsvValue($this->getProductSeoField($p, 'base', $field));
                                        break;
                                    default:
                                        $value = $p?->{$field} ?? '';
                                        $a[$field] = $this->sanitizeCsvValue($value);
                                        break;
                                }
                            } catch (\Exception $e) {
                                Log::error("Error processing field '{$field}' for product ID {$p->id}: " . $e->getMessage());
                                $a[$field] = '';
                            }
                        }

                        if (count($a)) {
                            $this->putCsv($fh, $a, $opt);
                        }
                    } catch (\Exception $e) {
                        Log::error("Error processing product ID {$p->id}: " . $e->getMessage());
                        // Continue with next product
                    }

                    $processed++;
                    $pct = (int)floor($processed * 100 / $total);

                    // Calculate ETA if we have processed at least 1% and have some progress
                    if ($processed > 0 && $pct > 0) {
                        $elapsed = microtime(true) - $startTime;
                        $rate = $processed / $elapsed; // items per second
                        $remaining = $total - $processed;
                        $etaSeconds = $remaining / $rate;

                        if ($etaSeconds > 0 && $etaSeconds < 86400) { // less than 24 hours
                            $eta = niceEta($etaSeconds);
                        } else {
                            $eta = '—';
                        }
                    }

                    // Log progress every 10 products for debugging
                    if ($processed % 10 === 0) {
                        Log::info("Export progress: {$processed}/{$total} ({$pct}%) - Product ID: {$p->id}");
                    }

                    // Emit on each new percent (cap at 99 before the final "finished")
                    if ($pct !== $lastPct) {
                        $lastPct = $pct;
                        $this->pushProgress($task, [
                            'processed' => $processed,
                            'total'     => $total,
                            'progress'  => min($pct, 98),
                            'eta'       => $eta,
                        ]);
                        $lastBeat = microtime(true);
                    }
                    // Heartbeat: refresh counters at least every 0.5s even if % didn't change
                    elseif ((microtime(true) - $lastBeat) >= 0.5) {
                        $this->pushProgress($task, [
                            'processed' => $processed,
                            'total'     => $total,
                            'progress'  => min($pct, 98),
                            'eta'       => $eta,
                        ]);
                        $lastBeat = microtime(true);
                    }
                }
            });
            // --- end 1% emitter ---

            // Saving
            $task->update([
                'status'  => sTaskModel::TASK_STATUS_RUNNING,
                'message' => __('sCommerce::global.preparing_file') . '...',
            ]);
            $this->pushProgress($task, ['progress' => 99]);

            fclose($fh);

            // Done - use helper method
            $downloadUrl = route('sTask.task.download', ['id' => $task->id]);
            $downloadUrl = str_replace(['http://localhost', 'https://localhost', EVO_SITE_URL, EVO_CORE_PATH], '|', $downloadUrl);
            $downloadUrl = explode('|', $downloadUrl);
            $downloadUrl = end($downloadUrl);
            $downloadUrl = '/' . ltrim($downloadUrl, '/.');
            $this->markFinished(
                $task,
                $path,
                '**' . __('sCommerce::global.done') . '. [📥 ' . __('sCommerce::global.download') . '](' . $downloadUrl . ')**'
            );

            //self::maybePruneExports();
        } catch (\Throwable $e) {
            // Precise failure location in UI log
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $message = 'Failed @ ' . $where . ' — ' . $e->getMessage();

            // Use helper method
            $this->markFailed($task, $message);
            throw $e;
        }
    }

    /**
     * Import products from CSV file.
     *
     * This method processes a CSV file and imports product data into the database.
     * It handles both creating new products and updating existing ones based on ID or SKU.
     * For large files, it uses streaming processing or temporary database table approach.
     *
     * @param sTaskModel $task The task instance containing execution context
     * @param array $opt Additional options for import process
     * @return void
     * @throws \RuntimeException If file cannot be opened or processed
     */
    public function taskImport(sTaskModel $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');
        @ini_set('memory_limit', '2G');
        @set_time_limit(0);

        try {
            // CSV defaults
            $opt = array_merge([
                'delimiter' => self::CSV_DELIMITER,
                'enclosure' => '"',
                'escape'    => '\\',
                'add_bom'   => true,
                'batch_size' => 100,
                'use_temp_table' => false,
            ], $opt);

            // Get filename from task meta or options
            $filename = $task->meta['filename'] ?? $opt['filename'] ?? null;
            if (!$filename) {
                throw new \RuntimeException('Filename not specified for import task');
            }

            if (file_exists($filename)) {
                $filePath = realpath($filename);
            } else {
                $filePath = storage_path(self::IMPORT_DIR . '/' . $filename);
                if (!file_exists($filePath)) {
                    throw new \RuntimeException('CSV file not found: ' . $filename);
                }
            }

            // Check file size and decide processing method
            $fileSize = filesize($filePath);
            $serverLimits = [
                'maxFileSize' => 100 * 1024 * 1024,      // 100 MB
                'chunkSize' => 1024 * 1024,               // 1 MB
                'singleUploadLimit' => 2 * 1024 * 1024,  // 2 MB
            ];
            $useTempTable = $opt['use_temp_table'] || $fileSize > ($serverLimits['maxFileSize'] * 0.5); // 50% of max file size threshold

            if ($useTempTable) {
                Log::info('importWithTempTable');
                //$this->importWithTempTable($task, $filePath, $opt);
            } else {
                $this->importWithStreaming($task, $filePath, $opt);
            }
        } catch (\Exception $e) {
            $this->markFailed($task, $e->getMessage());
        }
    }

    /**
     * Write a single row to the CSV stream.
     *
     * @param resource $fh Open CSV file handle.
     * @param array $row Row values to write.
     * @param array $opt CSV formatting options.
     * @return void
     */
    protected function putCsv($fh, array $row, array $opt): void
    {
        $result = fputcsv($fh, $row, $opt['delimiter'], $opt['enclosure'], $opt['escape']);
        if ($result === false) {
            throw new \RuntimeException('Failed to write CSV row to file');
        }
    }

    /**
     * Maybe prune old export CSVs (lazy, throttled).
     */
    protected static function maybePruneExports(): void
    {
        // ~1% шанс або за потреби можна викликати без випадковості після великих задач
        if (\mt_rand(1, 100) === 1) {
            self::pruneExports();
        }
    }

    /**
     * Delete export files older than EXPORT_TTL_DAYS.
     * Also clears stale DB pointers (result_path) if files are gone.
     */
    protected static function pruneExports(): void
    {
        $dir = storage_path(self::EXPORT_DIR);
        if (!is_dir($dir)) return;

        $now   = time();
        $limit = $now - max(1, self::EXPORT_TTL_DAYS) * 86400;

        // throttle by marker
        $mark = $dir . '/.gc_exports';
        $last = @filemtime($mark) ?: 0;
        if (($now - $last) < self::EXPORT_GC_MIN_INTERVAL) return;

        // Delete old CSV files
        foreach (glob($dir.'/*.csv') ?: [] as $file) {
            clearstatcache(false, $file);
            $mtime = @filemtime($file) ?: 0;
            if ($mtime && $mtime < $limit) @unlink($file);
        }

        // Clean stale DB pointers (best-effort)
        try {
            $tasks = sTaskModel::query()
                ->where('identifier', (new static)->identifier())
                ->whereNotNull('result')
                ->where('status', sTaskModel::TASK_STATUS_FINISHED)
                ->orderByDesc('id')
                ->limit(500)
                ->get();

            foreach ($tasks as $t) {
                if (!is_string($t->result) || $t->result === '') continue;
                if (!is_file($t->result)) {
                    $t->result = null;
                    $t->save();
                }
            }
        } catch (\Throwable) {
            // ignore if DB temporarily unavailable
        }

        @touch($mark);
    }

    /**
     * Generate header row for CSV export with translated field names.
     *
     * This method creates a comprehensive array of field names mapped to their translated labels
     * for use in CSV headers. It includes both standard product fields and dynamic attributes
     * from the sCommerce system. The translations are taken from the sCommerce language files.
     *
     * The method generates headers for:
     * - Basic product information (ID, SKU, alias, published status, availability)
     * - Pricing information (regular price, special price, currency)
     * - Physical properties (weight, dimensions, volume)
     * - Inventory and ratings (stock quantity, views, rating)
     * - Media and content (cover image, titles, descriptions)
     * - Categories and relationships
     * - Dynamic product attributes from the database
     *
     * @return array Associative array where keys are field names and values are translated labels
     */
    private function headerRow(): array
    {
        $sCommerceController = new sCommerceController();
        return $this->buildHeaderRow($this->shouldUseMultilangCsv(), $sCommerceController);
    }

    /**
     * Build the CSV header definition, including optional multilingual and SEO fields.
     *
     * @param bool $useMultilang Whether multilingual columns should be included.
     * @param sCommerceController $sCommerceController Controller used to resolve language metadata.
     * @return array Header map in internalField => label format.
     */
    private function buildHeaderRow(bool $useMultilang, sCommerceController $sCommerceController): array
    {
        $product = [
            // Basic product information
            'id' => 'ID',
            'sku' => __('sCommerce::global.sku'),
            'alias' => __('sCommerce::global.product_link'),
            'published' => __('sCommerce::global.visibility'),
            'availability' => __('sCommerce::global.availability'),
            // 'mode' => __('sCommerce::global.product_type'),

            // Inventory and ratings
            'inventory' => __('sCommerce::global.inventory'),
            // 'views' => __('sCommerce::global.views'),
            // 'rating' => __('sCommerce::global.rating'),

            // Pricing information
            'price_regular' => __('sCommerce::global.price'),
            'price_special' => __('sCommerce::global.price_special'),
            // 'price_opt_regular' => __('sCommerce::global.price_opt'),
            // 'price_opt_special' => __('sCommerce::global.price_opt_special'),
            'currency' => __('sCommerce::global.currency'),

            // Physical properties
            // 'weight' => __('sCommerce::global.weight'),
            // 'width' => __('sCommerce::global.width'),
            // 'height' => __('sCommerce::global.height'),
            // 'length' => __('sCommerce::global.length'),
            // 'volume' => __('sCommerce::global.volume'),

            // Media and content
            'cover' => __('sCommerce::global.cover'),
            'gallery' => 'Галерея',

            // Categories and relationships
            'category' => __('sCommerce::global.category'),
            // 'categories' => __('sCommerce::global.categories'),
            // 'relevants' => __('sCommerce::global.relevant'),
            // 'similar' => __('sCommerce::global.relevant'),

            // Additional data
            // 'tmplvars' => __('sCommerce::global.additional_fields_main_product_tab'),
            // 'additional' => __('sCommerce::global.additional_fields_to_products_tab'),
            // 'representation' => __('sCommerce::global.representation_products_fields'),

            // Timestamps
            // 'created_at' => __('sCommerce::global.created'),
            // 'updated_at' => __('sCommerce::global.created'),
        ];

        // Product translations
        if ($useMultilang) {
            foreach ($this->csvLanguagesList($sCommerceController) as $lang) {
                $langTag = '[' . $lang . ']';
                $product[$this->buildLangTextKey($lang, 'pagetitle')] = __('sCommerce::global.product_name') . ' ' . $langTag;
                $product[$this->buildLangTextKey($lang, 'longtitle')] = __('global.long_title') . ' ' . $langTag;
                $product[$this->buildLangTextKey($lang, 'introtext')] = __('global.resource_summary') . ' ' . $langTag;
                $product[$this->buildLangTextKey($lang, 'content')] = __('sCommerce::global.content') . ' ' . $langTag;
            }
        } else {
            $product['pagetitle'] = __('sCommerce::global.product_name');
            $product['longtitle'] = __('global.long_title');
            $product['introtext'] = __('global.resource_summary');
            $product['content'] = __('sCommerce::global.content');
        }

        if ($this->hasSeoSupport()) {
            if ($useMultilang) {
                foreach ($this->csvLanguagesList($sCommerceController) as $lang) {
                    $langTag = '[' . $lang . ']';
                    $product[$this->buildLangSeoKey($lang, 'meta_title')] = __('sSeo::global.meta_title') . ' ' . $langTag;
                    $product[$this->buildLangSeoKey($lang, 'meta_description')] = __('sSeo::global.meta_description') . ' ' . $langTag;
                    $product[$this->buildLangSeoKey($lang, 'meta_keywords')] = __('sSeo::global.meta_keywords') . ' ' . $langTag;
                    $product[$this->buildLangSeoKey($lang, 'canonical_url')] = __('sSeo::global.canonical') . ' ' . $langTag;
                }
            } else {
                $product['meta_title'] = __('sSeo::global.meta_title');
                $product['meta_description'] = __('sSeo::global.meta_description');
                $product['meta_keywords'] = __('sSeo::global.meta_keywords');
                $product['canonical_url'] = __('sSeo::global.canonical');
            }
        }

        // Related Attributes
        $attributes = [];
        $attributesQuery = sAttribute::lang($sCommerceController->langDefault())->active()->get();

        if ($attributesQuery) {
            foreach ($attributesQuery as $attr) {
                $attributeName = str_replace(['"', "'", "\n", "\r", "\t"], ['""', "''", ' ', ' ', ' '], $attr->pagetitle);
                $attributes['attribute:' . $attr->alias] = __('sCommerce::global.attribute') . ' ' . $attributeName;
            }
        }

        return array_merge($product, $attributes);
    }

    /**
     * Import using streaming processing for medium-sized files.
     *
     * @param sTaskModel $task The task instance
     * @param string $filePath Path to CSV file
     * @param array $opt Import options
     * @return void
     */
    private function importWithStreaming(sTaskModel $task, string $filePath, array $opt): void
    {
        $task->update([
            'status'  => sTaskModel::TASK_STATUS_PREPARING,
            'message' => __('sCommerce::global.preparing') . '...',
        ]);
        $this->pushProgress($task);

        // Open CSV file
        $fh = fopen($filePath, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open file for reading: ' . $filePath);
        }

        // Skip BOM if present
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Read header
        $header = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape']);
        if (!$header) {
            throw new \RuntimeException('Cannot read CSV header');
        }

        // Count total lines for progress tracking
        $totalLines = 0;
        while (fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape']) !== false) {
            $totalLines++;
        }
        rewind($fh);

        // Skip BOM and header again
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape']);

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;
        $batch = [];

        // Progress tracking variables
        $lastPct = -1;
        $startTime = microtime(true);
        $eta = '—';

        $task->update([
            'status'  => sTaskModel::TASK_STATUS_RUNNING,
            'message' => __('sCommerce::global.importing') . '...',
        ]);
        $this->pushProgress($task, [
            'progress'  => 0,
            'processed' => 0,
            'total'     => $totalLines,
        ]);

        $headerKeys = $this->importHeaderLabelToFieldMap();

        // Process each row in batches
        while (($row = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape'])) !== false) {
            try {
                $data = $this->normalizeImportRowData($header, $row, $headerKeys);
                if (!$data) {
                    $errors++;
                    continue;
                }

                $batch[] = $data;

                // Process batch when it reaches the batch size
                if (count($batch) >= $opt['batch_size']) {
                    $result = $this->processBatch($batch);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $errors += $result['errors'];
                    $processed += count($batch);
                    $batch = [];

                    // Update progress with ETA calculation
                    $pct = (int)floor($processed * 100 / $totalLines);

                    // Calculate ETA if we have processed at least 1% and have some progress
                    if ($processed > 0 && $pct > 0) {
                        $elapsed = microtime(true) - $startTime;
                        $rate = $processed / $elapsed; // items per second
                        $remaining = $totalLines - $processed;
                        $etaSeconds = $remaining / $rate;

                        if ($etaSeconds > 0 && $etaSeconds < 86400) { // less than 24 hours
                            $eta = niceEta($etaSeconds);
                        } else {
                            $eta = '—';
                        }
                    }

                    // Update progress if percentage changed
                    if ($pct !== $lastPct) {
                        $lastPct = $pct;

                        $this->pushProgress($task, [
                            'processed' => $processed,
                            'total'     => $totalLines,
                            'progress'  => min($pct, 98),
                            'eta'       => $eta,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                Log::channel('scommerce')->error("sCommerce CSV Import Error: " . $e->getMessage());
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $result = $this->processBatch($batch);
            $created += $result['created'];
            $updated += $result['updated'];
            $errors += $result['errors'];
            $processed += count($batch);

            // Update progress for final batch
            $pct = (int)floor($processed * 100 / $totalLines);

            // Calculate ETA if we have processed at least 1% and have some progress
            if ($processed > 0 && $pct > 0) {
                $elapsed = microtime(true) - $startTime;
                $rate = $processed / $elapsed; // items per second
                $remaining = $totalLines - $processed;
                $etaSeconds = $remaining / $rate;

                if ($etaSeconds > 0 && $etaSeconds < 86400) { // less than 24 hours
                    $eta = niceEta($etaSeconds);
                } else {
                    $eta = '—';
                }
            }

            // Update progress if percentage changed
            if ($pct !== $lastPct) {
                $lastPct = $pct;

                // Update task message to reflect current progress
                $message = sprintf(
                    __('sCommerce::global.importing') . '... (%d/%d)',
                    $processed,
                    $totalLines
                );

                $task->update(['message' => $message]);

                $this->pushProgress($task, [
                    'processed' => $processed,
                    'total'     => $totalLines,
                    'progress'  => min($pct, 98),
                    'eta'       => $eta,
                    'message'   => $message,
                ]);
            }
        }

        fclose($fh);

        // Final status update
        $message = '**' . sprintf(
                __('sCommerce::global.import_completed') . ': %d %s, %d %s, %d %s',
                $created,
                __('sCommerce::global.created'),
                $updated,
                __('sCommerce::global.updated'),
                $errors,
                __('sCommerce::global.errors')
            ) . '**';

        $this->markFinished($task, null, $message);
    }

    /**
     * Import using temporary table for very large files.
     *
     * @param sTaskModel $task The task instance
     * @param string $filePath Path to CSV file
     * @param array $opt Import options
     * @return void
     */
    private function importWithTempTable(sTaskModel $task, string $filePath, array $opt): void
    {
        $task->update([
            'status'  => sTaskModel::TASK_STATUS_PREPARING,
            'message' => __('sCommerce::global.preparing') . ' (temp table)...',
        ]);

        // Create temporary table
        $tempTableName = 'scommerce_import_temp_' . $task->id;
        $this->createTempTable($tempTableName);

        try {
            // Load CSV data into temporary table
            $this->loadCsvToTempTable($filePath, $tempTableName, $opt);

            // Process data from temporary table
            $this->processFromTempTable($task, $tempTableName, $opt);
        } finally {
            // Clean up temporary table
            $this->dropTempTable($tempTableName);
        }
    }

    /**
     * Create temporary table for import data.
     *
     * @param string $tableName Name of temporary table
     * @return void
     */
    private function createTempTable(string $tableName): void
    {
        Schema::create($tableName, function ($table) {
            $table->bigIncrements('row_id');
            $table->integer('id')->nullable();
            $table->string('sku', 255)->nullable();
            $table->string('alias', 255)->nullable();
            $table->boolean('published')->default(1);
            $table->boolean('availability')->default(0);
            $table->integer('inventory')->default(0);
            $table->decimal('price_regular', 10, 2)->default(0.00);
            $table->decimal('price_special', 10, 2)->nullable();
            $table->string('currency', 3)->default('UAH');
            $table->string('cover', 500)->nullable();
            $table->longText('gallery')->nullable();
            $table->integer('category')->nullable();
            $table->string('pagetitle', 255)->nullable();
            $table->string('longtitle', 255)->nullable();
            $table->text('introtext')->nullable();
            $table->longText('content')->nullable();
            $table->longText('text_payload')->nullable();
            $table->longText('seo_payload')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('processed')->default(0);

            // Add indexes
            $table->index('sku', 'idx_sku');
            $table->index('id', 'idx_id');
            $table->index('processed', 'idx_processed');
        });
    }

    /**
     * Load CSV data into temporary table using streaming approach.
     *
     * @param string $filePath Path to CSV file
     * @param string $tableName Name of temporary table
     * @param array $opt Import options
     * @return void
     */
    private function loadCsvToTempTable(string $filePath, string $tableName, array $opt): void
    {
        // Open CSV file
        $fh = fopen($filePath, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open file for reading: ' . $filePath);
        }

        // Skip BOM if present
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // Read header
        $header = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape']);
        if (!$header) {
            fclose($fh);
            throw new \RuntimeException('Cannot read CSV header');
        }

        $headerKeys = $this->importHeaderLabelToFieldMap();
        $defaultLang = $this->csvDefaultLanguage(new sCommerceController());
        $batch = [];
        $batchSize = 1000; // Larger batch for temp table loading
        $allowMultilang = $this->shouldUseMultilangCsv();

        // Process each row
        while (($row = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape'])) !== false) {
            $data = $this->normalizeImportRowData($header, $row, $headerKeys);
            if (!$data) {
                continue;
            }

            $textPayload = $this->extractTextPayloadByLang($data, $defaultLang, $allowMultilang);
            $seoPayload = $this->extractSeoPayloadByLang($data, $defaultLang, $allowMultilang);
            $defaultPayload = $textPayload[$defaultLang] ?? [];

            // Prepare data for temp table
            $tempData = [
                'id' => isset($data['id']) && is_numeric($data['id']) ? (int)$data['id'] : null,
                'sku' => $data['sku'] ?? null,
                'alias' => $data['alias'] ?? null,
                'published' => isset($data['published']) && $data['published'] !== '' ? (int)$data['published'] : 1,
                'availability' => isset($data['availability']) && $data['availability'] !== '' ? (int)$data['availability'] : 0,
                'inventory' => isset($data['inventory']) && $data['inventory'] !== '' ? (int)$data['inventory'] : 0,
                'price_regular' => isset($data['price_regular']) && $data['price_regular'] !== '' ? (float)$data['price_regular'] : 0.00,
                'price_special' => isset($data['price_special']) && $data['price_special'] !== '' ? (float)$data['price_special'] : null,
                'currency' => $data['currency'] ?? 'UAH',
                'cover' => $data['cover'] ?? null,
                'gallery' => $data['gallery'] ?? null,
                'category' => isset($data['category']) && is_numeric($data['category']) ? (int)$data['category'] : null,
                'pagetitle' => $data['pagetitle'] ?? ($defaultPayload['pagetitle'] ?? null),
                'longtitle' => $data['longtitle'] ?? ($defaultPayload['longtitle'] ?? null),
                'introtext' => $data['introtext'] ?? ($defaultPayload['introtext'] ?? null),
                'content' => $data['content'] ?? ($defaultPayload['content'] ?? null),
                'text_payload' => $this->encodeJson($textPayload),
                'seo_payload' => $this->encodeJson($seoPayload),
                'attributes' => $this->prepareAttributesForTempTable($data),
                'processed' => 0,
            ];

            $batch[] = $tempData;

            // Insert batch when it reaches the batch size
            if (count($batch) >= $batchSize) {
                \DB::table($tableName)->insert($batch);
                $batch = [];
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            \DB::table($tableName)->insert($batch);
        }

        fclose($fh);
    }

    /**
     * Prepare attributes data for temporary table.
     *
     * @param array $data CSV row data
     * @return string|null JSON string of attributes
     */
    private function prepareAttributesForTempTable(array $data): ?string
    {
        $attributes = [];

        foreach ($data as $key => $value) {
            if (!str_starts_with((string)$key, 'attribute:')) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $attributes[$key] = $value;
        }

        return !empty($attributes) ? $this->encodeJson($attributes) : null;
    }

    /**
     * Process data from temporary table.
     *
     * @param sTaskModel $task The task instance
     * @param string $tableName Name of temporary table
     * @param array $opt Import options
     * @return void
     */
    private function processFromTempTable(sTaskModel $task, string $tableName, array $opt): void
    {
        $totalRows = \DB::table($tableName)->count();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        $task->update([
            'status'  => sTaskModel::TASK_STATUS_RUNNING,
            'message' => __('sCommerce::global.importing') . '...',
        ]);

        $this->pushProgress($task, [
            'progress'  => 0,
            'processed' => 0,
            'total'     => $totalRows,
        ]);

        $startTime = microtime(true);
        $lastPct = -1;
        $eta = '—';

        // Process in batches
        $offset = 0;
        while ($offset < $totalRows) {
            $batch = \DB::table($tableName)
                ->where('processed', 0)
                ->limit($opt['batch_size'])
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $result = $this->processBatchFromTempTable($batch->toArray());
            $created += $result['created'];
            $updated += $result['updated'];
            $errors += $result['errors'];
            $processed += $batch->count();

            // Mark as processed using Eloquent
            $batchIds = $batch->pluck('row_id')->filter()->toArray();
            if (!empty($batchIds)) {
                \DB::table($tableName)
                    ->whereIn('row_id', $batchIds)
                    ->update(['processed' => 1]);
            }

            $offset += $opt['batch_size'];

            // Update progress
            $this->updateProgress($task, $processed, $totalRows, $startTime, $lastPct, $eta);
        }

        // Final status update
        $message = '**' . sprintf(
                __('sCommerce::global.import_completed') . ': %d %s, %d %s, %d %s',
                $created,
                __('sCommerce::global.created'),
                $updated,
                __('sCommerce::global.updated'),
                $errors,
                __('sCommerce::global.errors')
            ) . '**';

        $this->markFinished($task, null, $message);
    }

    /**
     * Drop temporary table.
     *
     * @param string $tableName Name of temporary table
     * @return void
     */
    private function dropTempTable(string $tableName): void
    {
        Schema::dropIfExists($tableName);
    }

    /**
     * Process a batch of product data.
     *
     * @param array $batch Array of product data
     * @return array Result with counts
     */
    private function processBatch(array $batch): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($batch as $data) {
            try {
                $result = $this->processProductRow($data);
                if ($result['action'] === 'created') {
                    $created++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::channel('scommerce')->error("sCommerce CSV Import Error: " . $e->getMessage());
            }
        }

        return compact('created', 'updated', 'errors');
    }

    /**
     * Process a batch from temporary table.
     *
     * @param array $batch Array of product data from temp table
     * @return array Result with counts
     */
    private function processBatchFromTempTable(array $batch): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($batch as $row) {
            try {
                $data = $this->inflateTempTableRow((array)$row);

                // Data from temp table already has correct field names
                $result = $this->processProductRow($data);

                if ($result['action'] === 'created') {
                    $created++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::channel('scommerce')->error("sCommerce CSV Import Error: " . $e->getMessage());
            }
        }

        return compact('created', 'updated', 'errors');
    }

    /**
     * Process a single product row from CSV data.
     *
     * This method handles the core logic of creating or updating a product
     * based on CSV data. It performs duplicate checking and validation.
     *
     * @param array $data CSV row data
     * @return array Result with action and product info
     * @throws \Exception If processing fails
     */
    private function processProductRow(array $data): array
    {
        $sCommerceController = new sCommerceController();
        $allowMultilang = $this->shouldUseMultilangCsv();
        $defaultLang = $this->csvDefaultLanguage($sCommerceController);
        $payloadByLang = $this->extractTextPayloadByLang($data, $defaultLang, $allowMultilang);
        $seoPayloadByLang = $this->extractSeoPayloadByLang($data, $defaultLang, $allowMultilang);
        $requestId = intval($data['id'] ?? 0);
        $alias = trim($data['alias'] ?? '') ?: 'new-product';
        $product = sCommerce::getProduct($requestId);
        $prodCats = $product->categories->mapWithKeys(function ($value) {return [$value->id => $value->pivot->position];})->all();

        if (empty($alias) || str_starts_with($alias, 'new-product')) {
            $translate = sProductTranslate::whereProduct($requestId)
                ->whereIn('lang', ['en', $sCommerceController->langDefault()])->orderByRaw('FIELD(lang, "en", "' . $sCommerceController->langDefault() . '")')
                ->first();
            if ($translate) {
                $alias = trim($translate->pagetitle) ?: 'new-product';
            } else {
                $alias = $this->resolveAliasFromPayload($payloadByLang, $defaultLang);
            }
        }

        $product->currency = trim($data['currency'] ?? sCommerce::config('basic.main_currency', 'USD'));
        if (isset($data['published'])) $product->published = intval($data['published'] ?? 0);
        if (isset($data['availability'])) $product->availability = intval($data['availability'] ?? 0);
        if (isset($data['sku'])) $product->sku = trim($data['sku'] ?? '');
        if (isset($data['alias'])) $product->alias = $sCommerceController->validateAlias($alias, (int)$product->id);
        if (isset($data['inventory']) && sCommerce::config('product.inventory_on', 0) == 2) $product->inventory = intval($data['inventory'] ?? 0);
        if (isset($data['price_regular'])) $product->price_regular = $sCommerceController->validatePrice($data['price_regular'] ?? 0);
        if (isset($data['price_special'])) $product->price_special = $sCommerceController->validatePrice($data['price_special'] ?? 0);
        if (isset($data['price_opt_regular'])) $product->price_opt_regular = $sCommerceController->validatePrice($data['price_opt_regular'] ?? 0);
        if (isset($data['price_opt_special'])) $product->price_opt_special = $sCommerceController->validatePrice($data['price_opt_special'] ?? 0);
        if (isset($data['weight'])) $product->weight = intval($data['weight'] ?? 0);
        if (isset($data['width'])) $product->width = intval($data['width'] ?? 0);
        if (isset($data['height'])) $product->height = intval($data['height'] ?? 0);
        if (isset($data['length'])) $product->length = intval($data['length'] ?? 0);
        if (isset($data['volume'])) $product->volume = intval($data['volume'] ?? 0);
        if (isset($data['cover'])) $product->cover = trim($data['cover'] ?? '/assets/site/noimage.png');
        if (isset($data['relevants'])) $product->relevants = json_encode(($data['relevants'] ?? []), JSON_UNESCAPED_UNICODE);
        if (isset($data['similar'])) $product->similar = json_encode(($data['similar'] ?? []), JSON_UNESCAPED_UNICODE);
        if (isset($data['tmplvars'])) $product->tmplvars = json_encode(($data['tmplvars'] ?? []), JSON_UNESCAPED_UNICODE);
        $product->save();

        // Process text fields (pagetitle, longtitle, introtext, content) incl. multilang
        if ($product->id) {
            \View::getFinder()->setPaths([EVO_BASE_PATH . 'assets/modules/scommerce/builder']);

            foreach ($payloadByLang as $lang => $payload) {
                $lang = strtolower(trim((string)$lang));
                if ($lang === '') continue;

                $translate = sProductTranslate::whereProduct($product->id)->whereLang($lang)->first();
                if (!$translate) {
                    $translate = new sProductTranslate();
                    $translate->product = $product->id;
                    $translate->lang = $lang;
                }

                if (array_key_exists('pagetitle', $payload)) $translate->pagetitle = trim((string)($payload['pagetitle'] ?? ''));
                if (array_key_exists('longtitle', $payload)) $translate->longtitle = trim((string)($payload['longtitle'] ?? ''));
                if (array_key_exists('introtext', $payload)) $translate->introtext = trim((string)($payload['introtext'] ?? ''));
                if (array_key_exists('content', $payload)) {
                    $contentField = view('richtext.render', ['id' => 'richtext', 'value' => trim((string)($payload['content'] ?? ''))])->render();
                    $contentField = str_replace([chr(9), chr(10), chr(13), '  '], '', $contentField);

                    $translate->content = $contentField;
                    $translate->builder = json_encode([["richtext" => $contentField]], JSON_UNESCAPED_UNICODE);
                }

                $translate->save();
            }
        }

        if ($product->id) {
            $this->syncProductSeo($product, $seoPayloadByLang, $defaultLang);
            if (array_key_exists('gallery', $data)) {
                $this->syncProductGallery($product, $data['gallery'] ?? null);
            }
        }

        // Process category field (domain:id format for multisite, simple IDs for single site)
        $categoryData = $data['category'] ?? '';
        $categories = [];

        if (!empty($categoryData)) {
            $inputCats = explode('||', $categoryData);
            foreach ($inputCats as $cat) {
                $cat = trim($cat);
                if (empty($cat)) continue;

                if (evo()->getConfig('check_sMultisite', false)) {
                    // Multisite mode: parse domain:id format
                    if (strpos($cat, ':') !== false) {
                        [$domain, $catId] = explode(':', $cat, 2);
                        $catId = intval($catId);
                        if ($catId > 0) {
                            $categories[$catId] = ['scope' => 'primary_' . $domain, 'position' => ($prodCats[$catId] ?? 0)];
                        }
                    } else {
                        // Fallback: treat as simple ID
                        $catId = intval($cat);
                        if ($catId > 0) {
                            $categories[$catId] = ['position' => ($prodCats[$catId] ?? 0)];
                        }
                    }
                } else {
                    // Single site mode: simple IDs
                    $catId = intval($cat);
                    if ($catId > 0) {
                        $categories[$catId] = ['position' => ($prodCats[$catId] ?? 0)];
                    }
                }
            }
        }

        // Process categories field (always simple IDs, cross-domain)
        $categoriesData = $data['categories'] ?? '';
        if (!empty($categoriesData)) {
            $inputCats = explode('||', $categoriesData);
            foreach ($inputCats as $cat) {
                $cat = trim($cat);
                if (empty($cat)) continue;

                $catId = intval($cat);
                if ($catId > 0) {
                    $categories[$catId] = ['position' => ($prodCats[$catId] ?? 0)];
                }
            }
        }

        if (evo()->getConfig('check_sMultisite', false)) {
            foreach(\Seiger\sMultisite\Models\sMultisite::all() as $domain) {
                $parent = intval($data['parent_' . $domain->key] ?? 0);
                if ($parent > 0) {
                    $categories[$parent] = ['scope' => 'primary_' . $domain->key, 'position' => ($prodCats[$parent] ?? 0)];
                }
            }
        } else {
            $parent = intval($data['parent'] ?? sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1)));
            if ($parent > 0) {
                $categories[$parent] = ['scope' => 'primary', 'position' => ($prodCats[$parent] ?? 0)];
            }
        }
        $product->categories()->sync([]);
        $product->categories()->sync($categories);
        $product->unsetRelation('categories');
        $product->load('categories');

        // Process attributes after categories are attached so category-bound attributes can be resolved.
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'attribute:')) {
                $attributeAlias = substr($key, 10); // Remove 'attribute:' prefix
                if (!empty($value)) {
                    $this->updateProductAttribute($product, $attributeAlias, $value);
                }
            }
        }

        if ($requestId) {
            return ['action' => 'updated', 'product' => $product];
        } else {
            return ['action' => 'created', 'product' => $product];
        }
    }

    /**
     * Export product attribute value in a CSV-friendly format.
     *
     * Handles multiselect attributes by returning all selected labels joined with `||`.
     */
    private function exportProductAttributeValue(sProduct $product, string $attributeAlias): string
    {
        $attribute = $product->attribute($attributeAlias);
        if (!$attribute) {
            return '';
        }

        if ((int)$attribute->type !== sAttribute::TYPE_ATTR_MULTISELECT) {
            return trim((string)($attribute->label ?? ''));
        }

        $selected = $product->attrValues()->whereAlias($attributeAlias)->get();
        if ($selected->isEmpty()) {
            return '';
        }

        $labels = [];
        foreach ($selected as $item) {
            $option = $item->values()->whereAvid((int)($item->pivot->valueid ?? 0))->first();
            $label = $this->attributeOptionDisplayValue($option);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));
        return implode('||', $labels);
    }

    /**
     * Resolve attribute option display value using current locale, then base, then alias.
     */
    private function attributeOptionDisplayValue(?sAttributeValue $option): string
    {
        if (!$option) {
            return '';
        }

        $locale = trim((string)evo()->getLocale());
        $value = '';

        if ($locale !== '' && isset($option->{$locale}) && trim((string)$option->{$locale}) !== '') {
            $value = trim((string)$option->{$locale});
        } elseif (isset($option->base) && trim((string)$option->base) !== '') {
            $value = trim((string)$option->base);
        } else {
            $value = trim((string)($option->alias ?? ''));
        }

        return $value;
    }

    /**
     * Resolve a CSV attribute option token to an attribute value id.
     */
    private function resolveAttributeOptionId(sAttribute $attribute, string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            $option = $attribute->values()->where('avid', (int)$value)->first();
            if ($option) {
                return (int)$option->avid;
            }
        }

        $locale = trim((string)evo()->getLocale());
        $query = $attribute->values();
        $query->where(function ($q) use ($value, $locale) {
            $q->where('alias', $value)
                ->orWhere('base', $value);

            if ($locale !== '' && Schema::hasColumn('s_attribute_values', $locale)) {
                $q->orWhere($locale, $value);
            }
        });

        $option = $query->first();

        if ($option) {
            return (int)$option->avid;
        }

        $option = $this->createAttributeOptionFromImport($attribute, $value);

        return (int)($option->avid ?? 0);
    }

    /**
     * Create a missing select-like attribute option from an imported CSV value.
     */
    private function createAttributeOptionFromImport(sAttribute $attribute, string $value): ?sAttributeValue
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $sCommerceController = new SCommerceController();
        $option = new sAttributeValue();
        $option->attribute = (int)$attribute->id;
        $option->position = ((int)$attribute->values()->max('position')) + 1;
        $option->alias = $this->uniqueAttributeOptionAlias($attribute, $value, $sCommerceController);
        $option->code = (int)$attribute->type === sAttribute::TYPE_ATTR_COLOR ? $value : '';
        $option->base = $value;

        foreach ($sCommerceController->langList() as $lang) {
            $lang = trim((string)$lang);
            if ($lang !== '' && $lang !== 'base' && Schema::hasColumn('s_attribute_values', $lang)) {
                $option->{$lang} = $value;
            }
        }

        $locale = trim((string)evo()->getLocale());
        if ($locale !== '' && $locale !== 'base' && Schema::hasColumn('s_attribute_values', $locale)) {
            $option->{$locale} = $value;
        }

        $option->save();

        return $option;
    }

    /**
     * Build a unique option alias inside the current attribute.
     */
    private function uniqueAttributeOptionAlias(sAttribute $attribute, string $value, SCommerceController $sCommerceController): string
    {
        $alias = $sCommerceController->validateAliasValues($value, 0, (int)$attribute->id);
        if ($alias === '') {
            $alias = (string)(((int)$attribute->values()->max('avid')) + 1);
        }

        $baseAlias = $alias;
        $counter = 1;
        while ($attribute->values()->where('alias', $alias)->exists()) {
            $alias = $baseAlias . $counter;
            $counter++;
        }

        return $alias;
    }

    /**
     * Update product attribute value.
     *
     * @param \Seiger\sCommerce\Models\sProduct $product
     * @param string $attributeAlias
     * @param mixed $value
     * @return void
     */
    private function updateProductAttribute(sProduct $product, string $attributeAlias, $value): void
    {
        if (!$product->id) {
            return;
        }

        $sCommerceController = new SCommerceController();
        $categoryParentsIds = [0];
        if ($product->categories) {
            foreach ($product->categories as $category) {
                $categoryParentsIds = array_merge($categoryParentsIds, $sCommerceController->categoryParentsIds($category->id));
            }
        }

        $attributes = sAttribute::lang($sCommerceController->langDefault())->whereHas('categories', function ($q) use ($categoryParentsIds) {
            $q->whereIn('category', $categoryParentsIds);
        })->get();

        $attribute = $attributes->where('alias', $attributeAlias)->first();
        if ($attribute) {
            $product->attrValues()->detach($attribute->id);
            switch ($attribute->type) {
                case sAttribute::TYPE_ATTR_NUMBER : // 0
                    if (trim($value)) {
                        if (is_float($value)) {
                            $value = floatval(str_replace(',', '.', $value));
                        } else {
                            $value = intval($value);
                        }
                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => $value]);
                    }
                    break;
                case sAttribute::TYPE_ATTR_CHECKBOX : // 1
                case sAttribute::TYPE_ATTR_PRICE_RANGE : // 16
                    if (trim($value)) {
                        $value = intval($value);
                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => $value]);
                    }
                    break;
                case sAttribute::TYPE_ATTR_SELECT : // 3
                case sAttribute::TYPE_ATTR_COLOR : // 8
                    if (trim((string) $value)) {
                        $valueId = $this->resolveAttributeOptionId($attribute, (string) $value);
                        if ($valueId > 0) {
                            $product->attrValues()->attach($attribute->id, ['valueid' => $valueId, 'value' => $valueId]);
                        }
                    }
                    break;
                case sAttribute::TYPE_ATTR_MULTISELECT : // 4
                    if (is_string($value)) {
                        $value = array_filter(array_map('trim', explode('||', $value)), static fn ($item) => $item !== '');
                    }

                    if (is_array($value) && count($value)) {
                        foreach ($value as $v) {
                            if (!trim((string) $v)) {
                                continue;
                            }

                            $vId = $this->resolveAttributeOptionId($attribute, (string) $v);
                            if ($vId > 0) {
                                $product->attrValues()->attach($attribute->id, ['valueid' => $vId, 'value' => $vId]);
                            }
                        }
                    }
                    break;
                case sAttribute::TYPE_ATTR_TEXT : // 5
                    if (is_array($value) && count($value)) {
                        $vals = [];
                        foreach ($value as $k => $v) {
                            if (trim($v)) {
                                $vals[$k] = trim($v);
                            }
                        }
                    } elseif (is_string($value) && trim($value)) {
                        $vals['base'] = trim($value);
                    }

                    if (isset($vals) && count($vals)) {
                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => json_encode($vals, JSON_UNESCAPED_UNICODE)]);
                    }
                    break;
                case sAttribute::TYPE_ATTR_CUSTOM : // 15
                    if (is_array($value) && count($value)) {
                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
                    } elseif (is_string($value) && trim($value)) {
                        $product->attrValues()->attach($attribute->id, ['valueid' => 0, 'value' => trim($value)]);
                    }
                    break;
            }
        }
    }

    /**
     * Clean up old temporary files and logs.
     * Removes files older than 24 hours from export and import directories.
     *
     * @return void
     */
    public function cleanupOldFiles(): void
    {
        try {
            $exportDir = storage_path(self::EXPORT_DIR);
            $importDir = storage_path(self::IMPORT_DIR);
            $logDir = storage_path('logs');

            $cutoffTime = time() - (24 * 60 * 60); // 24 hours ago

            // Clean export files
            if (is_dir($exportDir)) {
                $this->cleanupDirectory($exportDir, $cutoffTime);
            }

            // Clean import files
            if (is_dir($importDir)) {
                $this->cleanupDirectory($importDir, $cutoffTime);
            }

            // Clean log files (sCommerce specific)
            if (is_dir($logDir)) {
                $this->cleanupDirectory($logDir, $cutoffTime, ['scommerce.log']);
            }

            Log::channel('scommerce')->info('Old files cleanup completed');
        } catch (\Exception $e) {
            Log::channel('scommerce')->error('Failed to cleanup old files', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clean up files in a directory older than specified time.
     *
     * @param string $directory Directory path
     * @param int $cutoffTime Unix timestamp cutoff
     * @param array $allowedExtensions Optional array of allowed file extensions
     * @return void
     */
    private function cleanupDirectory(string $directory, int $cutoffTime, array $allowedExtensions = []): void
    {
        $files = glob($directory . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = filemtime($file);

                // Check if file is older than cutoff time
                if ($fileTime < $cutoffTime) {
                    // If allowedExtensions is specified, check file extension
                    if (!empty($allowedExtensions)) {
                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowedExtensions)) {
                            continue;
                        }
                    }

                    // Delete the file
                    unlink($file);
                    Log::channel('scommerce')->debug('Deleted old file', ['file' => basename($file)]);
                }
            }
        }
    }

    /**
     * Check whether multilingual CSV mode should be enabled.
     *
     * @return bool True when sLang is enabled and available.
     */
    private function shouldUseMultilangCsv(): bool
    {
        $enabled = (bool)evo()->getConfig('check_sLang', false);
        if (!$enabled) {
            return false;
        }

        // Soft check: avoid fatal if package is missing despite config.
        return class_exists(\Seiger\sLang\Facades\sLang::class);
    }

    /**
     * Get the normalized list of languages used for CSV import/export.
     *
     * @param sCommerceController $controller Controller used to resolve language metadata.
     * @return array Language code list.
     */
    private function csvLanguagesList(sCommerceController $controller): array
    {
        $langs = $controller->langList();
        $langs = array_values(array_unique(array_filter(array_map(function ($lang) {
            $lang = strtolower(trim((string)$lang));
            return $lang !== '' ? $lang : null;
        }, $langs))));

        if (empty($langs)) {
            $def = strtolower(trim($controller->langDefault()));
            return $def !== '' ? [$def] : ['base'];
        }

        return $langs;
    }

    /**
     * Get the normalized default language code for CSV fallback behavior.
     *
     * @param sCommerceController $controller Controller used to resolve language metadata.
     * @return string Default language code.
     */
    private function csvDefaultLanguage(sCommerceController $controller): string
    {
        $default = strtolower(trim($controller->langDefault()));
        return $default !== '' ? $default : 'base';
    }

    /**
     * Map CSV header labels to internal field keys.
     *
     * Supports:
     * - single-language columns when multilingual mode is disabled
     * - multilang columns with language index (e.g. "Назва товару [uk]")
     */
    private function importHeaderLabelToFieldMap(): array
    {
        $sCommerceController = new sCommerceController();

        $legacy = $this->buildHeaderRow(false, $sCommerceController);
        $map = array_flip($legacy);
        foreach (array_keys($legacy) as $field) {
            $map[$field] = $field;
        }

        if ($this->shouldUseMultilangCsv()) {
            $multilang = $this->buildHeaderRow(true, $sCommerceController);
            foreach (array_flip($multilang) as $label => $field) {
                $map[$label] = $field;
            }

            foreach (array_keys($multilang) as $field) {
                $map[$field] = $field;
            }
        }

        return $map;
    }

    /**
     * Build an internal CSV key for multilingual product text fields.
     *
     * @param string $lang Language code.
     * @param string $field Product text field name.
     * @return string Composite CSV key.
     */
    private function buildLangTextKey(string $lang, string $field): string
    {
        return 'lang.' . strtolower(trim($lang)) . '.' . strtolower(trim($field));
    }

    /**
     * Build an internal CSV key for multilingual SEO fields.
     *
     * @param string $lang Language code.
     * @param string $field SEO field name.
     * @return string Composite CSV key.
     * @since 1.0.12
     */
    private function buildLangSeoKey(string $lang, string $field): string
    {
        return 'seo.' . strtolower(trim($lang)) . '.' . strtolower(trim($field));
    }

    /**
     * Parse a multilingual text CSV key into language and field parts.
     *
     * @param string $key Internal CSV key.
     * @return array|null Parsed key parts or null when the key does not match.
     */
    private function parseLangIndexedTextKey(string $key): ?array
    {
        if (!preg_match('/^lang\.([a-z0-9_\-]+)\.(pagetitle|longtitle|introtext|content)$/i', $key, $m)) {
            return null;
        }

        return [
            'lang' => strtolower($m[1]),
            'field' => strtolower($m[2]),
        ];
    }

    /**
     * Parse a multilingual SEO CSV key into language and field parts.
     *
     * @param string $key Internal CSV key.
     * @return array|null Parsed key parts or null when the key does not match.
     * @since 1.0.12
     */
    private function parseLangIndexedSeoKey(string $key): ?array
    {
        if (!preg_match('/^seo\.([a-z0-9_\-]+)\.(meta_title|meta_description|meta_keywords|canonical_url)$/i', $key, $m)) {
            return null;
        }

        return [
            'lang' => strtolower($m[1]),
            'field' => strtolower($m[2]),
        ];
    }

    /**
     * Extract product text values from an import row and group them by language.
     *
     * @param array $data Normalized import row.
     * @param string $defaultLang Default language for non-indexed columns.
     * @param bool $allowMultilang Whether multilingual keys should be parsed.
     * @return array Text payload grouped by language.
     */
    private function extractTextPayloadByLang(array $data, string $defaultLang, bool $allowMultilang = true): array
    {
        $defaultLang = strtolower(trim($defaultLang)) ?: 'base';
        $payloadByLang = [];

        foreach ($data as $key => $value) {
            if (in_array($key, self::TEXT_FIELDS, true)) {
                $payloadByLang[$defaultLang][$key] = $value;
                continue;
            }

            if (!$allowMultilang) {
                continue;
            }

            $parsed = $this->parseLangIndexedTextKey((string)$key);
            if ($parsed && in_array($parsed['field'], self::TEXT_FIELDS, true)) {
                $payloadByLang[$parsed['lang']][$parsed['field']] = $value;
            }
        }

        return $payloadByLang;
    }

    /**
     * Extract SEO values from an import row and group them by language.
     *
     * @param array $data Normalized import row.
     * @param string $defaultLang Default language for non-indexed columns.
     * @param bool $allowMultilang Whether multilingual keys should be parsed.
     * @return array SEO payload grouped by language.
     * @since 1.0.12
     */
    private function extractSeoPayloadByLang(array $data, string $defaultLang, bool $allowMultilang = true): array
    {
        if (!$this->hasSeoSupport()) {
            return [];
        }

        $defaultLang = strtolower(trim($defaultLang)) ?: 'base';
        $payloadByLang = [];

        foreach ($data as $key => $value) {
            if (in_array($key, self::SEO_FIELDS, true)) {
                $payloadByLang[$defaultLang][$key] = $value;
                continue;
            }

            if (!$allowMultilang) {
                continue;
            }

            $parsed = $this->parseLangIndexedSeoKey((string)$key);
            if ($parsed && in_array($parsed['field'], self::SEO_FIELDS, true)) {
                $payloadByLang[$parsed['lang']][$parsed['field']] = $value;
            }
        }

        return $payloadByLang;
    }

    /**
     * Normalize a raw CSV row into an associative array keyed by internal field names.
     *
     * @param array $header Original CSV header row.
     * @param array $row Original CSV data row.
     * @param array $headerKeys Map of external labels to internal field names.
     * @return array Normalized row data.
     */
    private function normalizeImportRowData(array $header, array $row, array $headerKeys): array
    {
        $row = array_pad($row, count($header), '');
        $row = array_slice($row, 0, count($header));
        $dirtyData = array_combine($header, $row);
        if (!$dirtyData) {
            return [];
        }

        $data = [];
        foreach ($dirtyData as $key => $item) {
            $normalizedKey = $headerKeys[$key] ?? $key;
            $data[$normalizedKey] = $item;
        }

        return $data;
    }

    /**
     * Flatten multilingual text payload into CSV-style field keys.
     *
     * @param array $payloadByLang Text payload grouped by language.
     * @return array Flattened text payload.
     */
    private function flattenTextPayloadByLang(array $payloadByLang): array
    {
        $flat = [];
        foreach ($payloadByLang as $lang => $payload) {
            $lang = strtolower(trim((string)$lang));
            if ($lang === '') {
                continue;
            }

            foreach ((array)$payload as $field => $value) {
                $field = strtolower(trim((string)$field));
                if (!in_array($field, self::TEXT_FIELDS, true)) {
                    continue;
                }

                $flat[$this->buildLangTextKey($lang, $field)] = $value;
            }
        }

        return $flat;
    }

    /**
     * Flatten multilingual SEO payload into CSV-style field keys.
     *
     * @param array $payloadByLang SEO payload grouped by language.
     * @return array Flattened SEO payload.
     * @since 1.0.12
     */
    private function flattenSeoPayloadByLang(array $payloadByLang): array
    {
        $flat = [];
        foreach ($payloadByLang as $lang => $payload) {
            $lang = strtolower(trim((string)$lang));
            if ($lang === '') {
                continue;
            }

            foreach ((array)$payload as $field => $value) {
                $field = strtolower(trim((string)$field));
                if (!in_array($field, self::SEO_FIELDS, true)) {
                    continue;
                }

                $flat[$this->buildLangSeoKey($lang, $field)] = $value;
            }
        }

        return $flat;
    }

    /**
     * Restore temp-table row payload back into the normalized import format.
     *
     * @param array $data Temp-table row data.
     * @return array Inflated import row.
     */
    private function inflateTempTableRow(array $data): array
    {
        $attributes = $this->decodeJsonToArray($data['attributes'] ?? null);
        $textPayload = $this->decodeJsonToArray($data['text_payload'] ?? null);
        $seoPayload = $this->decodeJsonToArray($data['seo_payload'] ?? null);

        unset($data['attributes'], $data['text_payload'], $data['seo_payload'], $data['processed'], $data['row_id']);

        foreach ($attributes as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($this->flattenTextPayloadByLang($textPayload) as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($this->flattenSeoPayloadByLang($seoPayload) as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Decode a JSON payload into an array with safe fallback.
     *
     * @param mixed $value JSON string candidate.
     * @return array Decoded array or an empty array.
     */
    private function decodeJsonToArray($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode an array payload into JSON for temp-table persistence.
     *
     * @param array $value Payload to encode.
     * @return string|null Encoded JSON or null for an empty payload.
     */
    private function encodeJson(array $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

    /**
     * Resolve a fallback alias source from multilingual text payload.
     *
     * @param array $payloadByLang Text payload grouped by language.
     * @param string $defaultLang Default language code.
     * @return string Alias source string.
     */
    private function resolveAliasFromPayload(array $payloadByLang, string $defaultLang): string
    {
        $candidates = ['en', strtolower(trim($defaultLang)), 'base'];
        foreach ($candidates as $lang) {
            $pagetitle = trim((string)($payloadByLang[$lang]['pagetitle'] ?? ''));
            if ($pagetitle !== '') {
                return $pagetitle;
            }
        }

        foreach ($payloadByLang as $payload) {
            $pagetitle = trim((string)($payload['pagetitle'] ?? ''));
            if ($pagetitle !== '') {
                return $pagetitle;
            }
        }

        return 'new-product';
    }

    /**
     * Get a translated product text field for a specific language.
     *
     * @param sProduct $product Product model.
     * @param string $lang Language code.
     * @param string $field Product text field name.
     * @return mixed Field value or empty string.
     */
    private function getProductTranslateField(sProduct $product, string $lang, string $field)
    {
        $lang = strtolower(trim($lang));
        $field = strtolower(trim($field));
        if ($lang === '' || !in_array($field, self::TEXT_FIELDS, true)) {
            return '';
        }

        // Prefer already eager-loaded translations to avoid N+1 queries.
        if ($product->relationLoaded('texts')) {
            $translate = $product->texts->firstWhere('lang', $lang);
            return $translate?->{$field} ?? '';
        }

        return sProductTranslate::whereProduct($product->id)->whereLang($lang)->first()?->{$field} ?? '';
    }

    /**
     * Check whether sGallery support is available in the current installation.
     *
     * @return bool True when the gallery package and table exist.
     * @since 1.0.12
     */
    private function hasGallerySupport(): bool
    {
        return class_exists(sGalleryModel::class) && Schema::hasTable('s_galleries');
    }

    /**
     * Check whether sSeo support is available in the current installation.
     *
     * @return bool True when the SEO package and table exist.
     * @since 1.0.12
     */
    private function hasSeoSupport(): bool
    {
        return class_exists(sSeoModel::class) && Schema::hasTable('s_seo');
    }

    /**
     * Export gallery images for a product into a CSV-friendly string.
     *
     * @param sProduct $product Product model.
     * @return string Gallery items joined with the multivalue separator.
     * @since 1.0.12
     */
    private function exportProductGallery(sProduct $product): string
    {
        if (!$this->hasGallerySupport() || !$product->id) {
            return '';
        }

        $query = sGalleryModel::where('parent', $product->id)
            ->where('item_type', 'product')
            ->where('type', sGalleryModel::TYPE_IMAGE)
            ->orderBy('position');

        if (Schema::hasColumn('s_galleries', 'block')) {
            $query->where('block', '1');
        }

        return $query->get()
            ->map(function ($image) {
                return $this->normalizeCsvMediaPath((string)($image->src ?? $image->file ?? ''));
            })
            ->filter(fn($item) => $item !== '')
            ->implode('||');
    }

    /**
     * Normalize media paths for CSV output by removing local host prefixes.
     *
     * @param string $value Raw media path or URL.
     * @return string Normalized relative path.
     * @since 1.0.12
     */
    private function normalizeCsvMediaPath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^https?://localhost\.?/#i', '', $value) ?? $value;
        $value = preg_replace('#^https?://127\.0\.0\.1/?#i', '', $value) ?? $value;

        return ltrim($value, '/');
    }

    /**
     * Split imported gallery data into unique normalized items.
     *
     * @param string|null $gallery Raw gallery CSV value.
     * @return array Normalized gallery items.
     * @since 1.0.12
     */
    private function normalizeGalleryImportItems(?string $gallery): array
    {
        $items = [];
        foreach (explode('||', (string)$gallery) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $items[] = $this->normalizeGalleryImportPath($item);
        }

        return array_values(array_unique(array_filter($items, fn($item) => $item !== '')));
    }

    /**
     * Normalize a single imported gallery item path before storage.
     *
     * @param string $path Raw path or URL from CSV.
     * @return string Normalized storage path or external URL.
     * @since 1.0.12
     */
    private function normalizeGalleryImportPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (sGallery::hasLink($path)) {
            return $path;
        }

        $path = preg_replace('/^https?:\/\/[^\/]+/i', '', $path) ?? $path;
        return '/' . ltrim($path, '/');
    }

    /**
     * Replace product gallery records with the values imported from CSV.
     *
     * @param sProduct $product Product model.
     * @param mixed $gallery Raw gallery payload from CSV.
     * @return void
     * @since 1.0.12
     */
    private function syncProductGallery(sProduct $product, $gallery): void
    {
        if (!$this->hasGallerySupport()) {
            return;
        }

        $items = $this->normalizeGalleryImportItems((string)$gallery);

        $query = sGalleryModel::where('parent', $product->id)
            ->where('item_type', 'product')
            ->where('type', sGalleryModel::TYPE_IMAGE);

        if (Schema::hasColumn('s_galleries', 'block')) {
            $query->where('block', '1');
        }

        $query->delete();

        foreach ($items as $position => $item) {
            $galleryModel = new sGalleryModel();
            $galleryModel->parent = $product->id;
            $galleryModel->position = $position;
            $galleryModel->file = $item;
            $galleryModel->type = sGalleryModel::TYPE_IMAGE;
            $galleryModel->item_type = 'product';
            if (Schema::hasColumn('s_galleries', 'block')) {
                $galleryModel->block = '1';
            }
            $galleryModel->save();
        }
    }

    /**
     * Get a single SEO field value for a product and language.
     *
     * @param sProduct $product Product model.
     * @param string $lang Language code.
     * @param string $field SEO field name.
     * @return string SEO field value or empty string.
     * @since 1.0.12
     */
    private function getProductSeoField(sProduct $product, string $lang, string $field): string
    {
        if (!$this->hasSeoSupport() || !$product->id || !in_array($field, self::SEO_FIELDS, true)) {
            return '';
        }

        $lang = strtolower(trim($lang)) ?: 'base';
        $query = sSeoModel::where('resource_id', $product->id)
            ->where('resource_type', 'product')
            ->where('domain_key', 'default');

        if ($lang === 'base') {
            $defaultLang = $this->csvDefaultLanguage(new sCommerceController());
            $item = $query->whereIn('lang', ['base', $defaultLang])
                ->orderByRaw("FIELD(lang, 'base', '{$defaultLang}')")
                ->first();
        } else {
            $item = $query->where('lang', $lang)->first();
        }

        return trim((string)($item?->{$field} ?? ''));
    }

    /**
     * Persist imported SEO payload for a product across languages.
     *
     * @param sProduct $product Product model.
     * @param array $payloadByLang SEO payload grouped by language.
     * @param string $defaultLang Default language code.
     * @return void
     * @since 1.0.12
     */
    private function syncProductSeo(sProduct $product, array $payloadByLang, string $defaultLang): void
    {
        if (!$this->hasSeoSupport() || !$product->id || empty($payloadByLang)) {
            return;
        }

        $defaultLang = strtolower(trim($defaultLang)) ?: 'base';

        foreach ($payloadByLang as $lang => $payload) {
            $lang = strtolower(trim((string)$lang));
            if ($lang === '') {
                continue;
            }

            $targetLang = $lang === $defaultLang ? 'base' : $lang;
            $item = sSeoModel::where('resource_id', $product->id)
                ->where('resource_type', 'product')
                ->where('domain_key', 'default')
                ->whereIn('lang', $targetLang === 'base' ? ['base', $defaultLang] : [$targetLang])
                ->orderByRaw($targetLang === 'base' ? "FIELD(lang, 'base', '{$defaultLang}')" : "FIELD(lang, '{$targetLang}')")
                ->first();

            if (!$item) {
                $item = new sSeoModel();
                $item->resource_id = $product->id;
                $item->resource_type = 'product';
                $item->domain_key = 'default';
                $item->lang = $targetLang;
            } else {
                $item->lang = $targetLang;
            }

            foreach (self::SEO_FIELDS as $field) {
                if (array_key_exists($field, $payload)) {
                    $item->{$field} = trim((string)($payload[$field] ?? ''));
                }
            }

            $item->save();
        }
    }

    /**
     * Sanitize scalar values before writing them to CSV cells.
     *
     * @param mixed $value Source value.
     * @return string Sanitized CSV-safe string.
     */
    private function sanitizeCsvValue($value): string
    {
        $value = (string)($value ?? '');
        return str_replace(['"', "'", "\n", "\r", "\t"], ['""', "''", ' ', ' ', ' '], $value);
    }

    /**
     * Get settings for this worker
     *
     * @return array
     */
    public function settings(): array
    {
        return [
            'export_dir' => self::EXPORT_DIR,
            'max_file_age_days' => 7,
            'allowed_extensions' => self::ALLOWED_EXTENSIONS,
        ];
    }
}
