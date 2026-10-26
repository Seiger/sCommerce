<?php namespace Seiger\sCommerce\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderShipment;
use Seiger\sCommerce\Services\OrderPaymentAction;
use Seiger\sCommerce\Services\OrderShipments;
use Seiger\sCommerce\Shipping\ShipmentConflictException;
use Seiger\sCommerce\Shipping\ShipmentData;

/**
 * Manager endpoint of the "Add tracking" form: creates a whole-order shipment, or updates the
 * selected shipment or the active shipment of the order with the same tracking number.
 *
 * @since 1.5.0
 */
final class OrderShipmentController
{
    /** Form nonce action of the tracking form. */
    public const NONCE_ACTION = 'shipment';

    public function __construct(private readonly OrderShipments $shipments = new OrderShipments())
    {
    }

    public function handle(Request $request): JsonResponse
    {
        if ($request->getRealMethod() !== 'POST' || !$request->isMethod('POST')) {
            return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.bulk_status_post_only')], 405, ['Allow' => 'POST']);
        }
        $managerId = (int)evo()->getLoginUserID('mgr');
        if (empty($_SESSION['mgrValidated']) || $managerId < 1 || !evo()->hasPermission('exec_module')) {
            return $this->fail('payment_action_forbidden', 403);
        }
        $token = $request->input('_token', $request->header('X-CSRF-TOKEN'));
        $sessionToken = $_SESSION['_token'] ?? null;
        if (!is_string($token) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return $this->fail('bulk_status_session_expired', 403);
        }

        $integer = static fn ($value, int $min) => (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]) : false;
        $orderId = $integer($request->input('order'), 1);
        $shipmentId = $integer($request->input('shipment', '0'), 0);
        $nonce = $request->input('nonce');
        $text = static fn (string $key) => is_string($value = $request->input($key, '')) ? trim($value) : null;
        $status = $text('status');
        $fields = ['carrier' => $text('carrier'), 'service' => $text('service'), 'tracking_number' => $text('tracking_number'), 'tracking_url' => $text('tracking_url')];
        if ($orderId === false || $shipmentId === false || !is_string($nonce) || !in_array($status, array_keys(sOrderShipment::listStatuses()), true)
            || in_array(null, $fields, true)) {
            return $this->fail('shipment_invalid', 422);
        }
        if (!OrderPaymentAction::nonceMatches($nonce, $orderId, 0, self::NONCE_ACTION)) {
            return $this->fail('bulk_status_session_expired', 403);
        }
        $order = sOrder::query()->find($orderId);
        $shipment = null;
        if ($order && $shipmentId > 0) {
            $shipment = sOrderShipment::query()->where('order_id', $order->id)->find($shipmentId);
        } elseif ($order && $fields['tracking_number'] !== '') {
            $shipment = sOrderShipment::query()->where('order_id', $order->id)->where('tracking_number', $fields['tracking_number'])
                ->whereIn('status', sOrderShipment::ACTIVE_STATUSES)->orderByDesc('id')->first();
        }
        if (!$order || ($shipmentId > 0 && !$shipment)) {
            return $this->fail('shipment_invalid', 422);
        }

        // Empty inputs keep the stored values of an existing shipment.
        $data = new ShipmentData(
            status: $status,
            carrier: $fields['carrier'] === '' ? null : $fields['carrier'],
            service: $fields['service'] === '' ? null : $fields['service'],
            trackingNumber: $fields['tracking_number'] === '' ? null : $fields['tracking_number'],
            trackingUrl: $fields['tracking_url'] === '' ? null : $fields['tracking_url'],
            source: 'manual',
            metadata: ['manager_id' => $managerId],
        );
        try {
            EventScope::run(OrderShipments::SOURCE_MANAGER, $managerId, fn () => $shipment
                ? $this->shipments->update($shipment, $data)
                : $this->shipments->upsert($order, $data));
        } catch (\InvalidArgumentException) {
            return $this->fail('shipment_invalid', 422);
        } catch (ShipmentConflictException) {
            return $this->fail('shipment_conflict', 409);
        } catch (\Throwable $exception) {
            Log::channel('scommerce')->error('sCommerce shipment save failed', ['order_id' => $orderId, 'exception' => $exception]);
            return $this->fail('bulk_status_error', 500);
        }

        return new JsonResponse(['success' => true, 'message' => __('sCommerce::global.shipment_saved')]);
    }

    private function fail(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => __('sCommerce::global.' . $key)], $status);
    }
}
