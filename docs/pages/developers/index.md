---
id: developers
title: Developer Guide
sidebar_position: 4
---

# Developer Guide

This guide covers advanced configuration, customization, and development with sCommerce.

## Architecture Overview

sCommerce follows a modular architecture with clear separation of concerns:

```
sCommerce/
├── src/
│   ├── Models/           # Eloquent models
│   ├── Controllers/      # HTTP controllers
│   ├── Services/         # Business logic
│   ├── Facades/          # Service facades
│   ├── Http/            # Routes and middleware
│   └── Integration/      # External integrations
├── views/               # Blade templates
├── assets/              # CSS, JS, images
└── database/            # Migrations and seeders
```

## Models

### sProduct Model

The main product model with relationships and attributes:

```php
use Seiger\sCommerce\Models\sProduct;

// Create a product
$product = sProduct::create([
    'name' => 'Product Name',
    'alias' => 'product-name',
    'price_regular' => 99.99,
    'category' => 1,
    'published' => 1
]);

// Relationships
$product->category;           // BelongsTo sCategory
$product->images;             // HasMany sProductImage
$product->attributes;         // HasMany sProductAttribute
$product->translates;         // HasMany sProductTranslate
$product->reviews;            // HasMany sProductReview

// Scopes
sProduct::published();        // Only published products
sProduct::inStock();          // Only products in stock
sProduct::byCategory(1);      // Products in specific category
sProduct::priceRange(10, 100); // Products in price range

// Attributes
$product->link;               // Product URL
$product->reviewsCount;       // Number of reviews
$product->averageRating;      // Average rating
```

### sCategory Model

Category management with hierarchical structure:

```php
use Seiger\sCommerce\Models\sCategory;

// Create a category
$category = sCategory::create([
    'name' => 'Electronics',
    'alias' => 'electronics',
    'parent' => 0,
    'published' => 1
]);

// Relationships
$category->children;          // HasMany sCategory (subcategories)
$category->parent;            // BelongsTo sCategory
$category->products;          // HasMany sProduct

// Methods
$category->getAllChildren();  // Get all subcategories recursively
$category->getPath();         // Get category path (Breadcrumbs)
$category->getProducts();     // Get products in this category
```

### sOrder Model

Order management and processing:

```php
use Seiger\sCommerce\Models\sOrder;

// Create an order
$order = sOrder::create([
    'customer_id' => 1,
    'status' => 'pending',
    'total' => 199.98,
    'currency' => 'USD'
]);

// Relationships
$order->customer;             // BelongsTo sCustomer
$order->items;                // HasMany sOrderItem
$order->payments;             // HasMany sPayment

// Methods
$order->addItem($product, $quantity, $price);
$order->calculateTotal();
$order->updateStatus('processing');
$order->sendNotification();
```

## Services

### sCommerce Service

Main service class for e-commerce operations:

```php
use Seiger\sCommerce\Facades\sCommerce;

// Product operations
$products = sCommerce::getProducts($filters);
$product = sCommerce::getProduct($id);
$product = sCommerce::getProductByAlias($alias);

// Category operations
$categories = sCommerce::getCategories();
$category = sCommerce::getCategory($id);

// Cart operations
sCommerce::addToCart($productId, $quantity);
sCommerce::removeFromCart($itemId);
sCommerce::updateCartItem($itemId, $quantity);
$cart = sCommerce::getCart();
sCommerce::clearCart();

// Order operations
$order = sCommerce::createOrder($data);
sCommerce::processOrder($orderId);
sCommerce::cancelOrder($orderId);

// Search operations
$results = sCommerce::searchProducts($query, $filters);
$suggestions = sCommerce::getSearchSuggestions($query);
```

### ProductService

Advanced product management:

```php
use Seiger\sCommerce\Services\ProductService;

$productService = new ProductService();

// Bulk operations
$productService->importFromCsv($file);
$productService->exportToCsv($filters);
$productService->updatePrices($updates);
$productService->updateInventory($updates);

// Product variants
$productService->createVariant($productId, $attributes);
$productService->updateVariant($variantId, $data);
$productService->deleteVariant($variantId);

// Product images
$productService->addImage($productId, $imagePath, $alt);
$productService->updateImage($imageId, $data);
$productService->deleteImage($imageId);
$productService->reorderImages($productId, $imageIds);
```

## API Development

