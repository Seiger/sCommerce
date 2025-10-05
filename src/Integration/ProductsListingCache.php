<?php namespace Seiger\sCommerce\Integration;

use Illuminate\Support\Facades\Cache;
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
 * ProductsListingCache - Integration for caching product listings
 *
 * This class implements the product listing cache integration for sCommerce.
 * It extends BaseIntegration and provides comprehensive functionality for caching
 * product listings with optimized cursor pagination and memory control.
 *
 * Features:
 * - Cursor pagination for large datasets (130k+ records)
 * - Memory usage control with automatic batch size adjustment
 * - Progress tracking with ETA calculation
 * - Composite cursor using (product_id, category, scope)
 * - Garbage collection between batches
 * - Error handling and validation
 * - Support for multisite scopes
 *
 * Cache Process:
 * 1. Processes products in batches using cursor pagination
 * 2. Generates product URLs for each scope
 * 3. Stores results in cache with scope-based keys
 * 4. Monitors memory usage and adjusts batch size
 * 5. Provides real-time progress updates
 *
 * @package Seiger\sCommerce\Integration
 * @author Seiger IT Team
 * @since 1.0.0
 */
class ProductsListingCache extends BaseIntegration
{
    /**
     * Get the unique name of the BaseIntegration.
     *
     * The name is used as an identifier for this integration throughout the system.
     *
     * @return string The unique name of the integration.
     */
    public function getKey(): string
    {
        return 'splc';
    }

    /**
     * Get the admin display icon for the ProductsListingCache integration.
     *
     * Retrieves the icon for the integration to be displayed in the admin panel.
     *
     * @return string The formatted icon for admin display.
     */
    public function getIcon(): string
    {
        return '<i class="fas fa-memory"></i>';
    }

