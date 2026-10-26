<?php namespace Seiger\sCommerce\Payment;

use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPaymentMethod;

/**
 * Instantiates the payment method an order was placed with, including inactive rows:
 * refunds, settlements and webhooks continue after a method is disabled.
 *
 * @since 1.5.0
 */
final class PaymentMethodResolver
{
    /** @var array<string, PaymentMethodInterface|null> Instances by method key for this request. */
    private static array $instances = [];

    /**
     * Payment method of an order, from `payment_info.method`.
     *
     * @since 1.5.0
     */
    public static function forOrder(sOrder $order): ?PaymentMethodInterface
    {
        $info = is_array($order->payment_info) ? $order->payment_info : [];

        return self::forKey(trim((string)($info['method'] ?? '')));
    }

    /**
     * Payment method by checkout key: method name followed by the row identifier.
     *
     * @since 1.5.0
     */
    public static function forKey(string $key): ?PaymentMethodInterface
    {
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, self::$instances)) {
            return self::$instances[$key];
        }

        $row = sPaymentMethod::query()->orderByDesc('active')->orderBy('position')->orderBy('id')->get()
            ->first(static fn (sPaymentMethod $row) => $row->name . $row->identifier === $key);
        $className = trim((string)($row->class ?? ''));
        $instance = null;
        if ($className !== '' && class_exists($className)) {
            try {
                $candidate = new $className((string)$row->identifier);
                $instance = $candidate instanceof PaymentMethodInterface ? $candidate : null;
            } catch (\Throwable $exception) {
                Log::channel('scommerce')->warning('sCommerce payment method could not be instantiated', ['method' => $key, 'error' => $exception->getMessage()]);
            }
        }

        return self::$instances[$key] = $instance;
    }

    /**
     * Forget instances, e.g. after settings change in a long-running worker.
     *
     * @since 1.5.0
     */
    public static function flush(): void
    {
        self::$instances = [];
    }
}
