<?php namespace Seiger\sCommerce\Checkout;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Interfaces\PaymentMethodInterface;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;
use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPaymentMethod;
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
     * delivery method.
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
            'user.name' => 'required|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'required|string|max:20',
            'user.address.country' => 'sometimes|string|max:255',
            'user.address.state' => 'sometimes|string|max:255',
            'user.address.city' => 'sometimes|string|max:255',
            'user.address.street' => 'sometimes|string|max:255',
            'user.address.zip' => 'sometimes|string|max:10',
            'delivery.method' => 'required|string|in:' . implode(',', $deliveryMethods),
            'payment.method' => 'required|string|in:' . implode(',', $paymentMethods),
            'comment' => 'nullable|string|max:1000',
            'do_not_call' => 'nullable|boolean',
        ];

        if (!empty($data['delivery']['method']) && isset($this->deliveryMethods[$data['delivery']['method']])) {
            $deliveryMethod = $this->deliveryMethods[$data['delivery']['method']];
            $rules = array_merge($rules, $deliveryMethod->getValidationRules());
        }

        return $rules;
    }

    /**
     * Initialize the checkout process.
     *
     * Prepares order data for the user, including cart items, delivery, and payment preferences.
     * Redirects if the cart is empty.
     *
     * @return array The prepared order data.
     */
    public function initCheckout(): array
    {
        $cartData = sCart::getMiniCart();

        if (count($cartData['items']) === 0) {
            return evo()->sendRedirect(back()->getTargetUrl());
        }

        $userId = $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr'); // Checking if the user is authorized
        $user = evo()->getUserInfo($userId ?: 0) ?: [];
        $user = array_merge($user, evo()->getUserSettings());

        $userData = [
            'id' => $user['id'] ?? 0,
            'name' => $user['fullname'] ?? '',
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

        return $this->orderData;
    }

    /**
     * Retrieve all available delivery methods.
     *
     * This method returns a list of all registered delivery methods with their details.
     * The returned array includes the name, title, description, additional details,
     * and settings for each delivery method.
     *
     * @return array An array of delivery methods, where each method includes:
     *               - `name` (string): The unique identifier of the delivery method.
     *               - `title` (string): The localized title of the delivery method.
     *               - `description` (string): The localized description of the delivery method.
     *               - `settings` (array): Configuration settings specific to the delivery method.
     */
    public function getDeliveries(): array
    {
        $methods = [];

        if (sCommerce::config('basic.deliveries_on', 1) == 1) {
            $reservedKeys = ['name', 'title', 'description'];

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
            $reservedKeys = ['name', 'title', 'description'];

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

        $reservedKeys = ['name', 'title', 'description'];
        $settings = $methodInstance->getSettings();
        $filteredSettings = array_diff_key($settings, array_flip($reservedKeys));

        return array_merge([
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

        if (sCommerce::config('basic.deliveries_on', 1) == 1) {
            if (!empty($validatedData['delivery']['method']) && isset($this->deliveryMethods[$this->orderData['delivery']['method']])) {
                $deliveryMethod = $this->deliveryMethods[$this->orderData['delivery']['method']];
                $this->orderData['delivery']['cost'] = $deliveryMethod->calculateCost($this->orderData);
            }
        }

        $_SESSION['sCheckout'] = $this->orderData;

        return array_merge(['success' => true], $this->orderData);
    }

    /**
     * Process the order and return the result.
     *
     * This method saves the order data, logs the result, and handles errors gracefully.
     * It ensures a consistent response structure for the frontend.
     *
     * @return array The processed order data or error details.
     */
    public function processOrder(): array
    {
        try {
            if (empty($this->orderData['products'])) {
                throw new \Exception("Invalid products in Order.");
            }

            if (sCommerce::config('basic.deliveries_on', 1) == 1) {
                $deliveryMethod = $this->deliveryMethods[$this->orderData['delivery']['method']] ?? null;

                if (!$deliveryMethod) {
                    throw new \Exception("Invalid delivery method.");
                }

                $this->orderData['cost'] += $this->orderData['delivery']['cost'] ?? 0;
            }

            if (sCommerce::config('basic.payments_on', 1) == 1) {
                $paymentMethod = $this->paymentMethods[$this->orderData['payment']['method']] ?? null;

                if (!$paymentMethod) {
                    throw new \Exception("Invalid payment method.");
                }
            }

            $_SESSION['sCheckout'] = $this->orderData;

            $order = $this->saveOrder();

            evo()->logEvent(0, 1, "Order #{$order->id} successfully created.", 'sCommerce: Order Created');
            Log::info("Order #{$order->id} successfully created.", ['order_id' => $order->id, 'total_cost' => $order->cost, 'user_id' => $order->user_id]);

            if (sCommerce::config('notifications.email_template_admin_order_on', 0)) {
                self::notifyEmail(
                    explode(',', sCommerce::config('notifications.email_addresses', '')),
                    sCommerce::config('notifications.email_template_admin_order', ''),
                    ['order' => $order]
                );
            }

            if (sCommerce::config('notifications.email_template_customer_order_on', 0)) {
                if (!empty($order->user_info['email'])) {
                    self::notifyEmail(
                        $order->user_info['email'],
                        sCommerce::config('notifications.email_template_customer_order', ''),
                        ['order' => $order]
                    );
                }
            }

            unset($_SESSION['sCheckout'], $_SESSION['sCart']);
            $this->orderData = [];

            return [
                'success' => true,
                'message' => __('sCommerce::order.success'),
                'order' => $order,
            ];
        } catch (\Exception $e) {
            evo()->logEvent(0, 3, $e->getMessage(), 'sCommerce: Order Processing Failed');
            Log::error("Order processing failed: {$e->getMessage()}", ['order_data' => $this->orderData, 'error' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Process the quick order.
     *
     * This method validates the input data, creates a new order for a quick purchase,
     * and saves it in the database. If product is 0, it will fetch products from the cart.
     *
     * @param array $data The data from the request, including product_id, quantity, user_phone, and/or user_email.
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

        if (empty($data['phone']) && empty($data['email'])) {
            return [
                'success' => false,
                'message' => 'Either phone number or email is required.',
            ];
        }

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid data provided.',
                'errors' => $validator->errors(),
            ];
        }

        $userId = evo()->getLoginUserID('web') ?: evo()->getLoginUserID('mgr'); // Checking if the user is authorized
        $user = evo()->getUserInfo($userId ?: 0) ?: [];
        $user = array_merge($user, evo()->getUserSettings());

        $userData = [
            'id' => $user['id'] ?? 0,
            'name' => $user['fullname'] ?? '',
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
            $product = sCommerce::getProduct($data['productId']);
            if (!$product) {
                return [
                    'success' => false,
                    'message' => 'Product not found.',
                ];
            }

            $quantity = isset($data['quantity']) && $data['quantity'] > 0 ? (int) $data['quantity'] : 1;
            $price = sCommerce::convertPriceNumber($product->price, $product->currency, sCommerce::currentCurrency());
            $cost = $price * $quantity;

            $productsData = [
                [
                    'id' => $product->id,
                    'title' => $product->title,
                    'link' => $product->link,
                    'coverSrc' => $product->coverSrc,
                    'category' => $product->category,
                    'sku' => $product->sku,
                    'inventory' => $product->inventory,
                    'price' => $product->price,
                    'oldPrice' => $product->oldPrice,
                    'quantity' => $quantity,
                ]
            ];
        }

        do {
            $identifier = Str::random(rand(32, 64));
        } while (sOrder::where('identifier', $identifier)->exists());

        $adminNotes = [
            [
                'comment' => "Quick order created by user " . implode(' ', [trim($userData['name']), trim($userData['phone']), trim($userData['email'])]) . '.',
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
        $order->is_quick = true;
        $order->admin_notes = $adminNotes;
        $order->history = $history;
        $order->save();

        if ($data['productId'] == 0) {
            unset($_SESSION['sCheckout'], $_SESSION['sCart']);
        }

        evo()->logEvent(0, 1, "Order #{$order->id} successfully created.", 'sCommerce: Order By Click Created');
        Log::info("Order By Click #{$order->id} successfully created.", ['order_id' => $order->id, 'total_cost' => $order->cost, 'user_id' => $order->user_id]);

        if (sCommerce::config('notifications.email_template_admin_fast_order_on', 0)) {
            self::notifyEmail(
                explode(',', sCommerce::config('notifications.email_addresses', '')),
                sCommerce::config('notifications.email_template_admin_fast_order', ''),
                ['order' => $order]
            );
        }

        if (sCommerce::config('notifications.email_template_customer_fast_order_on', 0)) {
            if (!empty($order->user_info['email'])) {
                self::notifyEmail(
                    $order->user_info['email'],
                    sCommerce::config('notifications.email_template_customer_fast_order', ''),
                    ['order' => $order]
                );
            }
        }

        return [
            'success' => true,
            'message' => __('sCommerce::order.success'),
            'order' => $order,
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
     *
     * @return \Seiger\sCommerce\Models\sOrder The newly created order model.
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
        $order->lang = evo()->getLocale();
        $order->identifier = $identifier;
        $order->history = $history;
        $order->save();

        return $order;
    }

    /**
     * Sends an email notification to the specified recipients using a template and data.
     *
     * This method processes the recipient list, message template, and additional data,
     * renders the template using the View, and sends the email via the `evo()->sendMail` method.
     *
     * @param array|string $to     The recipients of the email. Can be a single email or a comma-separated list.
     * @param string $template      The email template or the text to be sent.
     * @param array $data           Additional data for the template (optional).
     *
     * @return void
     *
     * @throws \Exception If there are errors during the template rendering or email sending.
     */
    protected static function notifyEmail(array|string $to, string $template, array $data = []): void
    {
        if (is_scalar($to)) {
            $to = explode(',', $to);
        }

        $to = array_diff($to, ['', null]);

        if (!empty($to)) {
            $to = array_map('trim', $to);
            $params['to'] = implode(',', $to);

            if (trim($template)) {
                $params['subject'] = 'sCheckout notify - ' . evo()->getConfig('site_name');
                if (Str::endsWith($template, '.blade.php')) {
                    try {
                        $template = rtrim($template, '.blade.php');
                        $view = View::make($template, $data);
                        $renderSections = $view->renderSections();

                        if (isset($renderSections['subject'])) {
                            $params['subject'] = trim($renderSections['subject']);
                        }

                        $params['body'] = $view->render();
                    } catch (\Exception $e) {
                        Log::error("sCheckout. Render template. " . $e->getMessage());
                    }
                } else {
                    $params['body'] = $template;
                }

                try {
                    evo()->sendMail($params);
                } catch (\Exception $e) {
                    Log::error("sCheckout. Send Email. " . $e->getMessage());
                }
            } else {
                Log::alert("sCheckout. User notify by Email template or text missing.");
            }
        } else {
            Log::alert("sCheckout. User notify by Email address is empty.");
        }
    }
}