### REST API Endpoints

sCommerce provides a comprehensive REST API:

```php
// Products API
GET    /api/products              # List products
GET    /api/products/{id}         # Get product details
POST   /api/products              # Create product
PUT    /api/products/{id}         # Update product
DELETE /api/products/{id}         # Delete product

// Categories API
GET    /api/categories            # List categories
GET    /api/categories/{id}       # Get category details
POST   /api/categories            # Create category
PUT    /api/categories/{id}       # Update category
DELETE /api/categories/{id}       # Delete category

// Cart API
GET    /api/cart                  # Get cart
POST   /api/cart/items            # Add item to cart
PUT    /api/cart/items/{id}       # Update cart item
DELETE /api/cart/items/{id}       # Remove cart item
DELETE /api/cart                  # Clear cart

// Orders API
GET    /api/orders                # List orders
GET    /api/orders/{id}           # Get order details
POST   /api/orders                # Create order
PUT    /api/orders/{id}           # Update order
```

### API Authentication

```php
// Using API tokens
$headers = [
    'Authorization' => 'Bearer ' . $apiToken,
    'Content-Type' => 'application/json'
];

// Using session authentication
$headers = [
    'X-CSRF-TOKEN' => $csrfToken,
    'Content-Type' => 'application/json'
];
```

### API Response Format

All API responses follow a consistent format:

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Product Name",
        "price": 99.99
    },
    "message": "Operation completed successfully",
    "meta": {
        "total": 100,
        "page": 1,
        "per_page": 20
    }
}
```

## Custom Integrations

### Payment Gateway Integration

Create a custom payment gateway:

```php
<?php namespace App\Payments;

use Seiger\sCommerce\Contracts\PaymentGatewayInterface;

class CustomPaymentGateway implements PaymentGatewayInterface
{
    public function processPayment(array $data): array
    {
        // Process payment logic
        $result = $this->callPaymentAPI($data);
        
        return [
            'success' => $result['status'] === 'success',
            'transaction_id' => $result['transaction_id'],
            'message' => $result['message']
        ];
    }
    
    public function refundPayment(string $transactionId, float $amount): array
    {
        // Refund logic
        return [
            'success' => true,
            'refund_id' => 'ref_' . time()
        ];
    }
}
```

### Shipping Provider Integration

Create a custom shipping provider:

```php
<?php namespace App\Shipping;

use Seiger\sCommerce\Contracts\ShippingProviderInterface;

class CustomShippingProvider implements ShippingProviderInterface
{
    public function calculateShipping(array $data): array
    {
        // Calculate shipping cost
        $cost = $this->calculateCost($data);
        
        return [
            'success' => true,
            'cost' => $cost,
            'delivery_time' => '3-5 business days',
            'carrier' => 'Custom Carrier'
        ];
    }
    
    public function trackShipment(string $trackingNumber): array
    {
        // Track shipment
        return [
            'success' => true,
            'status' => 'In Transit',
            'location' => 'Distribution Center',
            'estimated_delivery' => '2024-01-15'
        ];
    }
}
```

## Event System

sCommerce provides an event system for extending functionality:

```php
use Seiger\sCommerce\Events\ProductCreated;
use Seiger\sCommerce\Events\OrderCreated;
use Seiger\sCommerce\Events\OrderStatusChanged;

// Listen to events
Event::listen(ProductCreated::class, function ($event) {
    // Send notification
    // Update search index
    // Sync with external systems
});

Event::listen(OrderCreated::class, function ($event) {
    // Send confirmation email
    // Update inventory
    // Create fulfillment order
});

Event::listen(OrderStatusChanged::class, function ($event) {
    // Send status update email
    // Update external systems
    // Trigger fulfillment
});
```

## Custom Fields

Extend products and orders with custom fields:

```php
// Add custom field to products
Schema::table('s_products', function (Blueprint $table) {
    $table->json('custom_fields')->nullable();
});

// Use custom fields
$product = sProduct::find(1);
$product->custom_fields = [
    'warranty' => '2 years',
    'color' => 'Blue',
    'material' => 'Cotton'
];
$product->save();

// Access custom fields
$warranty = $product->custom_fields['warranty'] ?? null;
```

## Performance Optimization

### Database Optimization

```php
// Use eager loading to avoid N+1 queries
$products = sProduct::with(['category', 'images', 'attributes'])
    ->published()
    ->get();

