<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A returned container: the deposit refunded in cash or credited to an order.
 *
 * Every return is recorded here, including cash refunds without an order, which have no row
 * in the order payments ledger.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $source_order_id
 * @property int|null $product_id
 * @property int $quantity
 * @property string $amount
 * @property string $currency
 * @property string $settlement cash|credit
 * @property int|null $credit_order_id
 * @since 1.5.0
 */
class sContainerReturn extends Model
{
    public const CASH = 'cash';
    public const CREDIT = 'credit';

    protected $table = 's_container_returns';

    protected $guarded = ['id'];

    protected $casts = [
        'customer' => 'array',
        'amount' => 'decimal:2',
        'unit_amount' => 'decimal:2',
        'source_order_id' => 'integer',
        'credit_order_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'user_id' => 'integer',
        'actor_id' => 'integer',
    ];
}
