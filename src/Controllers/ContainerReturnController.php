<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Payment\PaymentActionException;
use Seiger\sCommerce\Services\ContainerDeposit;
use Seiger\sCommerce\Services\OrderPaymentAction;

/**
 * Manager POST endpoint for a container return without an order: the customer brings back
 * containers and gets the deposit in cash or as a credit toward an order.
 *
 * @since 1.5.0
 */
final class ContainerReturnController
{
    /** Form nonce action of the standalone return form. */
    public const NONCE_ACTION = 'container_return';

    /**
     * Validate and record a standalone container return.
     *
     * @return array{success: bool, message: string} `message` is a package translation key.
     * @since 1.5.0
     */
    public function save(Request $request): array
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return ['success' => false, 'message' => 'bulk_status_post_only'];
        }
        if (!ContainerDeposit::enabled()) {
            return ['success' => false, 'message' => 'container_return_invalid'];
        }
        $managerId = (int)evo()->getLoginUserID('mgr');
        if (empty($_SESSION['mgrValidated']) || $managerId < 1 || !OrderPaymentAction::permitted(self::NONCE_ACTION)) {
            return ['success' => false, 'message' => 'payment_action_forbidden'];
        }
        $token = $request->input('_token');
        $nonce = $request->input('nonce');
        if (!is_string($token) || !is_string($_SESSION['_token'] ?? null) || $_SESSION['_token'] === '' || !hash_equals($_SESSION['_token'], $token)
            || !is_string($nonce) || !OrderPaymentAction::nonceMatches($nonce, 0, 0, self::NONCE_ACTION)) {
            return ['success' => false, 'message' => 'bulk_status_session_expired'];
        }

        $integer = static fn ($value, int $min) => (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]) : false;
        $quantity = $integer($request->input('quantity'), 1);
        $productId = $request->filled('product') ? $integer($request->input('product'), 1) : null;
        $userId = $request->filled('user_id') ? $integer($request->input('user_id'), 1) : 0;
        $settlement = $request->input('settlement', 'cash');
        $creditId = $settlement === 'credit' ? $integer($request->input('credit_order'), 1) : null;
        $unit = $request->input('unit');
        $unit = is_string($unit) && trim($unit) !== '' ? trim($unit) : null;
        $currency = strtoupper(trim((string)$request->input('currency', '')));
        $customer = $request->input('customer', []);
        if ($quantity === false || $productId === false || $userId === false || $creditId === false || !in_array($settlement, ['cash', 'credit'], true)
            || ($unit !== null && !preg_match('/\A\d{1,10}(?:[.,]\d{1,2})?\z/', $unit)) || !is_array($customer)
            || ($currency !== '' && !in_array($currency, (array)sCommerce::config('basic.available_currencies', []), true))) {
            return ['success' => false, 'message' => 'container_return_invalid'];
        }
        $product = $productId ? sCommerce::getProduct($productId) : null;
        $credit = $creditId ? sOrder::query()->find($creditId) : null;
        if (($productId && (!$product || !(int)$product->id)) || ($creditId && !$credit)) {
            return ['success' => false, 'message' => 'container_return_invalid'];
        }

        try {
            EventScope::run(ContainerDeposit::SOURCE, $managerId, fn () => (new ContainerDeposit())->returnContainers([
                'reference' => 'standalone/' . substr(hash('sha256', $nonce), 0, 24), 'quantity' => $quantity, 'settlement' => $settlement,
                'product' => $product, 'unit' => $unit === null ? null : (float)str_replace(',', '.', $unit), 'currency' => $currency,
                'title' => (string)$request->input('title', ''), 'creditOrder' => $credit, 'userId' => (int)$userId,
                'customer' => array_intersect_key($customer, array_flip(['name', 'phone', 'email'])), 'note' => (string)$request->input('note', ''),
            ], $managerId));
        } catch (PaymentActionException $exception) {
            return ['success' => false, 'message' => $exception->messageKey];
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce standalone container return failed', ['exception' => $exception]);
            return ['success' => false, 'message' => 'bulk_status_error'];
        }

        return ['success' => true, 'message' => 'container_return_success'];
    }
}
