<?php namespace Seiger\sCommerce\Api\Services;

use Seiger\sCommerce\Api\Contracts\OrderUpdateValidatorInterface;

final class OrderUpdateValidator implements OrderUpdateValidatorInterface
{
    public function validate(array $payload): array
    {
        $errors = [];
        $data = [];

        $knownTopLevel = [
            'status' => true,
            'payment_status' => true,
            'comment' => true,
            'sync' => true,
            'totals' => true,
            'currency' => true,
            'delivery' => true,
            'payment' => true,
            'user' => true,
            'items' => true,
        ];

        foreach ($payload as $key => $_) {
            if (is_string($key) && !isset($knownTopLevel[$key])) {
                $errors[$key] = 'Unknown field.';
            }
        }

        if (array_key_exists('status', $payload)) {
            if (!is_numeric($payload['status'])) {
                $errors['status'] = 'Must be an integer.';
            } else {
                $data['status'] = (int)$payload['status'];
            }
        }

        if (array_key_exists('payment_status', $payload)) {
            if (!is_numeric($payload['payment_status'])) {
                $errors['payment_status'] = 'Must be an integer.';
            } else {
                $data['payment_status'] = (int)$payload['payment_status'];
            }
        }

        if (array_key_exists('comment', $payload)) {
            if ($payload['comment'] === null) {
                $data['comment'] = null;
            } else {
                $comment = (string)$payload['comment'];
                if (mb_strlen($comment) > 2000) {
                    $errors['comment'] = 'Too long.';
                } else {
                    $data['comment'] = $comment;
                }
            }
        }

        if (array_key_exists('currency', $payload)) {
            $currency = strtoupper(trim((string)$payload['currency']));
            if (!preg_match('~^[A-Z]{3}$~', $currency)) {
                $errors['currency'] = 'Must be ISO-4217 (3 letters).';
            } else {
                $data['currency'] = $currency;
            }
        }

        if (isset($payload['sync'])) {
            if (!is_array($payload['sync'])) {
                $errors['sync'] = 'Must be an object.';
            } else {
                if (array_key_exists('exported', $payload['sync'])) {
                    $data['loaded'] = (bool)$payload['sync']['exported'];
                }

                foreach ($payload['sync'] as $key => $_) {
                    if (is_string($key) && $key !== 'exported') {
                        $errors["sync.$key"] = 'Unknown field.';
                    }
                }
            }
        }

        if (isset($payload['totals'])) {
            if (!is_array($payload['totals'])) {
                $errors['totals'] = 'Must be an object.';
            } else {
                if (array_key_exists('cost', $payload['totals'])) {
                    if (!is_numeric($payload['totals']['cost'])) {
                        $errors['totals.cost'] = 'Must be a number.';
                    } else {
                        $cost = (float)$payload['totals']['cost'];
                        if ($cost < 0) {
                            $errors['totals.cost'] = 'Must be >= 0.';
                        } else {
                            $data['cost'] = $cost;
                        }
                    }
                }

                foreach ($payload['totals'] as $key => $_) {
                    if (is_string($key) && $key !== 'cost' && $key !== 'order_total') {
                        $errors["totals.$key"] = 'Unknown field.';
                    }
                }
            }
        }

        foreach (['delivery' => 'delivery', 'payment' => 'payment', 'user' => 'user'] as $payloadKey => $dataKey) {
            if (array_key_exists($payloadKey, $payload)) {
                if (!is_array($payload[$payloadKey])) {
                    $errors[$payloadKey] = 'Must be an object.';
                } else {
                    $data[$dataKey] = $payload[$payloadKey];
                }
            }
        }

        if (array_key_exists('items', $payload)) {
            if (!is_array($payload['items'])) {
                $errors['items'] = 'Must be an array.';
            } else {
                $itemsErrors = [];
                foreach ($payload['items'] as $i => $row) {
                    if (!is_array($row)) {
                        $itemsErrors[(string)$i] = 'Item must be an object.';
                    }
                }
                if ($itemsErrors !== []) {
                    $errors['items'] = $itemsErrors;
                } else {
                    $data['items'] = $payload['items'];
                }
            }
        }

        $hasAnyUpdate = $data !== [];
        if (!$hasAnyUpdate) {
            $errors['_payload'] = 'No updatable fields provided.';
        }

        return [
            'ok' => $errors === [],
            'data' => $errors === [] ? $data : [],
            'errors' => $errors,
        ];
    }
}
