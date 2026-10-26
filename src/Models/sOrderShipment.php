<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Seiger\sCommerce\Events\DomainEventRecorder;
use Seiger\sCommerce\Events\ShipmentUpdated;
use Seiger\sCommerce\Services\OrderShipments;

/**
 * Class sOrderShipment
 *
 * One parcel or delivery of an order with its carrier tracking. Write rows through
 * {@see OrderShipments}, which validates quantities and status transitions.
 *
 * @since 1.5.0
 */
class sOrderShipment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELED = 'canceled';

    /** Normal forward path; a later position never moves back to an earlier one. */
    public const FORWARD_STATUSES = [self::STATUS_PENDING, self::STATUS_READY, self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED];

    /** Statuses that end a shipment. */
    public const FINAL_STATUSES = [self::STATUS_DELIVERED, self::STATUS_FAILED, self::STATUS_RETURNED, self::STATUS_CANCELED];

    /** Statuses whose items are allocated to this shipment. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_READY, self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED];

    /** Statuses of a parcel that left the shop. */
    public const SENT_STATUSES = [self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED];

    /** Columns whose change is reported by `sCommerce.ShipmentUpdated`. */
    public const TRACKED_COLUMNS = ['status', 'carrier', 'service', 'tracking_number', 'tracking_url', 'items', 'shipped_at', 'delivered_at'];

    /**
     * @var string
     */
    protected $table = 's_order_shipments';

    protected $casts = [
        'items' => 'array',
        'metadata' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * @var array<int,string>
     */
    protected $fillable = [
        'order_id',
        'sequence',
        'status',
        'carrier',
        'service',
        'tracking_number',
        'tracking_url',
        'items',
        'source',
        'external_ref',
        'metadata',
        'shipped_at',
        'delivered_at',
    ];

    /**
     * Fill the UUID, record `sCommerce.ShipmentUpdated` after commit and project the order.
     *
     * @since 1.5.0
     */
    protected static function booted(): void
    {
        static::creating(function (sOrderShipment $shipment) {
            if (empty($shipment->uuid)) {
                $shipment->uuid = (string)Str::uuid();
            }
        });
        static::created(function (sOrderShipment $shipment) {
            DomainEventRecorder::instance()->record(ShipmentUpdated::from($shipment, true, null), $shipment->getConnection());
            (new OrderShipments())->project((int)$shipment->order_id);
        });
        static::updated(function (sOrderShipment $shipment) {
            if (!$shipment->wasChanged(self::TRACKED_COLUMNS)) {
                return;
            }
            $oldStatus = (string)$shipment->getOriginal('status');
            DomainEventRecorder::instance()->record(ShipmentUpdated::from($shipment, false, $oldStatus), $shipment->getConnection());
            (new OrderShipments())->project((int)$shipment->order_id);
        });
        static::deleted(function (sOrderShipment $shipment) {
            (new OrderShipments())->project((int)$shipment->order_id);
        });
    }

    /**
     * Shipment belongs to an order.
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(sOrder::class, 'order_id');
    }

    /**
     * List of shipment statuses with labels.
     *
     * @return array<string, string>
     * @since 1.5.0
     */
    public static function listStatuses(): array
    {
        $list = [];
        foreach (array_merge(self::FORWARD_STATUSES, [self::STATUS_FAILED, self::STATUS_RETURNED, self::STATUS_CANCELED]) as $status) {
            $list[$status] = __('sCommerce::global.shipment_status_' . $status);
        }

        return $list;
    }
}
