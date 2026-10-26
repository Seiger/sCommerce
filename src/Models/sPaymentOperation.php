<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manager payment operation (capture, refund or cancel) and its provider result.
 *
 * Written by {@see \Seiger\sCommerce\Services\OrderPaymentAction} before the provider is
 * called. `in_flight`, `pending` and `unknown` operations reserve their amount.
 *
 * @since 1.5.0
 */
class sPaymentOperation extends Model
{
    public const STATE_IN_FLIGHT = 'in_flight';
    public const STATE_PENDING = 'pending';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED = 'failed';
    public const STATE_UNKNOWN = 'unknown';

    /** States whose provider effect may still happen. */
    public const ACTIVE_STATES = [self::STATE_IN_FLIGHT, self::STATE_PENDING, self::STATE_UNKNOWN];

    protected $table = 's_payment_operations';

    protected $casts = [
        'context' => 'array',
        'result' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(sOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(sOrderPayment::class, 'payment_id');
    }
}
