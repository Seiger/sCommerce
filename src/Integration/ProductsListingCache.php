<?php namespace Seiger\sCommerce\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Seiger\sCommerce\Controllers\sCommerceController;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sTask\Workers\BaseWorker;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sCommerce\Integration\IntegrationActionController;
use Seiger\sCommerce\Models\sAttribute;
use Seiger\sCommerce\Models\sAttributeValue;
use Seiger\sCommerce\Models\sIntegration;
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
class ProductsListingCache extends BaseWorker
{
    /**
     * Get the unique identifier for this worker.
     *
     * @return string The worker identifier
     */
    public function identifier(): string
    {
        return 's_products_listing_cache';
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
     * Get the icon for this worker.
     *
     * @return string The worker icon
     */
    public function icon(): string
    {
        return '<i class="fas fa-memory"></i>';
    }

    /**
     * Get the title for this worker.
     *
     * @return string The worker title
     */
    public function title(): string
    {
        return __('sCommerce::global.cache_products_listing');
    }

    /**
     * Get the description for this worker.
     *
     * @return string The worker description
     */
    public function description(): string
    {
        return __('sCommerce::global.cache_products_listing_desc');
    }

    /**
     * Render the worker widget for the administrative interface.
     *
     * This method renders a custom widget for the ProductsListingCache worker
     * that includes integration-specific progress tracking and controls.
     *
     * @return string HTML content for the worker widget
     */
    public function renderWidget(): string
    {
        return '';
    }

    /**
     * Execute the cache action.
     *
     * Processes products in batches using composite cursor pagination (product_id, category, scope)
     * to handle large datasets (130k+ records) efficiently.
     *
     * @param sTaskModel $task The task model for progress tracking
     * @param array $opt Action parameters:
     *                   - batch_size: Number of records per batch (default: 5000)
     *                   - max_memory_mb: Memory limit per batch in MB (default: 512)
     * @return void
     * @throws \RuntimeException If no products found to cache or file operation fails
     * @throws \Throwable For any other errors during processing
     */
    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        @ini_set('auto_detect_line_endings', '1');
        @ini_set('output_buffering', '0');

        try {
            // Preparing
            $task->update([
                'status' => sTaskModel::TASK_STATUS_PREPARING,
                'message' => __('sCommerce::global.task_running') . '...',
            ]);

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
                'status' => sTaskModel::TASK_STATUS_RUNNING,
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
                    $link = end($link);
                    $productsListing[$scope][ltrim($link, '.')] = $product->id;
                }

                $totalProcessed += $products->count();
                $lastProductId = $products->last()?->id ?? 0;
                $lastCategory = $products->last()?->category ?? 0;
                $lastScope = $products->last()?->scope ?? '';

                $batchMemory = (memory_get_usage() - $batchStartMemory) / 1024 / 1024;

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
                        $eta = niceEta((float)$etaSeconds);
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
                'status' => sTaskModel::TASK_STATUS_RUNNING,
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

            // Done
            $task->update([
                'status' => sTaskModel::TASK_STATUS_FINISHED,
                'progress' => 100,
                'message' => '**' . __('sCommerce::global.done') . '. ' .
                    __('sCommerce::global.cache_products_listing') . ': ' .
                    number_format($totalProcessed, 0, '.', ' ') . ' ' . __('sCommerce::global.products') .
                    ' (' . round($totalTime, 2) . 's)**',
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => 'finished',
                'progress' => 100,
                'message' => '**' . __('sCommerce::global.done') . '. ' .
                    __('sCommerce::global.cache_products_listing') . ': ' .
                    number_format($totalProcessed, 0, '.', ' ') . ' ' . __('sCommerce::global.products') .
                    ' (' . round($totalTime, 2) . 's)**',
            ]);
        } catch (\Throwable $e) {
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $message = 'Failed @ ' . $where . ' — ' . $e->getMessage();

            $task->update([
                'status' => sTaskModel::TASK_STATUS_FAILED,
                'message' => $message,
                'finished_at' => now(),
            ]);

            $this->pushProgress($task, [
                'status' => 'failed',
                'message' => $message,
            ]);

            Log::error('ProductsListingCache failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