// Use database indexes
Schema::table('s_products', function (Blueprint $table) {
    $table->index(['published', 'category']);
    $table->index(['price_regular', 'price_special']);
    $table->index('alias');
});

// Use query scopes
$products = sProduct::published()
    ->inStock()
    ->byCategory(1)
    ->priceRange(10, 100)
    ->get();
```

### Caching

```php
use Illuminate\Support\Facades\Cache;

// Cache product data
$products = Cache::remember('products.category.1', 3600, function () {
    return sProduct::published()
        ->byCategory(1)
        ->get();
});

// Cache category tree
$categories = Cache::remember('categories.tree', 7200, function () {
    return sCategory::with('children')
        ->where('parent', 0)
        ->published()
        ->get();
});

// Clear cache when data changes
Cache::forget('products.category.1');
Cache::forget('categories.tree');
```

### Image Optimization

```php
use Intervention\Image\Facades\Image;

// Resize product images
$image = Image::make($uploadedFile);
$image->resize(800, 600, function ($constraint) {
    $constraint->aspectRatio();
    $constraint->upsize();
});
$image->save($path);

// Generate thumbnails
$thumbnail = Image::make($uploadedFile);
$thumbnail->resize(200, 200, function ($constraint) {
    $constraint->aspectRatio();
    $constraint->upsize();
});
$thumbnail->save($thumbnailPath);
```

## Testing

### Unit Tests

```php
use Tests\TestCase;
use Seiger\sCommerce\Models\sProduct;

class ProductTest extends TestCase
{
    public function test_can_create_product()
    {
        $product = sProduct::create([
            'name' => 'Test Product',
            'alias' => 'test-product',
            'price_regular' => 99.99,
            'published' => 1
        ]);
        
        $this->assertDatabaseHas('s_products', [
            'name' => 'Test Product',
            'alias' => 'test-product'
        ]);
    }
    
    public function test_product_has_correct_price()
    {
        $product = sProduct::create([
            'name' => 'Test Product',
            'price_regular' => 100.00,
            'price_special' => 80.00
        ]);
        
        $this->assertEquals(80.00, $product->getPrice());
    }
}
```

### Feature Tests

```php
use Tests\TestCase;
use Seiger\sCommerce\Facades\sCommerce;

class CartTest extends TestCase
{
    public function test_can_add_product_to_cart()
    {
        $product = sProduct::factory()->create();
        
        $response = $this->post('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('s_cart_items', [
            'product_id' => $product->id,
            'quantity' => 2
        ]);
    }
}
```

## Deployment

### Production Configuration

```php
// config/scommerce.php
return [
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
    'images' => [
        'optimize' => true,
        'quality' => 85,
        'formats' => ['webp', 'jpg'],
    ],
    'performance' => [
        'eager_loading' => true,
        'query_optimization' => true,
    ],
];
```

### Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scommerce
DB_USERNAME=username
DB_PASSWORD=password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Files
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

## Troubleshooting

### Common Issues

#### Performance Issues
- Enable query caching
- Use database indexes
- Optimize images
- Use CDN for static assets

#### Memory Issues
- Increase PHP memory limit
- Use chunked processing for large datasets
- Optimize database queries

#### Integration Issues
- Check API credentials
- Verify webhook URLs
- Review error logs
- Test in staging environment

### Debugging

```php
// Enable debug mode
config(['scommerce.debug' => true]);

// Log API calls
Log::info('API Call', [
    'endpoint' => $endpoint,
    'data' => $data,
    'response' => $response
]);

// Profile database queries
DB::enableQueryLog();
// ... your code ...
$queries = DB::getQueryLog();
Log::info('Database Queries', $queries);
```

## Frontend Integration

### Data Attributes Convention

sCommerce uses a consistent naming convention for frontend data attributes to handle user interactions. All attributes follow the `data-sc-{action}` pattern.

#### Action Attributes

- `data-sc-buy` — Add product to cart
- `data-sc-remove` — Remove product from cart
- `data-sc-wishlist` — Add/remove product to/from wishlist
- `data-sc-compare` — Add product to comparison
- `data-sc-fast-buy` — One-click purchase
- `data-sc-increment` — Increase quantity by 1
- `data-sc-decrement` — Decrease quantity by 1

#### Parameter Attributes

- `data-sc-quantity` — Product quantity
- `data-sc-price` — Price (for client-side calculations)
- `data-sc-variant` — Variant/modification ID

### Events API

sCommerce provides a simple event system to handle cart actions. Use callbacks to respond to user interactions:

```javascript
// Listen to add to cart event
sCommerce.onAddedToCart = (data) => {
    console.log('Product added:', data.product);
    // Update mini cart
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
    document.querySelector('.mini-cart-total').textContent = data.miniCart.total;
};

