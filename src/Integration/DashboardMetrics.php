<?php namespace Seiger\sCommerce\Integration;

use Seiger\sCommerce\Models\sDashboardMetric;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sReview;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

/**
 * Creates daily aggregated metrics consumed by the no-cart dashboard.
 *
 * The migration registers this class as a hidden sTask worker scheduled for
 * 23:55. Each run performs aggregate reads over products and reviews, then
 * upserts exactly one date-unique snapshot. The first run establishes the
 * cumulative view baseline and intentionally leaves views_delta null; later
 * runs calculate non-negative daily deltas without adding writes to frontend
 * product-view requests.
 *
 * Re-running the task on the same date refreshes that day's snapshot instead
 * of creating duplicates. Database failures are allowed to propagate to the
 * sTask execution layer so the task is reported as failed rather than silently
 * publishing incomplete metrics.
 *
 * @since 1.3.5
 */
class DashboardMetrics extends BaseWorker
{
    /**
     * Return the stable identifier used for registration and task dispatch.
     *
     * This value must remain identical to the identifier stored by the package
     * migration; changing it would orphan the scheduled worker configuration.
     *
     * @return string
     * @since 1.3.5
     */
    public function identifier(): string
    {
        return 'sCommerceDashboardMetrics';
    }

    /**
     * Associate the worker with the sCommerce package scope.
     *
     * sTask uses the scope to group package workers and resolve their manager
     * context even though this worker is hidden from the widget list.
     *
     * @return string
     * @since 1.3.5
     */
    public function scope(): string
    {
        return 'sCommerce';
    }

    /**
     * Provide the worker icon for sTask registry and diagnostic surfaces.
     *
     * The migration currently marks the worker as hidden, but the icon remains
     * part of the BaseWorker contract and supports future administrative views.
     *
     * @return string
     * @since 1.3.5
     */
    public function icon(): string
    {
        return '<i class="fas fa-chart-line"></i>';
    }

    /**
     * Resolve the localized worker title from the sCommerce dictionary.
     *
     * sTask uses this value in task status messages and any registry surface
     * where the hidden worker is inspected.
     *
     * @return string
     * @since 1.3.5
     */
    public function title(): string
    {
        return __('sCommerce::global.dashboard_metrics_worker');
    }

    /**
     * Resolve the localized explanation of the snapshot operation.
     *
     * The description communicates that the task performs one daily aggregate
     * capture rather than tracking individual frontend requests.
     *
     * @return string
     * @since 1.3.5
     */
    public function description(): string
    {
        return __('sCommerce::global.dashboard_metrics_worker_desc');
    }

    /**
     * Keep the scheduled worker hidden from the dashboard widget area.
     *
     * Returning an empty string is intentional: the worker has no interactive
     * manager controls and is executed exclusively through the sTask scheduler.
     *
     * @return string
     * @since 1.3.5
     */
    public function renderWidget(): string
    {
        return '';
    }

    /**
     * Resolve the effective schedule consumed by the sTask scheduler.
     *
     * Persisted worker settings take precedence; fresh installations fall back
     * to a periodic daily run at 23:55 with no weekday restriction.
     *
     * @return array<string, mixed>
     * @since 1.3.5
     */
    public function getSchedule(): array
    {
        return $this->getConfig('schedule', [
            'type' => 'periodic',
            'enabled' => true,
            'time' => '23:55',
            'frequency' => 'daily',
            'days' => [],
        ]);
    }

    /**
     * Persist or refresh the current daily dashboard snapshot.
     *
     * The method marks the task as running, aggregates product views and counts,
     * calculates the delta against the latest earlier snapshot, and upserts the
     * current date. It finishes the task only after persistence succeeds. The
     * optional payload is accepted for BaseWorker compatibility and is not used.
     *
     * @param sTaskModel $task Task used for status and progress reporting
     * @param array<string, mixed> $opt Optional task parameters
     * @return void
     * @since 1.3.5
     */
    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        $task->update([
            'status' => sTaskModel::TASK_STATUS_RUNNING,
            'progress' => 10,
            'message' => __('sCommerce::global.dashboard_metrics_worker') . '...',
        ]);

        $date = now()->toDateString();
        $viewsTotal = (int) sProduct::sum('views');
        $previous = sDashboardMetric::query()
            ->whereDate('date', '<', $date)
            ->orderByDesc('date')
            ->first(['views_total']);

        $metric = sDashboardMetric::updateOrCreate(
            ['date' => $date],
            [
                'views_total' => $viewsTotal,
                'views_delta' => $previous ? max(0, $viewsTotal - (int) $previous->views_total) : null,
                'products_total' => sProduct::count(),
                'reviews_total' => sReview::count(),
                'rating_average' => round((float) (sReview::avg('rating') ?? 0), 2),
            ]
        );

        $task->update(['progress' => 100]);
        $this->markFinished(
            $task,
            null,
            __('sCommerce::global.dashboard_metrics_worker_done', [
                'date' => $metric->date->format('d.m.Y'),
            ])
        );
    }
}
