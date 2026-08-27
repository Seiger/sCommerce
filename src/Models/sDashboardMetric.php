<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents one aggregated sCommerce dashboard snapshot per calendar day.
 *
 * The scheduled DashboardMetrics worker is the only writer. Manager requests
 * read the stored daily view deltas and combine them with the live cumulative
 * product counter, so rendering the dashboard never adds tracking writes.
 * The date column is unique and makes repeated worker runs for the same day
 * idempotent.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $views_total Cumulative product views at snapshot time
 * @property int|null $views_delta Views accumulated since the prior snapshot
 * @property int $products_total Product count at snapshot time
 * @property int $reviews_total Review count at snapshot time
 * @property float|null $rating_average Average review rating at snapshot time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @since 1.3.5
 */
class sDashboardMetric extends Model
{
    protected $table = 's_commerce_dashboard';

    protected $fillable = [
        'date',
        'views_total',
        'views_delta',
        'products_total',
        'reviews_total',
        'rating_average',
    ];

    protected $casts = [
        'date' => 'date',
        'views_total' => 'integer',
        'views_delta' => 'integer',
        'products_total' => 'integer',
        'reviews_total' => 'integer',
        'rating_average' => 'float',
    ];

    /**
     * Build daily view points for the selected dashboard period.
     *
     * Stored rows contain completed daily deltas. The current day is appended
     * from the live cumulative product counter without writing from a web request.
     * Unsupported periods fall back to 30 days. A live current-day point is
     * returned only after an earlier cumulative snapshot exists, because that
     * baseline is required to calculate a truthful delta.
     *
     * @param int $period Number of calendar days displayed by the chart
     * @param int $currentViews Current cumulative product view counter
     * @return array<int, array{date: string, label: string, views: int}>
     * @since 1.3.5
     */
    public static function viewsChartData(int $period, int $currentViews): array
    {
        $period = in_array($period, [7, 30, 90, 365], true) ? $period : 30;
        $today = today();
        $start = $today->copy()->subDays($period - 1);

        $points = static::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereNotNull('views_delta')
            ->orderBy('date')
            ->get(['date', 'views_delta'])
            ->mapWithKeys(static fn (self $metric): array => [
                $metric->date->toDateString() => [
                    'date' => $metric->date->toDateString(),
                    'label' => $metric->date->format('d.m'),
                    'views' => (int) $metric->views_delta,
                ],
            ])
            ->all();

        $previous = static::query()
            ->whereDate('date', '<', $today->toDateString())
            ->orderByDesc('date')
            ->first(['views_total']);

        if ($previous) {
            $points[$today->toDateString()] = [
                'date' => $today->toDateString(),
                'label' => $today->format('d.m'),
                'views' => max(0, $currentViews - (int) $previous->views_total),
            ];
        }

        ksort($points);

        return array_values($points);
    }
}