    /**
     * Get the admin display title for the ProductsListingCache integration.
     *
     * Retrieves the title for the integration to be displayed in the admin panel.
     *
     * @return string The formatted title for admin display.
     */
    public function getTitle(): string
    {
        return __('sCommerce::global.cache_products_listing');
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
        return view('sCommerce::partials.widgetProductsListingCache', [
            'instance' => $this,
            'key'      => $this->getKey(),
            'title'    => $this->getTitle(),
            'icon'     => $this->getIcon(),
            'class'    => static::class,
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
     * Specifies the configurable fields for the "ProductsListingCache" integration.
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
     * Run 'cache' action using optimized cursor pagination with memory control.
     *
     * Processes products in batches using composite cursor pagination (product_id, category, scope)
     * to handle large datasets (130k+ records) efficiently. The method achieves optimal performance
     * by combining several optimization techniques:
     * 
     * Performance Features:
     * - Cursor pagination prevents memory exhaustion on large datasets
     * - Automatic memory usage control with dynamic batch size adjustment
     * - Garbage collection between batches to free memory
     * - Atomic file operations using temporary files and rename()
     * - Direct PHP file generation for fastest cache access
     * - Real-time progress tracking with ETA calculation
     * - Support for multisite scopes and domain-specific caching
     * 
     * Cache Strategy:
     * - Creates separate cache files for each scope (domain)
     * - Uses atomic file replacement to prevent cache corruption
     * - Generates optimized PHP arrays for maximum performance
     * - Provides fallback error handling for file operations
     *
     * @param sIntegrationTask $task  Task model for progress tracking and status updates
     * @param array            $opt   Action options:
     *                                - batch_size: Number of records per batch (default: 5000)
     *                                - max_memory_mb: Memory limit per batch in MB (default: 128)
     * @return void
     * @throws \RuntimeException If no products found to cache or file operation fails
     * @throws \Throwable For any other errors during processing
     * 
     * @example
     * ```php
     * // Basic usage with default settings
     * $cache = new ProductsListingCache();
     * $cache->taskMake($task);
     * 
     * // Custom batch size and memory limit
     * $cache->taskMake($task, [
     *     'batch_size' => 3000,
     *     'max_memory_mb' => 256
     * ]);
     * ```
     */
    public function taskMake(sIntegrationTask $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');

        try {
            // Preparing
            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_PREPARING,
                'message' => __('sCommerce::global.task_running') . '...',
            ]);
            $this->pushProgress($task);

            // Get total count for progress tracking
            $total = sProduct::active()
                ->join('s_product_category', 's_products.id', '=', 's_product_category.product')
                ->where('s_product_category.scope', 'LIKE', 'primary%')
                ->count();

            if (!$total) {
                throw new \RuntimeException('No products found to cache.');
            }

            // Initialize cursor pagination variables
            $productsListing = [];
            $lastProductId = 0;
            $lastCategory = 0;
            $lastScope = '';
            $batchSize = $opt['batch_size'] ?? 5000;
            $maxMemoryMB = $opt['max_memory_mb'] ?? 512;
            $totalProcessed = 0;
            $startTime = microtime(true);

            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_RUNNING,
                'message' => __('sCommerce::global.cache_products_listing') . '...',
            ]);
            $this->pushProgress($task, [
                'progress'  => 0,
                'processed' => 0,
                'total'     => $total,
            ]);

            // Progress tracking variables
            $lastPct = -1;
            $lastBeat = microtime(true);
            $eta = '—';

            do {
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                $batchStartTime = microtime(true);
                $batchStartMemory = memory_get_usage();
                
                $products = sProduct::active()
                    ->join('s_product_category', 's_products.id', '=', 's_product_category.product')
                    ->where('s_product_category.scope', 'LIKE', 'primary%')
                    ->where(function($query) use ($lastProductId, $lastCategory, $lastScope) {
                        $query->where('s_products.id', '>', $lastProductId)
                            ->orWhere(function($q) use ($lastProductId, $lastCategory, $lastScope) {
                                $q->where('s_products.id', '=', $lastProductId)
                                ->where('s_product_category.category', '>', $lastCategory);
                            })
                            ->orWhere(function($q) use ($lastProductId, $lastCategory, $lastScope) {
                                $q->where('s_products.id', '=', $lastProductId)
                                  ->where('s_product_category.category', '=', $lastCategory)
                                  ->where('s_product_category.scope', '>', $lastScope);
                            });
                    })
                    ->select(
                        's_products.id',
                        's_products.alias',
                        's_product_category.scope',
                        's_product_category.category as catId'
                    )
                    ->orderBy('s_products.id')
                    ->orderBy('s_product_category.category')
                    ->orderBy('s_product_category.scope')
                    ->limit($batchSize)
                    ->get();

                foreach ($products as $product) {
                    $scope = trim(str_replace('primary', '', $product->scope), '_');
                    $link = str_replace([EVO_SITE_URL, EVO_CORE_PATH], '|', $product->getLinkAttribute(intval($product->catId)));
                    $link = explode('|', $link);
                    $productsListing[$scope][end($link)] = $product->id;
                }
                
                $totalProcessed += $products->count();
                $lastProductId = $products->last()?->id ?? 0;
                $lastCategory = $products->last()?->category ?? 0;
                $lastScope = $products->last()?->scope ?? '';
                
                $batchTime = microtime(true) - $batchStartTime;
                $batchMemory = (memory_get_usage() - $batchStartMemory) / 1024 / 1024;
                $peakMemory = memory_get_peak_usage() / 1024 / 1024;

                if ($batchMemory > $maxMemoryMB) {
                    $newBatchSize = max(100, intval($batchSize * 0.8));
                    $batchSize = $newBatchSize;
                }

                // Progress calculation and ETA
                $pct = (int)floor($totalProcessed * 100 / $total);
                
                if ($totalProcessed > 0 && $pct > 0) {
                    $elapsed = microtime(true) - $startTime;
                    $rate = $totalProcessed / $elapsed; // items per second
                    $remaining = $total - $totalProcessed;
                    $etaSeconds = $remaining / $rate;
                    
                    if ($etaSeconds > 0 && $etaSeconds < 86400) { // less than 24 hours
                        $eta = $this->formatEta($etaSeconds);
                    } else {
                        $eta = '—';
                    }
                }

                // Emit progress on each new percent
                if ($pct !== $lastPct) {
                    $lastPct = $pct;
                    $this->pushProgress($task, [
                        'processed' => $totalProcessed,
                        'total'     => $total,
                        'progress'  => min($pct, 98),
                        'eta'       => $eta,
                    ]);
                    $lastBeat = microtime(true);
                } elseif ((microtime(true) - $lastBeat) >= 0.5) {
                    $this->pushProgress($task, [
                        'processed' => $totalProcessed,
                        'total'     => $total,
                        'progress'  => min($pct, 98),
                        'eta'       => $eta,
                    ]);
                    $lastBeat = microtime(true);
                }
            } while ($products->count() === $batchSize);

            // Saving to cache
            $task->update([
                'status'  => sIntegrationTask::TASK_STATUS_SAVING,
                'message' => __('sCommerce::global.preparing_file') . '...',
            ]);
            $this->pushProgress($task, ['progress' => 99]);

            foreach ($productsListing as $scope => $products) {
                $scopeSuffix = trim($scope) ? '.' . $scope : '';
                $tmpFile = evo()->getCachePath() . 'sCommerceProductsListing' . $scopeSuffix . '.tmp';
                $phpFile = evo()->getCachePath() . 'sCommerceProductsListing' . $scopeSuffix . '.php';
                
                // Creating a temporary file
                $handle = fopen($tmpFile, 'w');
                if ($handle === false) {
                    throw new \RuntimeException("Cannot create temporary file: {$tmpFile}");
                }
                
                fwrite($handle, "<?php return [\r\n");
                foreach ($products ?? [] as $link => $id) {
                    fwrite($handle, "\t'{$link}' => {$id},\r\n");
                }
                fwrite($handle, "];");
                fclose($handle);

                if (!rename($tmpFile, $phpFile)) {
                    throw new \RuntimeException("Cannot rename temporary file to cache file: {$tmpFile} -> {$phpFile}");
                }
            }
            
            $totalTime = microtime(true) - $startTime;
            $totalMemory = memory_get_usage() / 1024 / 1024;
            $finalPeakMemory = memory_get_peak_usage() / 1024 / 1024;

            // Done
            $this->pushProgress($task, ['progress' => 100]);
            $this->markFinished(
                $task,
                null,
                '**' . __('sCommerce::global.done') . '. ' . 
                __('sCommerce::global.cache_products_listing') . ': ' . 
                number_format($totalProcessed, 0, '.', ' ') . ' ' . __('sCommerce::global.products') . 
                ' (' . round($totalTime, 2) . 's)**'
            );
        } catch (\Throwable $e) {
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $message = 'Failed @ ' . $where . ' — ' . $e->getMessage();

            $this->markFailed($task, $message);
            throw $e;
        }
    }
}
