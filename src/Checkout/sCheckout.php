<?php namespace Seiger\sCommerce\Checkout;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Seiger\sCommerce\Events\EventScope;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;
use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPaymentMethod;
use Seiger\sCommerce\Payment\PaymentFlow;
use Seiger\sCommerce\Payment\PaymentSchedule;
use Seiger\sCommerce\Services\ContainerDeposit;
use Seiger\sCommerce\Services\OrderLineIdentity;
use Seiger\sCommerce\Services\OrderTaxes;
use Seiger\sCommerce\Services\VatDisplay;
use Seiger\sCommerce\Services\sPriceResolver;
use View;

/**
 * Class sCheckout
 *
 * This class provides functionality for managing the checkout process,
 * including handling delivery and payment methods, validating user input,
 * and processing orders.
 *
 * @package Seiger\sCommerce\Checkout
 */
class sCheckout
{
    protected array $orderData = [];
    protected array $deliveryMethods = [];
    protected array $paymentMethods = [];
    /** Trusted context of the checkout being processed, see {@see CheckoutContext}. */
    protected array $checkoutContext = [];

    /**
     * sCheckout constructor.
     *
     * Initializes the checkout process, loads available delivery and payment methods,
     * and retrieves the cart data.
     */
    public function __construct()
    {
        $this->orderData = $this->loadOrderData();
        $this->loadDeliveryMethods();
        $this->loadPaymentMethods();
    }

    /**
     * Generate validation rules for the checkout process.
     *
     * This method dynamically generates validation rules for the checkout process
     * based on the provided order data. It includes base validation rules for user,
     * delivery, and payment fields and integrates additional rules from the selected
     * delivery method. When no cart line needs shipping, the delivery rules are dropped.
     * Plugins can then add, replace, or remove rules through
     * the sCommerce.CheckoutValidationRules event. Its payload contains
     * the input data and the rules array passed by reference.
     *
     * @param array $data The input data for the checkout process.
     *                    Example: ['delivery' => ['method' => 'courier', ...], ...].
     * @return array An associative array of validation rules, where the key is the
     *               field name, and the value is the validation rule.
     *
     * @throws \InvalidArgumentException If the selected delivery method is invalid.
     *
     * Example Output:
     * [
     *     'user.name' => 'required|string|max:255',
     *     'user.email' => 'required|email|max:255',
     *     'delivery.method' => 'required|string|in:pickup,courier',
     *     'delivery.address.city' => 'required|string|max:255',
     *     'delivery.address.street' => 'required|string|max:255',
     *     ...
     * ]
     */
    public function getValidationRules(array $data): array
    {
        $deliveryMethods = array_keys($this->deliveryMethods);
        $paymentMethods = array_keys($this->paymentMethods);

        $rules = [
            'user.first_name' => 'required|string|max:255',
            'user.middle_name' => 'nullable|string|max:255',
            'user.last_name' => 'nullable|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'nullable|string|max:20|regex:/^\+?[1-9]\d{7,14}$/',
            'user.vat' => 'sometimes|string|max:32',
            'user.address.country' => 'sometimes|string|max:255',
            'user.address.state' => 'sometimes|string|max:255',
            'user.address.city' => 'sometimes|string|max:255',
            'user.address.street' => 'sometimes|string|max:255',
            'user.address.zip' => 'sometimes|string|max:10',
            'delivery.method' => 'required|string|in:' . implode(',', $deliveryMethods),
            'payment.method' => 'required|string|in:' . implode(',', $paymentMethods),
            'comment' => 'nullable|string|max:1000',
            'do_not_call' => 'nullable|boolean',
            'delivery.courier.recipient_region' => 'sometimes|string|max:255',
            'delivery.courier.recipient_city' => 'sometimes|string|max:255',
            'delivery.courier.recipient_street' => 'sometimes|string|max:255',
            'delivery.courier.recipient_build' => 'sometimes|string|max:10',
            'delivery.courier.recipient_room' => 'sometimes|string|max:10',
            'delivery.courier.recipient_zip' => 'sometimes|string|max:10',
            'user.recipient_person' => 'nullable|string|max:255',
            'user.recipient_phone' => 'nullable|string|max:20|regex:/^\+?[1-9]\d{7,14}$/',
        ];

        if (!$this->requiresDelivery()) {
            unset($rules['delivery.method']);
        } elseif (!empty($data['delivery']['method']) && isset($this->deliveryMethods[$data['delivery']['method']])) {
            $deliveryMethod = $this->deliveryMethods[$data['delivery']['method']];
            $rules = array_merge($rules, $deliveryMethod->getValidationRules());
        }

        Event::dispatch('sCommerce.CheckoutValidationRules', [['data' => $data, 'rules' => &$rules]]);

        return $rules;
    }

