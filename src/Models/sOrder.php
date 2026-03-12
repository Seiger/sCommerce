<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Seiger\sCommerce\Services\OrderReferenceFormatter;

/**
 * Class sOrder
 * This class represents an order in the system.
 *
 * @package Seiger\sCommerce
 *
 * @method Builder|sOrder search()
 */
class sOrder extends Model
{
    use SoftDeletes;
    /**
     * Order status constants.
     */
    const ORDER_STATUS_DELETED = 0; // Замовлення видалено
    const ORDER_STATUS_NEW = 1; // Нове замовлення
    const ORDER_STATUS_PROCESSING = 2; // У процесі обробки
    const ORDER_STATUS_CONFIRMED = 3; // Підтверджено
    const ORDER_STATUS_PACKING = 4; // Упаковується
    const ORDER_STATUS_READY_FOR_SHIPMENT = 5; // Готове до відправлення
    const ORDER_STATUS_SHIPPED = 6; // Відправлено
    const ORDER_STATUS_DELIVERED = 7; // Доставлено
    const ORDER_STATUS_COMPLETED = 8; // Виконано (замовлення завершено)
    const ORDER_STATUS_CANCELED = 9; // Скасовано
    const ORDER_STATUS_ON_HOLD = 10; // Заморожено
    const ORDER_STATUS_RETURN_REQUESTED = 11; // Запит на повернення
    const ORDER_STATUS_RETURNED = 12; // Повернено
    const ORDER_STATUS_FAILED = 13; // Невдале замовлення (технічна проблема)

    /**
     * Payment status constants.
     */
    const PAYMENT_STATUS_PENDING = 0; // Очікує оплати
    const PAYMENT_STATUS_AWAITING_CONFIRMATION = 1; // Очікує підтвердження
    const PAYMENT_STATUS_PARTIALLY_PAID = 2; // Частково оплачено
    const PAYMENT_STATUS_PAID = 3; // Оплачено
    const PAYMENT_STATUS_FAILED = 4; // Помилка оплати
    const PAYMENT_STATUS_REFUND_REQUESTED = 5; // Запит на повернення коштів
    const PAYMENT_STATUS_REFUNDED = 6; // Повернено
    const PAYMENT_STATUS_PARTIALLY_REFUNDED = 7; // Частково повернено
    const PAYMENT_STATUS_DISPUTED = 8; // Спір
    const PAYMENT_STATUS_CANCELED = 9; // Оплату скасовано
    const PAYMENT_STATUS_REJECTED = 10; // Оплату відхилено
    const PAYMENT_STATUS_AUTHORIZED = 11; // Авторизовано (очікує списання)
    const PAYMENT_STATUS_PENDING_VERIFICATION = 12; // Очікує верифікації
    const PAYMENT_STATUS_EXPIRED = 13; // Термін дії платежу минув

    /**
     * Cast attributes to specific types.
     *
     * @var array
     */
    protected $casts = [
        'user_info' => 'array',
        'delivery_info' => 'array',
        'payment_info' => 'array',
        'products' => 'array',
        'manager_info' => 'array',
        'manager_notes' => 'array',
        'history' => 'array',
        'do_not_call' => 'boolean',
    ];

    /**
     * Return a list of order statuses with labels.
     *
     * @return array
     */
    public static function listOrderStatuses(): array
    {
        return self::extractStatusConstants('ORDER_STATUS_');
    }

    /**
     * Return a list of payment statuses with labels.
     *
     * @return array
     */
    public static function listPaymentStatuses(): array
    {
        return self::extractStatusConstants('PAYMENT_STATUS_');
    }

    /**
     * Get the readable name of the order status.
     *
     * @param int $status
     * @return string
     */
    public static function getOrderStatusName(int $status): string
    {
        $statuses = self::listOrderStatuses();

        return $statuses[$status] ?? __('Unknown');
    }

    /**
     * Get the readable name of the payment status.
     *
     * @param int $status
     * @return string
     */
    public static function getPaymentStatusName(int $status): string
    {
        $statuses = self::listPaymentStatuses();

        return $statuses[$status] ?? __('Unknown');
    }

    /**
     * Get the formatted order number for display purposes.
     *
     * This accessor returns a human-readable order number derived from the
     * immutable `reference` field using {@see OrderReferenceFormatter}.
     *
     * The returned value is intended for UI, emails, and external integrations.
     * It does NOT represent the internal primary key (`id`) and MUST NOT be used
     * for database lookups or mutations.
     *
     * If the order does not have a reference assigned, the method returns null.
     *
     * @return string|null Formatted order number or null if reference is missing.
     */
    public function getOrderNumberAttribute(): ?string
    {
        return OrderReferenceFormatter::orderNumber($this->reference ?? null);
    }

    /**
     * Apply search filters to the query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder object
     *
     * @return \Illuminate\Database\Eloquent\Builder The modified query builder object
     */
    public function scopeSearch($query)
    {
        $raw = trim((string)request()->input('search', ''));
        if ($raw === '') {
            return $query;
        }

        $raw = strip_tags($raw);
        $raw = preg_replace('/\s{2,}/', ' ', $raw) ?? $raw;

        return $query->where(function ($q) use ($raw) {
            $digits = OrderReferenceFormatter::extractTrailingDigits($raw);

            if (ctype_digit($raw)) {
                $num = (int)$raw;
                $q->orWhere('id', $num);

                $q->orWhere('reference', $raw)->orWhere('reference', 'like', '%' . $raw);
            } elseif ($digits !== null) {
                $q->orWhere('reference', $digits)->orWhere('reference', 'like', '%' . $digits);
            }

            $q->orWhere('reference', 'like', '%' . $raw . '%')
                ->orWhere('identifier', 'like', '%' . $raw . '%')
                ->orWhere('uuid', 'like', '%' . $raw . '%')
                ->orWhere('comment', 'like', '%' . $raw . '%')
                ->orWhere('user_info', 'like', '%' . $raw . '%')
                ->orWhere('delivery_info', 'like', '%' . $raw . '%')
                ->orWhere('payment_info', 'like', '%' . $raw . '%');
        });
    }

    /**
     * Extract constants based on a prefix and return them as a list.
     *
     * @param string $prefix
     * @return array
     */
    private static function extractStatusConstants(string $prefix): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);

        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, $prefix)) {
                $const = strtolower($constant);
                $list[$value] = __('sCommerce::global.' . $const);
            }
        }

        return $list;
    }
}
