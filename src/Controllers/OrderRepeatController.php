<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Services\OrderRepeater;

/**
 * Manager-only POST endpoint for saving an explicitly reviewed repeat draft.
 *
 * @since 1.4.0
 */
final class OrderRepeatController
{
    /**
     * Validate edits, persist once and return the new order URL to the form.
     *
     * Order/payment statuses and operational metadata are never accepted from POST.
     *
     * @param Request $request Manager form with current CSRF and draft token.
     * @return JsonResponse Success redirect or a non-mutating validation/error response.
     * @since 1.4.0
     */
    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return $this->error('bulk_status_post_only', 405);
        }
        $managerId = (int) evo()->getLoginUserID('mgr');
        if (empty($_SESSION['mgrValidated']) || $managerId < 1 || !evo()->hasPermission('exec_module')) {
            return $this->error('bulk_status_forbidden', 403);
        }
        $token = $request->input('_token');
        if (!is_string($token) || !is_string($_SESSION['_token'] ?? null)
            || $_SESSION['_token'] === '' || !hash_equals($_SESSION['_token'], $token)) {
            return $this->error('bulk_status_session_expired', 403);
        }
        $sourceId = $request->input('repeat_from');
        $draftToken = $request->input('repeat_token');
        if (!(is_int($sourceId) || is_string($sourceId))
            || !($sourceId = filter_var($sourceId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))
            || !is_string($draftToken) || !preg_match('/\A[a-f0-9]{64}\z/', $draftToken)) {
            return $this->error('repeat_invalid', 422);
        }
        try {
            $source = sOrder::query()->findOrFail($sourceId);
            $json = $request->input('products_data');
            if (!is_string($json) || strlen($json) > 2000000) {
                return $this->error('repeat_invalid', 422);
            }
            $products = json_decode($json, true);
            if (!is_array($products) || !array_is_list($products) || !$products || count($products) > 500) {
                return $this->error('repeat_invalid', 422);
            }
            $cost = 0.0;
            foreach ($products as &$product) {
                if (!is_array($product)) return $this->error('repeat_invalid', 422);
                $quantity = $product['quantity'] ?? null;
                if (!(is_int($quantity) || is_string($quantity))
                    || !($quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]))) {
                    return $this->error('repeat_invalid', 422);
                }
                foreach (['title', 'sku', 'link', 'coverSrc'] as $key) {
                    if (!is_string($product[$key] ?? null) || strlen($product[$key]) > 10000) {
                        return $this->error('repeat_invalid', 422);
                    }
                }
                foreach (['link', 'coverSrc'] as $key) {
                    $scheme = parse_url($product[$key], PHP_URL_SCHEME);
                    if ($scheme === false || ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
                        || preg_match('/[\\x00-\\x1f]/', $product[$key])) {
                        return $this->error('repeat_invalid', 422);
                    }
                }
                $rawPrice = $product['priceNumber'] ?? $product['price'] ?? null;
                if (!is_scalar($rawPrice) || is_bool($rawPrice) || !preg_match('/[0-9]/', (string) $rawPrice)) {
                    return $this->error('repeat_invalid', 422);
                }
                // The draft and product picker provide canonical numeric prices.
                if (!is_numeric($rawPrice)) return $this->error('repeat_invalid', 422);
                $price = (float) $rawPrice;
                if (!is_finite($price) || $price < 0 || $price > 9999999) return $this->error('repeat_invalid', 422);
                $product['quantity'] = $quantity;
                $product['price'] = $product['priceNumber'] = $price;
                $cost += $price * $quantity;
            }
            unset($product);
            if (!is_finite($cost) || $cost > 9999999) return $this->error('repeat_invalid', 422);
            $user = $request->input('user_info', []);
            $note = $request->input('note', '');
            if (!is_array($user) || !is_string($note) || strlen($note) > 10000) return $this->error('repeat_invalid', 422);
            $user = array_intersect_key($user, array_flip(['first_name', 'middle_name', 'last_name', 'email', 'phone']));
            foreach ($user as &$value) {
                if (!is_string($value) || mb_strlen($value) > 255) return $this->error('repeat_invalid', 422);
                $value = trim($value);
            }
            unset($value);
            if (!empty($user['email']) && !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) return $this->error('repeat_invalid', 422);
            // Keep retries stable even when the valid session CSRF token rotates.
            $identifier = 'repeat_' . hash('sha256', $managerId . ':' . $sourceId . ':' . $draftToken);
            $result = (new OrderRepeater())->save($sourceId, $identifier, [
                'products' => $products, 'user_info' => $user, 'cost' => round($cost, 2), 'note' => $note,
            ], $managerId);
        } catch (ModelNotFoundException|\OutOfBoundsException) {
            return $this->error('order_not_found', 404);
        } catch (\Throwable $exception) {
            Log::error('sCommerce repeat failed', ['source_id' => $sourceId, 'exception' => $exception]);
            return $this->error('repeat_error', 500);
        }
        $warning = false;
        if ($result['created']) {
            try {
                evo()->invokeEvent('sCommerceAfterOrderSave', ['item' => $result['item']]);
            } catch (\Throwable) {
                $warning = true;
            }
        }
        return new JsonResponse(['success' => true, 'created' => $result['created'],
            'message' => $warning ? __('sCommerce::global.bulk_menu_warning') : '',
            'url' => sCommerce::moduleUrl() . '&get=order&i=' . $result['item']->id]);
    }

    /**
     * Return localized feedback without exposing internal errors or customer data.
     *
     * @param string $key Package translation key.
     * @param int $status HTTP response status.
     * @return JsonResponse
     * @since 1.4.0
     */
    private function error(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['message' => __('sCommerce::global.' . $key)], $status);
    }
}