    /**
     * Whether the checkout needs a delivery method: deliveries are enabled and at least one
     * cart line is shipped. Lines without the `requires_shipping` key are shipped.
     *
     * @since 1.5.0
     */
    public function requiresDelivery(): bool
    {
        if (sCommerce::config('basic.deliveries_on', 1) != 1) {
            return false;
        }
        $lines = $this->orderData['products'] ?? (sCart::getMiniCart()['items'] ?? []);
        if (!is_array($lines) || $lines === []) {
            return true;
        }
        foreach ($lines as $line) {
            if (!is_array($line) || OrderLineIdentity::requiresShipping($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize the checkout process.
     *
     * Prepares order data for the user, including cart items, delivery, and payment preferences.
     * Redirects if the cart is empty. The result also contains `requiresDelivery`, so templates
     * can hide the delivery step of a services-only cart.
     *
     * @return array The prepared order data.
     */
    public function initCheckout(): array
    {
        $cartData = sCart::getMiniCart();

        if (count($cartData['items']) === 0) {
            return evo()->sendRedirect(back()->getTargetUrl());
        }

        $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr'); // Checking if the user is authorized
        $user = evo()->getUserInfo($userId ?: 0) ?: [];
        $user = array_merge($user, evo()->getUserSettings());

        $userData = [
            'id' => $user['id'] ?? 0,
            'first_name' => $user['first_name'] ?? '',
            'middle_name' => $user['middle_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => $user['phone'] ?? '',
            'address' => [
                'country' => $user['country'] ?? '',
                'state' => $user['state'] ?? '',
                'city' => $user['city'] ?? '',
                'street' => $user['street'] ?? '',
                'zip' => $user['zip'] ?? '',
            ],
        ];

        $this->orderData = [
            'user' => $userData,
            'products' => $cartData['items'],
            'cost' => $cartData['totalSum'],
            'currency' => sCommerce::currentCurrency(),
            'preferences' => [
                'language' => $user['language'] ?? evo()->getLocale(),
                'currency' => $user['currency'] ?? sCommerce::currentCurrency(),
            ],
        ];

        $_SESSION['sCheckout'] = $this->orderData;

        return $this->orderData + ['requiresDelivery' => $this->requiresDelivery()];
    }

    /**
     * Retrieve all available delivery methods.
     *
     * This method returns a list of all registered delivery methods with their details.
     * The returned array includes the name, title, description, rendered widget HTML,
     * and additional settings for each delivery method.
     *
     * @return array An array of delivery methods, where each method includes:
     *               - `name` (string): The unique identifier of the delivery method.
     *               - `title` (string): The localized title of the delivery method.
     *               - `description` (string): The localized description of the delivery method.
     *               - `widget` (string): The rendered HTML widget for the delivery method.
     *               - Additional settings specific to each delivery method.
     */
    public function getDeliveries(): array
    {
        $methods = [];

        if (sCommerce::config('basic.deliveries_on', 1) == 1) {
            $reservedKeys = ['name', 'title', 'description', 'widget'];

            foreach ($this->deliveryMethods as $methodName => $methodInstance) {
                $settings = $methodInstance->getSettings();

                $conflictingKeys = array_intersect(array_keys($settings), $reservedKeys);
                if (!empty($conflictingKeys)) {
                    $className = get_class($methodInstance);
                    $classPath = (new \ReflectionClass($methodInstance))->getFileName();

                    evo()->logEvent(
                        0,
                        2,
                        "Conflict in delivery method '{$methodName}': reserved keys detected (" . implode(', ', $conflictingKeys) . ").<br>" .
                        "Class: <code>{$className}</code><br>File: <code>{$classPath}</code>",
                        'Delivery Method Key Conflict'
                    );
                }

                $filteredSettings = array_diff_key($settings, array_flip($reservedKeys));

                $methods[] = array_merge([
                    'name' => $methodInstance->getName(),
                    'title' => $methodInstance->getTitle(),
                    'description' => $methodInstance->getDescription(),
                    'widget' => $methodInstance->renderWidget($this->orderData),
                ], $filteredSettings);
            }
        }
        return $methods;
    }

    /**
     * Retrieve the details of a specific delivery method by its name.
     *
     * This method fetches the details of a registered delivery method based on its unique name.
     * If the delivery method is not found, an exception is thrown.
     *
     * Example usage:
     * ```php
     * $delivery = $sCheckout->getDelivery('courier');
     * ```
     *
     * @param string $methodName The unique name of the delivery method to retrieve.
     * @return array An associative array containing the delivery method details:
     *               - `name` (string): The unique identifier of the delivery method.
     *               - `title` (string): The localized title of the delivery method.
     *               - `description` (string): The localized description of the delivery method.
     *               - `settings` (array): Configuration settings specific to the delivery method.
     *
     * @throws \InvalidArgumentException If the delivery method is not found.
     */
    public function getDelivery(string $methodName): array
    {
        if (sCommerce::config('basic.deliveries_on', 1) !== 1) {
            return [];
        }

        $methodInstance = $this->deliveryMethods[$methodName] ?? null;

        if (!$methodInstance) {
            throw new \InvalidArgumentException("Delivery method '{$methodName}' not found.");
        }

        $reservedKeys = ['name', 'title', 'description'];
        $settings = $methodInstance->getSettings();
        $filteredSettings = array_diff_key($settings, array_flip($reservedKeys));

        return array_merge([
            'name' => $methodInstance->getName(),
            'title' => $methodInstance->getTitle(),
            'description' => $methodInstance->getDescription(),
        ], $filteredSettings);
    }

    /**
     * Retrieve all available payment methods.
     *
     * This method returns a list of all registered payment methods with their details.
     * The returned array includes the name, title, description, additional details,
     * and settings for each payment method.
     *
     * @return array An array of payment methods, where each method includes:
     *               - `name` (string): The unique identifier of the payment method.
     *               - `title` (string): The localized title of the payment method.
     *               - `description` (string): The localized description of the payment method.
     *               - `settings` (array): Configuration settings specific to the payment method.
     */
    public function getPayments(): array
    {
        $methods = [];

        if (sCommerce::config('basic.payments_on', 1) == 1) {
            $reservedKeys = ['id', 'name', 'title', 'description'];

            foreach ($this->paymentMethods as $methodName => $methodInstance) {
                $settings = $methodInstance->getSettings();

                $conflictingKeys = array_intersect(array_keys($settings), $reservedKeys);
                if (!empty($conflictingKeys)) {
                    $className = get_class($methodInstance);
                    $classPath = (new \ReflectionClass($methodInstance))->getFileName();

                    evo()->logEvent(
                        0,
                        2,
                        "Conflict in payment method '{$methodName}': reserved keys detected (" . implode(', ', $conflictingKeys) . ").<br>" .
                        "Class: <code>{$className}</code><br>File: <code>{$classPath}</code>",
                        'Payment Method Key Conflict'
                    );
                }

                $filteredSettings = array_diff_key($settings, array_flip($reservedKeys));

                $methods[] = array_merge([
                    'id' => $methodInstance->getId(),
                    'key' => $methodInstance->getIdentifier(),
                    'name' => $methodInstance->getName(),
                    'title' => $methodInstance->getTitle(),
                    'description' => $methodInstance->getDescription(),
                ], $filteredSettings);
            }
        }
        return $methods;
    }

    /**
     * Retrieve the details of a specific payment method by its name.
     *
     * This method fetches the details of a registered payment method based on its unique name.
     * If the payment method is not found, an exception is thrown.
     *
     * Example usage:
     * ```php
     * $payment = $sCheckout->getPayment('cash');
     * ```
     *
     * @param string $methodName The unique name of the payment method to retrieve.
     * @return array An associative array containing the payment method details:
     *               - `name` (string): The unique identifier of the payment method.
     *               - `title` (string): The localized title of the payment method.
     *               - `description` (string): The localized description of the payment method.
     *               - `settings` (array): Configuration settings specific to the payment method.
     *
     * @throws \InvalidArgumentException If the payment method is not found.
     */
    public function getPayment(string $methodName): array
    {
        if (sCommerce::config('basic.payments_on', 1) !== 1) {
            return [];
        }

        $methodInstance = $this->paymentMethods[$methodName] ?? null;

        if (!$methodInstance) {
            throw new \InvalidArgumentException("Payment method '{$methodName}' not found.");
        }

        $reservedKeys = ['id', 'name', 'title', 'description'];
        $settings = $methodInstance->getSettings();
        $filteredSettings = array_diff_key($settings, array_flip($reservedKeys));

        return array_merge([
            'id' => $methodInstance->getId(),
            'key' => $methodInstance->getIdentifier(),
            'name' => $methodInstance->getName(),
            'title' => $methodInstance->getTitle(),
            'description' => $methodInstance->getDescription(),
        ], $filteredSettings);
    }

    /**
     * Validate and set order data.
     *
     * Merges validated user input into existing order data.
     *
     * @param array $data User input data to validate and merge.
     * @return array The updated order data.
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     */
    public function setOrderData(array $data): array
    {
        if (isset($data['user']['phone'])) {
            $phone = preg_replace('/[^\d+]/', '', $data['user']['phone']);
            $data['user']['phone'] = $phone;
        }

        $flattenedData = \Illuminate\Support\Arr::dot($data);

        $rules = $this->getValidationRules($data);
        $filteredRules = array_intersect_key($rules, $flattenedData);

        $validator = Validator::make($data, $filteredRules);

        if ($validator->fails()) {
            $errors = $validator->errors();
            foreach ($errors->messages() as $key => $messages) {
                $formattedKey = preg_replace('/\./', '[', $key);
                $formattedKey = preg_replace('/(\w+)$/', '$1]', $formattedKey);
                $formattedErrors[$formattedKey] = $messages;
            }
            return [
                'success' => false,
                'messages' => $formattedErrors,
            ];
        }

        $validatedData = $validator->validated();
        $resultData = [];

        foreach ($validatedData as $key => $value) {
            \Illuminate\Support\Arr::set($resultData, $key, $value);
        }

        $this->orderData = array_replace_recursive($this->orderData, $resultData);

        if ($this->requiresDelivery()) {
            if (!empty($validatedData['delivery']['method']) && isset($this->deliveryMethods[$this->orderData['delivery']['method']])) {
                $deliveryMethod = $this->deliveryMethods[$this->orderData['delivery']['method']];
                $this->orderData['delivery']['cost'] = $deliveryMethod->calculateCost($this->orderData);
            }
        }

        $_SESSION['sCheckout'] = $this->orderData;

        return array_merge(['success' => true], $this->orderData);
    }

    /**
     * Process the order and optionally trigger payment handling.
     *
     * Flow:
     * 1. Resolve the trusted checkout context; a refused context stops before any side effect.
     * 2. Validate delivery/payment methods if they are enabled. A cart without shipped lines
     *    needs no delivery method and stores `delivery_info = {method: '', required: false, cost: 0}`.
     * 3. Save the order to the database.
     * 4. Call {@see PaymentMethodInterface::processPayment()} for the selected method with the context
     *    in `checkout_context`; with a payment schedule, only an installment due at checkout is passed
     *    as `payment_request`.
     * 5. Merge payment data into `payment_info` and return it inside the `payment` key.
     * 6. Send notifications (test orders: only to the context's test recipients) and clear checkout session data.
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     order: \Seiger\sCommerce\Models\sOrder,
     *     payment?: array{success?: bool} & array<string, mixed>,
     *     redirectTo?: string
     * }
     */
    public function processOrder(): array
    {
        // One scope: the order and its payment_info update produce a single OrderCreated.
        $scope = EventScope::begin('storefront', (int)($this->orderData['user']['id'] ?? 0));
        try {
            if (empty($this->orderData['products'])) {
                throw new \Exception("Invalid products in Order.");
            }

            $this->checkoutContext = CheckoutContext::resolve($this->orderData, 'storefront', self::trustedActorId());

            $paymentMethod = null;
            $paymentOutcome = null;
            $paymentSuccess = true;

            if ($this->requiresDelivery()) {
                $deliveryMethod = $this->deliveryMethods[$this->orderData['delivery']['method'] ?? ''] ?? null;

                if (!$deliveryMethod) {
                    throw new \Exception('Invalid delivery method ' . ($this->orderData['delivery']['method'] ?? '') . '.');
                }

                $this->orderData['cost'] += $this->orderData['delivery']['cost'] ?? 0;
            } elseif (sCommerce::config('basic.deliveries_on', 1) == 1) {
                $this->orderData['delivery'] = ['method' => '', 'required' => false, 'cost' => 0];
            }

            if (sCommerce::config('basic.payments_on', 1) == 1) {
                $paymentMethod = $this->paymentMethods[$this->orderData['payment']['method']] ?? null;

                if (!$paymentMethod || !$paymentMethod->validatePayment($this->orderData)) {
                    evo()->logEvent(0, 3, 'Payment method ' . $this->orderData['payment']['method'] . ' is Invalid or not available. Please check your API credentials.', 'sCommerce: Payment ' . $this->orderData['payment']['method'] . ' Failed');
                    Log::channel('scommerce')->error("Order processing failed: Payment method {$this->orderData['payment']['method']} is Invalid or not available. Please check your API credentials.", ['order_data' => $this->orderData]);

                    return [
                        'success' => false,
                        'message' => 'The selected payment method is currently unavailable. Please try again later.',
                        'error_code' => 0,
                    ];
                }
            }

            $_SESSION['sCheckout'] = $this->orderData;
            $order = $this->saveOrder();

            evo()->logEvent(0, 1, "Order #{$order->id} successfully created.", "sCommerce: Order #{$order->id} Created");
            Log::channel('scommerce')->info("Order #{$order->id} successfully created.", ['order_id' => $order->id, 'total_cost' => $order->cost, 'user_id' => $order->user_id]);

            $paymentData = $order->toArray();
            $paymentData['checkout_context'] = $this->checkoutContext;
            $schedule = PaymentSchedule::of($order);
            if ($paymentMethod && $schedule) {
                // Only an installment due at checkout is charged now; the amount never comes from the client.
                $installment = (new PaymentFlow())->summary($order)->nextInstallment;
                if ($installment && $installment['due'] === 'checkout') {
                    $paymentData['payment_request'] = PaymentSchedule::paymentRequest($order, $installment);
                } else {
                    $paymentMethod = null;
                }
            }

            if ($paymentMethod) {
                try {
                    $paymentOutcome = $paymentMethod->processPayment($paymentData);

                    if (is_array($paymentOutcome)) {
                        $paymentSuccess = (bool)($paymentOutcome['success'] ?? true);

                        $paymentInfo = $order->payment_info ?? [];
                        $paymentInfo = is_array($paymentInfo) ? $paymentInfo : [];
                        $paymentInfo = array_merge($paymentInfo, $paymentOutcome);
                        if ($schedule) {
                            $paymentInfo['schedule'] = $schedule;
                        }
                        $order->payment_info = $paymentInfo;
                        $history = $order->history;
                        $history[] = [
                            'payment_status' => sOrder::PAYMENT_STATUS_AWAITING_CONFIRMATION,
                            'timestamp' => now()->toDateTimeString(),
                            'user_id' => $order->user_id,
                        ];
                        $order->history = $history;
                        $order->save();
                    } elseif ($paymentOutcome === false) {
                        $paymentSuccess = false;
                    }
                } catch (\Throwable $paymentException) {
                    $paymentSuccess = false;
                    $paymentOutcome = [
                        'success' => false,
                        'message' => $paymentException->getMessage(),
                    ];

                    Log::channel('scommerce')->error(
                        "Payment processing failed for order #{$order->id}: {$paymentException->getMessage()}",
                        ['order_id' => $order->id, 'payment_method' => $paymentMethod->getName(), 'trace' => $paymentException->getTraceAsString()]
                    );
                }
            }

            if (sCommerce::config('notifications.email_template_admin_order_on', 0)) {
                self::notifyOrder($this->checkoutContext, sCommerce::adminNotificationEmails(), 'notifications.email_template_admin_order', $order);
            }

            if (sCommerce::config('notifications.email_template_customer_order_on', 0)) {
                if (!empty($order->user_info['email'])) {
                    self::notifyOrder($this->checkoutContext, $order->user_info['email'], 'notifications.email_template_customer_order', $order);
                }
            }

            unset($_SESSION['sCheckout'], $_SESSION['sCart']);
            $this->orderData = [];

            $response = [
                'success' => $paymentSuccess,
                'message' => $paymentSuccess ? __('sCommerce::order.success') : ($paymentOutcome['message'] ?? 'Payment processing failed.'),
                'order' => $order,
            ];

            if (isset($paymentOutcome['redirectTo'])) {
                $response['redirectTo'] = $paymentOutcome['redirectTo'];
            }

            return $response;
        } catch (CheckoutContextException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 0,
            ];
        } catch (\Exception $e) {
            evo()->logEvent(0, 3, $e->getMessage(), 'sCommerce: Order Processing Failed');
            Log::channel('scommerce')->error("Order processing failed: {$e->getMessage()}", ['order_data' => $this->orderData, 'error' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        } finally {
            $scope->end();
        }
    }

    /**
     * Process the quick order.
     *
     * This method validates the input data, creates a new order for a quick purchase,
     * and saves it in the database. If product is 0, it will fetch products from the cart.
     * The trusted checkout context is resolved before the order is built.
     *
     * @param array $data The data from the request, including productId, optionId, quantity, exchange (empty returnable
     *                    containers handed over), phone, and/or email.
     * @return array An array containing the result of the operation with success status and a message.
     */
    public static function quickOrder(array $data)
    {
        $validator = Validator::make($data, [
            'product' => 'nullable|integer|exists:products,id,' . ((int)$data['productId'] == 0 ? '0' : null),
            'quantity' => 'nullable|integer|min:1',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'product' => (int)$data['productId'],
                'message' => 'Invalid data provided.',
                'errors' => $validator->errors(),
            ];
        }

        $product = sCommerce::getProduct($data['productId']);
        $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr'); // Checking if the user is authorized
        $user = evo()->getUserInfo($userId ?: 0) ?: [];
        $user = array_merge($user, evo()->getUserSettings());

        $userData = [
            'id' => $user['id'] ?? 0,
            'first_name' => $data['first_name'] ?? ($user['first_name'] ?? ''),
            'middle_name' => $data['middle_name'] ?? ($user['middle_name'] ?? ''),
            'last_name' => $data['last_name'] ?? ($user['last_name'] ?? ''),
            'email' => $data['email'] ?? ($user['email'] ?? ''),
            'phone' => $data['phone'] ?? ($user['phone'] ?? ''),
            'address' => [
                'country' => $user['country'] ?? '',
                'state' => $user['state'] ?? '',
                'city' => $user['city'] ?? '',
                'street' => $user['street'] ?? '',
                'zip' => $user['zip'] ?? '',
            ],
        ];

        if (empty($userData['phone']) && empty($userData['email'])) {
            return [
                'success' => false,
                'product' => $product?->toArray() ?? [],
                'message' => 'Either phone number or email is required.',
                'errors' => ['User is undefined'],
            ];
        }

        try {
            $context = CheckoutContext::resolve($data, 'storefront_quick', self::trustedActorId());
        } catch (CheckoutContextException $e) {
            return [
                'success' => false,
                'product' => (int)$data['productId'],
                'message' => $e->getMessage(),
            ];
        }

        if (empty($data['productId']) || $data['productId'] == 0) {
            $cartData = sCart::getMiniCart();
            if (empty($cartData['items'])) {
                return [
                    'success' => false,
                    'message' => 'Cart is empty. Cannot create a quick order.',
                ];
            }

            $productsData = $cartData['items'];
            $cost = $cartData['totalSum'];
        } else {
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Product not found.',
                ];
            }

            $quantity = isset($data['quantity']) && $data['quantity'] > 0 ? (int) $data['quantity'] : 1;
            $optionId = max(0, (int)($data['optionId'] ?? 0));
            $pricing = static::resolveProductPricing($product, $optionId);
            $price = (float)$pricing['priceAsFloat'];
            $cost = $price * $quantity;

            $productData = [
                'id' => $product->id,
                'title' => $product->title,
                'link' => $product->link,
                'coverSrc' => $product->coverSrc,
                'category' => $product->category,
                'sku' => $product->sku,
                'inventory' => $product->inventory,
                'price' => $pricing['price'],
                'priceAsFloat' => $pricing['priceAsFloat'],
                'oldPrice' => $pricing['oldPrice'],
                'oldPriceAsFloat' => $pricing['oldPriceAsFloat'],
                'quantity' => $quantity,
            ];

            if (isset($pricing['priceMode'])) {
                $productData['priceMode'] = $pricing['priceMode'];
            }
            $productData = array_merge($productData, app(OrderLineIdentity::class)->snapshot($product, $optionId));
            // Units exchanged for empty returnable containers carry no new deposit.
            $productData = array_merge($productData, ContainerDeposit::lineFields(ContainerDeposit::unit($product, $optionId), $quantity, max(0, (int)($data['exchange'] ?? 0))));
            $cost += (float)($productData['deposit_amount'] ?? 0);

            $productData = array_merge($productData, $product->physicalValues());
            $productsData = [$productData];
        }

        do {
            $identifier = Str::random(rand(32, 64));
        } while (sOrder::where('identifier', $identifier)->exists());

        $adminNotes = [
            [
                'comment' => "Quick order created by user " . implode(' ',
                    [
                        trim($userData['first_name']),
                        trim($userData['middle_name']),
                        trim($userData['last_name']),
                        trim($userData['phone']),
                        trim($userData['email']),
                    ]) . '.',
                'timestamp' => now()->toDateTimeString(),
                'user_id' => (int)$userData['id'],
            ]
        ];

        $history = [
            [
                'status' => sOrder::ORDER_STATUS_NEW,
                'timestamp' => now()->toDateTimeString(),
                'user_id' => 0,
            ]
        ];

        $order = new sOrder();
        $order->user_id = (int)$userData['id'];
        $order->identifier = $identifier;
        $order->user_info = $userData;
        $order->products = $productsData;
        $order->cost = $cost;
        $order->currency = sCommerce::currentCurrency();
        $order->lang = evo()->getLocale();
        $order->domain = trim((string)evo()->getConfig('site_key', 'default'));
        $order->is_quick = true;
        $order->manager_notes = $adminNotes;
        $order->history = $history;

        try {
            self::prepareNewOrder($order, $data, 'storefront_quick', $context);
            $schedule = PaymentSchedule::resolve($order);
        } catch (\InvalidArgumentException|CheckoutContextException $e) {
            return [
                'success' => false,
                'product' => (int)$data['productId'],
                'message' => $e->getMessage(),
            ];
        }
        if ($schedule) {
            $order->payment_info = ['schedule' => $schedule];
        }

        EventScope::run('storefront_quick', (int)$userData['id'], static function () use ($order): void {
            DB::transaction(function () use ($order): void {
                $order->save();
                self::assignBusinessReference($order);
            });
        });

        if ($data['productId'] == 0) {
            unset($_SESSION['sCheckout'], $_SESSION['sCart']);
        }

        evo()->logEvent(0, 1, "Order #{$order->id} successfully created.", "sCommerce: Order #{$order->id} By Click Created");
        Log::channel('scommerce')->info("Order By Click #{$order->id} successfully created.", ['order_id' => $order->id, 'total_cost' => $order->cost, 'user_id' => $order->user_id]);

        if (sCommerce::config('notifications.email_template_admin_fast_order_on', 0)) {
            self::notifyOrder($context, sCommerce::adminNotificationEmails(), 'notifications.email_template_admin_fast_order', $order);
        }

        if (sCommerce::config('notifications.email_template_customer_fast_order_on', 0)) {
            if (!empty($order->user_info['email'])) {
                self::notifyOrder($context, $order->user_info['email'], 'notifications.email_template_customer_fast_order', $order);
            }
        }

        return [
            'success' => true,
            'message' => __('sCommerce::order.success'),
            'order' => $order,
        ];
    }

    /**
     * Process the payment order.
     *
     * This method checks if the payment method exists and processes the payment
     * using the corresponding payment method's processPayment() method.
     * A client-sent `payment_request` is discarded. For an order (`order` = identifier) with a
     * payment schedule, or paid with a method that honours `payment_request`, it is replaced by
     * the next due installment or the unpaid rest computed from the ledger; 409 when nothing is due.
     * Client environment flags are discarded; for a known order the method receives its persisted
     * context in `checkout_context`, whoever opens the pay link.
     *
     * @param string|null $method The payment method identifier (optional, can be from URL).
     * @param array $data The data from the POST request (e.g., payment details).
     * @return array The result of the payment processing.
     */
    public function payOrder(?string $method, array $data): array
    {
        if (!$method && isset($data['payment_method'])) {
            $method = $data['payment_method'];
        }

        $paymentMethod = $this->paymentMethods[$method] ?? null;

        if (!$paymentMethod) {
            return [
                'success' => false,
                'message' => 'Payment method not found or invalid.',
                'code' => 404,
            ];
        }

        // The amount to charge is computed on the server, never taken from the request.
        unset($data['payment_request']);
        $data = CheckoutContext::withoutClientFlags($data);
        $identifier = isset($data['order']) && (is_string($data['order']) || is_int($data['order'])) ? trim((string)$data['order']) : '';
        $order = $identifier !== '' ? sOrder::where('identifier', $identifier)->first() : null;
        if ($order) {
            $data['checkout_context'] = CheckoutContext::of($order);
        }
        if ($order && PaymentSchedule::of($order) && !PaymentSchedule::supports($paymentMethod)) {
            return [
                'success' => false,
                'message' => __('sCommerce::global.payment_schedule_unsupported'),
                'code' => 422,
            ];
        }
        // Methods that honour `payment_request` are asked for the unpaid rest only.
        if ($order && PaymentSchedule::supports($paymentMethod)) {
            $installment = PaymentSchedule::nextPayment($order, (new PaymentFlow())->summary($order));
            if (!$installment || !PaymentSchedule::isDue($installment, $order)) {
                return [
                    'success' => false,
                    'message' => __('sCommerce::global.payment_nothing_due'),
                    'code' => 409,
                ];
            }
            $data['payment_request'] = PaymentSchedule::paymentRequest($order, $installment);
        }

        if (method_exists($paymentMethod, 'processPayment')) {
            return EventScope::run('storefront_pay', (int)evo()->getLoginUserID('web'), fn () => $paymentMethod->processPayment($data));
        }

        return [
            'success' => false,
            'message' => 'Payment method does not support processing.',
            'code' => 501,
        ];
    }

    /**
     * Register a delivery method.
     *
     * Adds a delivery method instance to the list of available methods.
     *
     * @param \Seiger\sCommerce\Interfaces\DeliveryMethodInterface $method The delivery method instance.
     */
    public function registerDeliveryMethod(DeliveryMethodInterface $method): void
    {
        $this->deliveryMethods[$method->getName()] = $method;
    }

    /**
     * Register a payment method.
     *
     * Adds a payment method instance to the list of available methods.
     *
     * @param \Seiger\sCommerce\Interfaces\PaymentMethodInterface $method The payment method instance.
     */
    public function registerPaymentMethod(PaymentMethodInterface $method): void
    {
        $this->paymentMethods[$method->getIdentifier()] = $method;
    }

    /**
     * Load order data from the session or database.
     *
     * @return array The order data.
     */
    protected function loadOrderData(): array
    {
        return $_SESSION['sCheckout'] ?? [];
    }

    /**
     * Load delivery methods from the database.
     *
     * Dynamically loads delivery method classes and registers them if they implement
     * the `DeliveryMethodInterface`.
     */
    protected function loadDeliveryMethods(): void
    {
        $methods = sDeliveryMethod::active()->orderBy('position')->get();

        foreach ($methods as $method) {
            if (isset($this->deliveryMethods[$method->name])) {
                continue;
            }

            if (class_exists($method->class)) {
                $className = $method->class;
                $instance = new $className();

                if ($instance instanceof DeliveryMethodInterface) {
                    $this->registerDeliveryMethod($instance);
                }
            }
        }
    }

    /**
     * Load payment methods from the database.
     *
     * Dynamically loads payment method classes and registers them if they implement
     * the `PaymentMethodInterface`.
     */
    protected function loadPaymentMethods(): void
    {
        $methods = sPaymentMethod::active()->orderBy('position')->get();

        foreach ($methods as $method) {
            if (isset($this->paymentMethods[$method->name . $method->identifier])) {
                continue;
            }

            if (class_exists($method->class)) {
                $className = $method->class;
                $instance = new $className($method->identifier);

                if ($instance instanceof PaymentMethodInterface) {
                    $this->registerPaymentMethod($instance);
                }
            }
        }
    }

    /**
     * Save the order to the database and return the order model.
     *
     * This method processes the order data stored in `$this->orderData` and saves it as a new record
     * in the `s_orders` table. It ensures the `identifier` is unique to avoid conflicts.
     * The checkout context is stored in `meta.integration`, tax data from `sCommerce.ResolveOrderTaxes`
     * in the lines and `totals`, and the payment schedule from `sCommerce.ResolvePaymentSchedule`
     * in `payment_info.schedule`.
     *
     * @return \Seiger\sCommerce\Models\sOrder The newly created order model.
     * @throws \InvalidArgumentException When the schedule is invalid or the payment method does not support installments.
     * @throws CheckoutContextException When the context cannot be resolved or stored.
     */
    protected function saveOrder(): sOrder
    {
        do {
            $identifier = Str::random(rand(32, 64));
        } while (sOrder::where('identifier', $identifier)->exists());

        $history = [
            [
                'status' => sOrder::ORDER_STATUS_NEW,
                'timestamp' => now()->toDateTimeString(),
                'user_id' => 0,
            ]
        ];

        $order = new sOrder();
        $order->user_id = $this->orderData['user']['id'] ?? 0;
        $order->user_info = $this->orderData['user'] ?? [];
        $order->delivery_info = $this->orderData['delivery'] ?? [];
        $order->payment_info = $this->orderData['payment'] ?? [];
        $order->products = $this->orderData['products'] ?? [];
        $order->cost = $this->orderData['cost'] ?? 0;
        $order->currency = $this->orderData['currency'] ?? sCommerce::currentCurrency();
        $order->status = sOrder::ORDER_STATUS_NEW;
        $order->do_not_call = intval($this->orderData['do_not_call'] ?? 0);
        $order->comment = $this->orderData['comment'] ?? '';
        if (Schema::hasColumn((new sOrder)->getTable(), 'marketing')) {
            $order->marketing = sCommerce::marketingTags() ?: null;
        }
        $order->lang = evo()->getLocale();
        $order->domain = trim((string)evo()->getConfig('site_key', 'default'));
        $order->identifier = $identifier;
        $order->history = $history;

        $context = $this->checkoutContext ?: CheckoutContext::resolve($this->orderData, 'storefront', self::trustedActorId());
        self::prepareNewOrder($order, $this->orderData, 'storefront', $context);

        $schedule = PaymentSchedule::resolve($order);
        if ($schedule) {
            $paymentMethod = $this->paymentMethods[$this->orderData['payment']['method'] ?? ''] ?? null;
            if (sCommerce::config('basic.payments_on', 1) == 1 && !PaymentSchedule::supports($paymentMethod)) {
                throw new \InvalidArgumentException(__('sCommerce::global.payment_schedule_unsupported'));
            }
            $paymentInfo = is_array($order->payment_info) ? $order->payment_info : [];
            $order->payment_info = array_merge($paymentInfo, ['schedule' => $schedule]);
        }

        DB::transaction(function () use ($order): void {
            $order->save();
            self::assignBusinessReference($order);
        });

        $_SESSION['orderNumber'] = $order->id;

        return $order;
    }

    /**
     * Bind the checkout context and collect tax data and project fields of an unsaved order.
     *
     * `sCommerce.BeforeOrderCreate` listeners may set order attributes, e.g. `meta.booking`;
     * they cannot change `meta.integration`.
     *
     * @param array<string, mixed> $data Checkout data of the order.
     * @param array<string, mixed> $context Resolved checkout context.
     * @throws CheckoutContextException When a test context cannot be stored.
     */
    private static function prepareNewOrder(sOrder $order, array $data, string $source, array $context): void
    {
        $canStore = $order->hasOptionalColumn('meta');
        if (!$canStore && ($context['environment'] ?? CheckoutContext::LIVE) !== CheckoutContext::LIVE) {
            Log::channel('scommerce')->error('sCommerce cannot store a test checkout context: run the sCommerce migrations', ['source' => $source]);
            throw new CheckoutContextException(__('sCommerce::global.checkout_context_unavailable'));
        }
        if ($canStore) {
            CheckoutContext::apply($order, $context);
        }
        // VAT display values depend on the visitor's mode; they are not order data.
        if (is_array($order->products)) {
            $display = array_flip(VatDisplay::keys());
            $order->products = OrderTaxes::applyRetailExcise(array_map(static fn ($line) => is_array($line) ? array_diff_key($line, $display) : $line, $order->products));
        }

        OrderTaxes::apply($order, $source);
        Event::dispatch('sCommerce.BeforeOrderCreate', [['order' => $order, 'data' => CheckoutContext::withoutClientFlags($data), 'source' => $source]]);

        if ($canStore) {
            CheckoutContext::apply($order, $context);
        } elseif ($order->meta !== null) {
            Log::channel('scommerce')->warning('sCommerce order meta was not stored: run the sCommerce migrations', ['source' => $source]);
            $order->offsetUnset('meta');
        }
    }

    /**
     * Send an order notification; a test order reaches only the context's test recipients.
     *
     * @param array<string, mixed> $context Checkout context of the order.
     * @param string|array<int, string> $recipients Live recipients.
     */
    private static function notifyOrder(array $context, string|array $recipients, string $templateKey, sOrder $order): void
    {
        $recipients = CheckoutContext::recipients($context, $recipients);
        if ($recipients === [] || $recipients === '') {
            return;
        }

        sCommerce::notifyEmail($recipients, sCommerce::config($templateKey, ''), ['order' => $order]);
    }

    /**
     * Authenticated web or manager user of the request; never taken from posted data.
     */
    private static function trustedActorId(): int
    {
        return (int)(evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr'));
    }

    /**
     * Assign an immutable business reference after the order receives its database id.
     *
     * The reference is derived from the persisted order id and written only when empty.
     * This keeps local checkout numbering stable while still allowing collision fallback
     * against existing imported references.
     *
     * @since 1.0.12
     * @param sOrder $order Persisted order model.
     */
    private static function assignBusinessReference(sOrder $order): void
    {
        if (!empty($order->reference)) {
            return;
        }

        /** @var \Seiger\sCommerce\Services\OrderReferenceGenerator $gen */
        $gen = app(\Seiger\sCommerce\Services\OrderReferenceGenerator::class);
        $reference = $gen->generate(is_numeric($order->id) ? (int)$order->id : null);

        if ($reference === '') {
            return;
        }

        $order->reference = $reference;
        $order->save();
    }

    private static function resolveProductPricing(object $product, int $optionId = 0): array
    {
        $priceMode = static::resolveProductPriceMode($product, $optionId);
        return app(sPriceResolver::class)->resolve($product, sCommerce::currentCurrency(), $priceMode, $optionId);
    }

    private static function resolveProductPriceMode(object $product, int $optionId = 0): string
    {
        $priceMode = static::getSessionPriceMode();

        foreach (Event::dispatch('sCommerce.ResolveProductPriceMode', [[
            'product' => $product,
            'optionId' => $optionId,
            'priceMode' => $priceMode,
        ]]) as $override) {
            if (is_string($override) && trim($override) !== '') {
                $priceMode = static::normalizePriceMode($override);
                break;
            }
        }

        return $priceMode;
    }

    private static function getSessionPriceMode(): string
    {
        $sessionPriceMode = is_array($_SESSION['sCommerce'] ?? null)
            ? ($_SESSION['sCommerce']['priceMode'] ?? null)
            : null;

        return static::normalizePriceMode(
            (string)($sessionPriceMode ?? $_SESSION['sCommercePriceMode'] ?? 'auto')
        );
    }

    private static function normalizePriceMode(string $priceMode = 'auto'): string
    {
        $priceMode = strtolower(trim($priceMode));

        return in_array($priceMode, ['wholesale', 'opt'], true) ? 'wholesale' : 'auto';
    }
}
