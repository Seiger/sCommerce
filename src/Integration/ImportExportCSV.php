<?php namespace Seiger\sCommerce\Integration;

use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Integration\BaseIntegration;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sIntegration;
use Seiger\sCommerce\Models\sIntegrationTask;
use Seiger\sCommerce\Models\sProduct;

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
            'exportUrl' => route('sCommerce.integrations.task.start', ['key' => $this->getKey(), 'action' => 'export']),
            'importUrl' => route('sCommerce.integrations.task.start', ['key' => $this->getKey(), 'action' => 'import']),
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
                'delimiter' => ',',
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
            $products = sProduct::orderBy('id')->get();

            if (!$products) {
                throw new \RuntimeException('No products found to export.');
            }

            // Ensure export dir e.g. storage/scommerce/exports
            $dirAbs = storage_path(self::EXPORT_DIR);
            if (!is_dir($dirAbs)) {
                @mkdir($dirAbs, 0775, true);
            }

            $name = 'products_' . date('Ymd_His') . "_{$task->id}.csv";
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
                            $a[$field] = $p?->attribute($alias)?->label ?? '';
                            break;
                        default:
                            $a[$field] = $p?->{$field} ?? '';
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

    /** CSV write helper. */
    protected function putCsv($fh, array $row, array $opt): void
    {
        fputcsv($fh, $row, $opt['delimiter'], $opt['enclosure'], $opt['escape']);
    }

    /**
     * Format ETA seconds into human-readable format.
     *
     * @param float $seconds
     * @return string
     */
    protected function formatEta(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.0fs', $seconds);
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%.0fm %.0fs', $minutes, $remainingSeconds);
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%.0fh %.0fm', $hours, $minutes);
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
                $attributes['attribute:' . $attr->alias] = __('sCommerce::global.attribute') . ' ' . $attr->pagetitle;
            }
        }

        return array_merge($product, $attributes);
    }
}
