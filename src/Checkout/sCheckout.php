<?php namespace Seiger\sCommerce\Checkout;

use Illuminate\Support\Facades\Validator;
use Seiger\sCommerce\Facades\sCart;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sDeliveryMethod;
use Seiger\sCommerce\Interfaces\DeliveryMethodInterface;

class sCheckout
{
    protected array $cartData = [];
    protected array $orderData = [];
    protected array $deliveryMethods = [];
    protected array $paymentMethods = [];

    public function __construct()
    {
        $this->loadDeliveryMethods();
        $this->cartData = sCart::getMiniCart();
    }

    /**
     * Retrieve the validation rules for the checkout process.
     *
     * This method returns an array of validation rules that define the structure
     * and requirements for the data submitted during the checkout process.
     * These rules ensure that the data conforms to the expected format and
     * constraints.
     *
     * Example:
     * ```php
     * $rules = sCheckout::getValidationRules();
     * ```
     *
     * @return array An associative array of validation rules, where keys represent
     *               the fields to be validated, and values are the respective
     *               validation rules.
     *
     * Example Output:
     * [
     *      'user.name' => 'required|string|max:255',
     *      'user.email' => 'required|email|max:255',
     *      'user.phone' => 'required|string|max:20',
     *      'user.address.country' => 'sometimes|string|max:255',
     *      'user.address.state' => 'sometimes|string|max:255',
     *      'user.address.city' => 'sometimes|string|max:255',
     *      'user.address.street' => 'sometimes|string|max:255',
     *      'user.address.zip' => 'sometimes|string|max:10',
     *      'delivery.method' => 'required|string|in:pickup,delivery',
     *      'payment.method' => 'required|string|in:card,cash,online',
     *      'additional.notes' => 'nullable|string|max:1000',
     * ]
     */
    public static function getValidationRules(): array
    {
        return [
            'user.name' => 'required|string|max:255',
            'user.email' => 'required|email|max:255',
            'user.phone' => 'required|string|max:20',
            'user.address.country' => 'sometimes|string|max:255',
            'user.address.state' => 'sometimes|string|max:255',
            'user.address.city' => 'sometimes|string|max:255',
            'user.address.street' => 'sometimes|string|max:255',
            'user.address.zip' => 'sometimes|string|max:10',
            'delivery.method' => 'required|string|in:pickup,delivery',
            'payment.method' => 'required|string|in:card,cash,online',
            'additional.notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Check if cart is not empty and prepare order data.
     *
     * @return array|RedirectResponse
     */
    public function initCheckout(): array
    {
        if (count($this->cartData['items']) === 0) {
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
            'products' => $this->cartData['items'],
            'total_cost' => $this->cartData['totalSum'],
            'payment_method' => null,
            'delivery_method' => null,
            'preferences' => [
                'language' => $user['language'] ?? evo()->getLocale(),
                'currency' => $user['currency'] ?? sCommerce::currentCurrency(),
            ],
        ];

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

        return [
            'name' => $methodInstance->getName(),
            'title' => $methodInstance->getTitle(),
            'description' => $methodInstance->getDescription(),
            'settings' => $methodInstance->getSettings(),
        ];
    }

    /**
     * Register a delivery method.
     *
     * @param \Seiger\sCommerce\Interfaces\DeliveryMethodInterface $method
     * @return void
     */
    public function registerDeliveryMethod(DeliveryMethodInterface $method): void
    {
        $this->deliveryMethods[$method->getName()] = $method;
    }

    public function registerPaymentMethod(PaymentMethodInterface $method): void
    {
        $this->paymentMethods[$method->getName()] = $method;
    }

    public function getPayments(): array
    {
        return $this->paymentMethods;
    }

    /**
     * Sets the order data with the provided input.
     *
     * This method merges the provided input data into the existing order data.
     * It validates the incoming data based on the defined validation rules.
     *
     * @param array $data The data to update the order with.
     * @return array The updated order data.
     * @throws \Illuminate\Validation\ValidationException If the provided data is invalid.
     */
    public function setOrderData(array $data): array
    {
        $flattenedData = \Illuminate\Support\Arr::dot($data);

        $rules = $this->getValidationRules();
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

        foreach ($validatedData as $key => $value) {
            \Illuminate\Support\Arr::set($this->orderData, $key, $value);
        }

        return array_merge(['success' => true], $this->orderData);
    }

    public function processOrder(array $orderData): bool
    {
        $paymentMethod = $this->paymentMethods[$orderData['payment_method']] ?? null;
        $deliveryMethod = $this->deliveryMethods[$orderData['delivery_method']] ?? null;

        if (!$paymentMethod || !$deliveryMethod) {
            throw new \Exception("Invalid payment or delivery method");
        }

        $deliveryCost = $deliveryMethod->calculateCost($orderData);
        $orderData['total_cost'] += $deliveryCost;

        return $paymentMethod->processPayment($orderData);
    }

    /**
     * Load delivery methods from the database.
     *
     * @return void
     */
    protected function loadDeliveryMethods(): void
    {
        $methods = sDeliveryMethod::active()->orderBy('position')->pluck('name');

        foreach ($methods as $methodName) {
            if (isset($this->deliveryMethods[$methodName])) {
                continue;
            }

            $methodInstance = $this->createDeliveryMethodInstance($methodName);

            if ($methodInstance instanceof DeliveryMethodInterface) {
                $this->registerDeliveryMethod($methodInstance);
            }
        }
    }

    /**
     * Dynamically create a delivery method instance by its class name from the database.
     *
     * @param string $methodName
     * @return \Seiger\sCommerce\Interfaces\DeliveryMethodInterface|null
     */
    protected function createDeliveryMethodInstance(string $methodName): ?DeliveryMethodInterface
    {
        $methodData = sDeliveryMethod::where('name', $methodName)->active()->first();

        if ($methodData && class_exists($methodData->class)) {
            $className = $methodData->class;
            $instance = new $className();

            if ($instance instanceof DeliveryMethodInterface) {
                return $instance;
            }
        }

        return null;
    }
}
