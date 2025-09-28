<?php namespace Seiger\sCommerce\Integration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Integration\BaseIntegration;
use Seiger\sCommerce\Integration\IntegrationActionController;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sCommerce\Models\sIntegrationTask;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sProductTranslate;
use Seiger\sGallery\Facades\sGallery;

/**
 * ImportExportCSV - Integration for CSV import/export operations
 *
 * This class implements the CSV import/export integration for sCommerce products.
 * It extends BaseIntegration and provides comprehensive functionality for exporting
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
class ImportExportCSV extends BaseIntegration
{
    /** Directory where this integration puts its CSV exports. */
    private const EXPORT_DIR = 'scommerce/exports';
    /** Keep export files for N days. */
    private const EXPORT_TTL_DAYS = 7;
    /** Run export GC at most once per hour. */
    private const EXPORT_GC_MIN_INTERVAL = 3600;

    /**
     * Get the unique name of the BaseIntegration.
     *
     * The name is used as an identifier for this integration throughout the system.
     *
     * @return string The unique name of the integration.
     */
    public function getKey(): string
    {
        return 'simpexpcsv';
    }

    /**
     * Get the admin display icon for the ImportExportCSV integration.
     *
     * Retrieves the icon for the integration to be displayed in the admin panel.
     *
     * @return string The formatted icon for admin display.
     */
    public function getIcon(): string
    {
        return '<i class="far fa-file-alt"></i>';
    }

    /**
     * Get the admin display title for the ImportExportCSV integration.
     *
     * Retrieves the title for the integration to be displayed in the admin panel.
     *
     * @return string The formatted title for admin display.
     */
    public function getTitle(): string
    {
        return __('sCommerce::global.import') . ' / ' . __('sCommerce::global.export') . ' CSV';
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
        return view('sCommerce::partials.widgetImportExportCSV', [
            'instance'  => $this,
            'key'       => $this->getKey(),
            'title'     => $this->getTitle(),
            'icon'      => $this->getIcon(),
            'class'     => static::class,
        ])->render();
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
     * @param sIntegrationTask $task  Task model.
     * @param array            $opt   Action options (CSV formatting, etc.)
     * @return void
     */
    public function taskExport(sIntegrationTask $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');

        try {
            // CSV defaults
            $opt = array_merge([
                'delimiter' => ';',
                'enclosure' => '"',
                'escape'    => '\\',
                'add_bom'   => true,
            ], $opt);

            // Preparing
            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_PREPARING,
                'message' => __('sCommerce::global.task_running') . '...',
            ]);
            $this->pushProgress($task);

            // Take the very first product (fail fast if none)
            $products = sProduct::with('categories')->orderBy('id')->get();

            if (!$products) {
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

            $total     = $products->count();
            $processed = 0;
            $headerRow = array_keys($this->headerRow());

            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_RUNNING,
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
            $eta = '—'; // default ETA display

            foreach ($products as $p) {
                $a = [];
                foreach ($headerRow as $field) {
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
                            $value = $p?->attribute($alias)?->label ?? '';
                            $a[$field] = str_replace(['"', "'", "\n", "\r", "\t"], ['""', "''", ' ', ' ', ' '], $value);
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
                        case 'categories':
                            // Cross-domain category list: always return simple IDs
                            $categoryIds = $p?->categories->pluck('id')->toArray();
                            $a[$field] = implode('||', $categoryIds);
                            break;
                        default:
                            $value = $p?->{$field} ?? '';
                            $a[$field] = str_replace(['"', "'", "\n", "\r", "\t"], ['""', "''", ' ', ' ', ' '], $value);
                            break;
                    }
                }

                if (count($a)) {
                    $this->putCsv($fh, $a, $opt);
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
                        $eta = $this->formatEta($etaSeconds);
                    } else {
                        $eta = '—';
                    }
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
            // --- end 1% emitter ---

            // Saving
            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_SAVING,
                'message' => __('sCommerce::global.preparing_file') . '...',
            ]);
            $this->pushProgress($task, ['progress' => 99]);

            fclose($fh);

            // Done - use helper method
            $downloadUrl = '/' . str_replace(['https://localhost', EVO_CORE_PATH], '', route('sCommerce.integrations.download', ['id' => $task->id]));
            $this->markFinished(
                $task,
                $path,
                '**' . __('sCommerce::global.done') . '. [📥 ' . __('sCommerce::global.download') . '](' . $downloadUrl . ')**'
            );

            self::maybePruneExports();
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
     * @param sIntegrationTask $task The task instance containing execution context
     * @param array $opt Additional options for import process
     * @return void
     * @throws \RuntimeException If file cannot be opened or processed
     */
    public function taskImport(sIntegrationTask $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');
        @ini_set('memory_limit', '2G');
        @set_time_limit(0);

        try {
            // CSV defaults
            $opt = array_merge([
                'delimiter' => ';',
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
            $response = (new IntegrationActionController)->serverLimits();
            $serverLimits = $response->getData(true);
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

    /** CSV write helper. */
    protected function putCsv($fh, array $row, array $opt): void
    {
        fputcsv($fh, $row, $opt['delimiter'], $opt['enclosure'], $opt['escape']);
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
            $tasks = sIntegrationTask::query()
                ->where('slug', (new static)->getKey())
                ->whereNotNull('result')
                ->where('status', sIntegrationTask::TASK_STATUS_FINISHED)
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
            
            // Categories and relationships
            'category' => __('sCommerce::global.category'),
            // 'categories' => __('sCommerce::global.categories'),
            // 'relevants' => __('sCommerce::global.relevant'),
            // 'similar' => __('sCommerce::global.relevant'),

            // Product translations
            'pagetitle' => __('sCommerce::global.product_name'),
            'longtitle' => __('global.long_title'),
            'introtext' => __('global.resource_summary'),
            'content' => __('sCommerce::global.content'),
            
            // Additional data
            // 'tmplvars' => __('sCommerce::global.additional_fields_main_product_tab'),
            // 'additional' => __('sCommerce::global.additional_fields_to_products_tab'),
            // 'representation' => __('sCommerce::global.representation_products_fields'),

            // Timestamps
            // 'created_at' => __('sCommerce::global.created'),
            // 'updated_at' => __('sCommerce::global.created'),
        ];

        // Related Attributes
        $attributes = [];
        $attributesQuery = sAttribute::lang((new sCommerceController)->langDefault())->active()->get();

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
     * @param sIntegrationTask $task The task instance
     * @param string $filePath Path to CSV file
     * @param array $opt Import options
     * @return void
     */
    private function importWithStreaming(sIntegrationTask $task, string $filePath, array $opt): void
    {
        $task->update([
            'status'  => sIntegrationTask::TASK_STATUS_PREPARING,
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
            'status'  => sIntegrationTask::TASK_STATUS_RUNNING,
            'message' => __('sCommerce::global.importing') . '...',
        ]);
        $this->pushProgress($task, [
            'progress'  => 0,
            'processed' => 0,
            'total'     => $totalLines,
        ]);

        $headerKeys = array_flip($this->headerRow());

        // Process each row in batches
        while (($row = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape'])) !== false) {
            try {
                $dirtyData = array_combine($header, $row);
                if (!$dirtyData) {
                    $errors++;
                    continue;
                }

                $data = [];
                foreach ($dirtyData as $key => $item) {
                    if (isset($headerKeys[$key])) {
                        $data[$headerKeys[$key]] = $item;
                    }
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
                            $eta = $this->formatEta($etaSeconds);
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
                    $eta = $this->formatEta($etaSeconds);
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
     * @param sIntegrationTask $task The task instance
     * @param string $filePath Path to CSV file
     * @param array $opt Import options
     * @return void
     */
    private function importWithTempTable(sIntegrationTask $task, string $filePath, array $opt): void
    {
        $task->update([
            'status'  => sIntegrationTask::TASK_STATUS_PREPARING,
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
            $table->integer('category')->nullable();
            $table->string('pagetitle', 255)->nullable();
            $table->string('longtitle', 255)->nullable();
            $table->text('introtext')->nullable();
            $table->longText('content')->nullable();
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

        $batch = [];
        $batchSize = 1000; // Larger batch for temp table loading

        // Process each row
        while (($row = fgetcsv($fh, 0, $opt['delimiter'], $opt['enclosure'], $opt['escape'])) !== false) {
            $data = array_combine($header, $row);
            if (!$data) {
                continue;
            }

            // Prepare data for temp table
            $tempData = [
                'id' => !empty($data['ID']) && is_numeric($data['ID']) ? (int)$data['ID'] : null,
                'sku' => $data['Артикул'] ?? null,
                'alias' => $data['Посилання на товар'] ?? null,
                'published' => !empty($data['Видимість']) ? (int)$data['Видимість'] : 1,
                'availability' => !empty($data['Доступність']) ? (int)$data['Доступність'] : 0,
                'inventory' => !empty($data['Залишок']) ? (int)$data['Залишок'] : 0,
                'price_regular' => !empty($data['Ціна']) ? (float)$data['Ціна'] : 0.00,
                'price_special' => !empty($data['Спеціальна ціна']) ? (float)$data['Спеціальна ціна'] : null,
                'currency' => $data['Валюта'] ?? 'UAH',
                'cover' => $data['Обкладинка'] ?? null,
                'category' => !empty($data['Категорія']) ? (int)$data['Категорія'] : null,
                'pagetitle' => $data['Назва товару'] ?? null,
                'longtitle' => $data['Розширений заголовок'] ?? null,
                'introtext' => $data['Анотація (введення)'] ?? null,
                'content' => $data['Контент'] ?? null,
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
            if (str_starts_with($key, 'Атрибут ')) {
                $attributeName = substr($key, 9); // Remove "Атрибут " prefix
                if (!empty($value)) {
                    $attributes[$attributeName] = $value;
                }
            }
        }

        return !empty($attributes) ? json_encode($attributes) : null;
    }

    /**
     * Process data from temporary table.
     *
     * @param sIntegrationTask $task The task instance
     * @param string $tableName Name of temporary table
     * @param array $opt Import options
     * @return void
     */
    private function processFromTempTable(sIntegrationTask $task, string $tableName, array $opt): void
    {
        $totalRows = \DB::table($tableName)->count();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        $task->update([
            'status'  => sIntegrationTask::TASK_STATUS_RUNNING,
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
            $batchIds = $batch->pluck('id')->filter()->toArray();
            if (!empty($batchIds)) {
                \DB::table($tableName)
                    ->whereIn('id', $batchIds)
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
                $data = (array) $row;
                
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
                $alias = 'new-product';
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

        // Process text fields (pagetitle, longtitle, introtext, content)
        if ($product->id) {
            $lang = $sCommerceController->langDefault();
            $translate = sProductTranslate::whereProduct($product->id)->whereLang($lang)->first();
            \View::getFinder()->setPaths([EVO_BASE_PATH . 'assets/modules/scommerce/builder']);
            
            if (!$translate) {
                $translate = new sProductTranslate();
                $translate->product = $product->id;
                $translate->lang = $lang;
            }
            
            if (isset($data['pagetitle'])) $translate->pagetitle = trim($data['pagetitle'] ?? '');
            if (isset($data['longtitle'])) $translate->longtitle = trim($data['longtitle'] ?? '');
            if (isset($data['introtext'])) $translate->introtext = trim($data['introtext'] ?? '');
            if (isset($data['content'])) {
                $contentField = view('richtext.render', ['id' => 'richtext', 'value' => trim($data['content'] ?? '')])->render();
                $contentField = str_replace([chr(9), chr(10), chr(13), '  '], '', $contentField);

                $translate->content = $contentField;
                $translate->builder = json_encode([["richtext" => $contentField]], JSON_UNESCAPED_UNICODE);
            }
            
            $translate->save();
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
        
        // Process attributes
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'attribute:')) {
                $attributeAlias = substr($key, 10); // Remove 'attribute:' prefix
                if (!empty($value)) {
                    $this->updateProductAttribute($product, $attributeAlias, $value);
                }
            }
        }
        
        $product->categories()->sync([]);
        $product->categories()->sync($categories);
        
        if ($requestId) {
            return ['action' => 'updated', 'product' => $product];
        } else {
            return ['action' => 'created', 'product' => $product];
        }
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
                    if (trim($value)) {
                        $valueId = sAttributeValue::where('base', $value)->first()?->avid ?? 0;
                        $product->attrValues()->attach($attribute->id, ['valueid' => $valueId, 'value' => $valueId]);
                    }
                    break;
                case sAttribute::TYPE_ATTR_MULTISELECT : // 4
                    if (is_array($value) && count($value)) {
                        foreach ($value as $k => $v) {
                            if (trim($v)) {
                                $vId = intval($v);
                                $product->attrValues()->attach($attribute->id, ['valueid' => $vId, 'value' => $v]);
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
}