// Listen to remove from cart event
sCommerce.onRemovedFromCart = (data) => {
    console.log('Product removed:', data.product);
    // Update mini cart
    document.querySelector('.mini-cart-count').textContent = data.miniCart.count;
};

// Listen to quantity update event
sCommerce.onUpdatedCart = (data) => {
    console.log('Quantity updated:', data);
};

// Listen to fast order event
sCommerce.onFastOrder = (data) => {
    if (data.success) {
        alert('Thank you! We will contact you soon.');
    }
};
```

#### Available Events

- `sCommerce.onAddedToCart` — Triggered when product is added to cart
- `sCommerce.onRemovedFromCart` — Triggered when product is removed from cart
- `sCommerce.onUpdatedCart` — Triggered when cart quantity is updated
- `sCommerce.onFastOrder` — Triggered when fast order is submitted

#### Event Data Structure

All events receive a `data` object with the following structure:

```javascript
{
    success: true,
    product: {
        id: 123,
        title: "Product Name",
        price: "99.99",
        // ... other product data
    },
    miniCart: {
        count: 3,
        total: "299.97",
        // ... other cart data
    }
}
```

### Usage Examples

#### Product Card (Catalog)

```html
<div class="product-card">
    <button data-sc-wishlist="{{$product->id}}">♥</button>
    
    <input type="number" class="qty-input" value="1" min="1" max="{{$product->inventory}}">
    
    <button data-sc-buy="{{$product->id}}">
        @lang('Buy')
    </button>
    
    <button data-sc-fast-buy="{{$product->id}}">
        @lang('Buy in 1 click')
    </button>
</div>
```

#### Shopping Cart

```html
<div class="cart-item" data-item-id="{{$product->id}}">
    <svg data-sc-remove="{{$product->id}}">...</svg>
    
    <div class="quantity-control">
        <button data-sc-decrement="{{$product->id}}">-</button>
        <input type="number" class="qty-input" value="{{$quantity}}" data-sc-quantity="{{$product->id}}">
        <button data-sc-increment="{{$product->id}}">+</button>
    </div>
</div>
```

### Deprecated API

:::warning Deprecated
The following approaches are deprecated and will be removed in version 1.5. Please migrate to the new Events API.
:::

#### Old CustomEvent API (Deprecated)

```javascript
// ❌ Deprecated - Will be removed in v1.5
document.addEventListener('sCommerceAddedToCart', (event) => {
    const data = event.detail;
    console.log(data);
});
```

**Migration:** Use `sCommerce.onAddedToCart = (data) => {}` instead.

#### Old Attribute Names (Deprecated)

- ❌ `data-s-buy` → ✅ Use `data-sc-buy`
- ❌ `data-s-fast-buy` → ✅ Use `data-sc-fast-buy`
- ❌ `data-s-remove` → ✅ Use `data-sc-remove`
- ❌ `data-s-quantity` → ✅ Use `data-sc-quantity`

### Benefits

- ✅ **W3C Valid** — All attributes follow HTML5 standards
- ✅ **Simple API** — Easy to use callbacks: `sCommerce.onEventName = (data) => {}`
- ✅ **Consistent** — Unified naming convention across the package
- ✅ **Readable** — Easy to understand and maintain
- ✅ **Extensible** — Simple to add new actions
- ✅ **Dataset API** — Automatically converts to camelCase in JavaScript (`dataset.scBuy`)
- ✅ **No dependencies** — Pure vanilla JavaScript

## Best Practices

1. **Always use transactions** for critical operations
2. **Validate input data** before processing
3. **Use proper error handling** and logging
4. **Implement rate limiting** for API endpoints
5. **Use caching** for frequently accessed data
6. **Optimize database queries** and use indexes
7. **Test thoroughly** before deploying
8. **Monitor performance** and error rates
9. **Keep dependencies updated** for security
10. **Document your customizations** for future maintenance
