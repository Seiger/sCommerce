<?php namespace Seiger\sCommerce\Services;

use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sOrder;

/** One row per order; native CSV, no generated files in public storage. */
final class OrderCsvExporter
{
    public function render(iterable $orders, array $deliveryMethods = [], ?array $domains = null): string
    {
        $stream = fopen('php://memory', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open order CSV stream');
        }
        try {
            $currencies = sCommerce::config('currencies', []);
            fwrite($stream, "\xEF\xBB\xBF");
            $keys = ['order_number', 'client', 'phone', 'email', 'created', 'sum', 'status', 'payment', 'delivery', 'export_tracking'];
            if ($domains !== null) {
                $keys[] = 'domains';
            }
            $this->write($stream, array_map(static fn ($key) => __('sCommerce::global.' . $key), $keys));
            foreach ($orders as $order) {
                $user = is_array($order->user_info) ? $order->user_info : [];
                $info = is_array($order->delivery_info) ? $order->delivery_info : [];
                $method = $this->text($info['method'] ?? '');
                $details = is_array($info[$method] ?? null) ? $info[$method] : [];
                $delivery = '';
                foreach ([$deliveryMethods[$method]['title'] ?? null, $info['title'] ?? null, $info['name'] ?? null, $details['title'] ?? null, $details['name'] ?? null, $method] as $candidate) {
                    if (trim($this->text($candidate)) !== '') {
                        $delivery = $this->text($candidate);
                        break;
                    }
                }
                $tracking = '';
                foreach (['ttn', 'tracking_number', 'trackingNumber', 'waybill', 'declaration'] as $key) {
                    $candidate = $details[$key] ?? $info[$key] ?? null;
                    if (trim($this->text($candidate)) !== '') {
                        $tracking = $this->text($candidate);
                        break;
                    }
                }
                $client = trim(implode(' ', array_map(fn ($key) => $this->text($user[$key] ?? ''), ['first_name', 'middle_name', 'last_name'])));
                $row = [
                    $order->order_number ?? $order->id, html_entity_decode($client, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $this->formatPhone($user['phone'] ?? ''), $user['email'] ?? '', $order->created_at?->format('Y-m-d H:i:s') ?? '',
                    // Match the order list, including the currency marker (bare decimals may be inferred as dates).
                    sCommerce::convertPrice($order->cost, $order->currency)
                        . (($currencies[$order->currency]['show'] ?? 0) == 0 ? ' ' . $order->currency : ''),
                    sOrder::getOrderStatusName((int) $order->status), sOrder::getPaymentStatusName((int) $order->payment_status),
                    $delivery, $tracking,
                ];
                if ($domains !== null) {
                    $row[] = $domains[$order->domain] ?? $order->domain;
                }
                $this->write($stream, $row, 2);
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            if ($csv === false) {
                throw new \RuntimeException('Cannot read order CSV stream');
            }
            return $csv;
        } finally {
            fclose($stream);
        }
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function formatPhone(mixed $value): string
    {
        $phone = $this->text($value);
        // Match the list's Ukrainian format, without turning arbitrary input into a trusted phone.
        if (!preg_match('/\A\+?[0-9][0-9 ()-]*\z/', $phone)) {
            return $phone;
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return sprintf('+38 (%s) %s-%s-%s', substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 2), substr($digits, 10, 2));
        }
        return $phone;
    }

    private function write($stream, array $row, ?int $phoneColumn = null): void
    {
        foreach ($row as $column => $value) {
            $text = $this->text($value);
            // Only plain phone numbers may bypass the formula guard, never arbitrary +prefixed input.
            $plainPhone = $column === $phoneColumn
                && preg_match('/\A\+?[0-9][0-9 ()-]*\z/', $text)
                && preg_match('/\A[0-9]{7,15}\z/', preg_replace('/[^0-9]/', '', $text));
            // CSV quoting alone does not prevent spreadsheet formula evaluation.
            if (!$plainPhone && (preg_match('/^[\x00-\x20]*[=+@-]/', $text) || preg_match('/^[\t\r\n]/', $text))) {
                $text = "'" . $text;
            }
            $row[$column] = $text;
        }
        // sCommerce uses semicolon + UTF-8 BOM. Disable PHP's proprietary escape so quotes round-trip correctly.
        if (fputcsv($stream, $row, ';', '"', '', "\r\n") === false) {
            throw new \RuntimeException('Cannot write order CSV row');
        }
    }
}
