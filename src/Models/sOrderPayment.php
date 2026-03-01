<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class sOrderPayment
 *
 * Represents a single payment record in the order payments ledger.
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
     * Payment belongs to an order.
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(sOrder::class, 'order_id');
    }
}