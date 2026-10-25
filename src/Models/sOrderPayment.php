<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Seiger\sCommerce\Events\DomainEventRecorder;
use Seiger\sCommerce\Events\PaymentRecorded;
use Seiger\sCommerce\Events\PaymentStatusChanged;

/**
 * Class sOrderPayment
 *
 * Represents a single payment record in the order payments ledger.
 * Write rows through {@see \Seiger\sCommerce\Payment\PaymentFlow}.
 */
class sOrderPayment extends Model
{
    /**
     * @var string
     */
    protected $table = 's_order_payments';

    protected $casts = [
        'metadata' => 'array',
        'captured_at' => 'datetime',
    ];

    /**
     * @var array<int,string>
     */
    protected $fillable = [
        'order_id',
        'sequence',
        'kind',
        'status',
        'amount',
        'currency',
        'provider',
        'provider_ref',
        'metadata',
        'captured_at',
    ];

    /**
     * Record ledger events after commit for every writer.
     *
     * @since 1.5.0
     */
    protected static function booted(): void
    {
        static::created(function (sOrderPayment $payment) {
            DomainEventRecorder::instance()->record(PaymentRecorded::from($payment), $payment->getConnection());
        });
        static::updated(function (sOrderPayment $payment) {
            if ($payment->wasChanged('status')) {
                DomainEventRecorder::instance()->record(
                    PaymentStatusChanged::from($payment, (string)$payment->getOriginal('status'), (string)$payment->status),
                    $payment->getConnection()
                );
            }
        });
    }

    /**
     * Payment belongs to an order.
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(sOrder::class, 'order_id');
    }
}