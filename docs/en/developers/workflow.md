---
id: workflow
title: Development Workflow
sidebar_position: 1
---

# Development Workflow with sCommerce

This comprehensive guide walks you through the complete process of building an e-commerce website using sCommerce and Evolution CMS.

## Overview

The development workflow consists of several key phases:

1. **Planning & Setup** - Project planning and environment setup
2. **Design & Architecture** - UI/UX design and system architecture
3. **Backend Development** - Core functionality implementation
4. **Frontend Development** - User interface and templates
5. **Integration & Testing** - Payment systems and testing
6. **Deployment & Launch** - Production deployment and go-live
7. **Maintenance & Optimization** - Ongoing support and improvements

---

## Phase 1: Planning & Setup

### 1.1 Project Requirements

**Define your e-commerce needs:**

- **Product catalog** - Number of products, categories, variants
- **User management** - Registration, profiles, customer groups
- **Order processing** - Cart, checkout, order management
- **Payment methods** - Credit cards, bank transfers, digital wallets
- **Shipping** - Delivery zones, rates, tracking
- **Taxes** - Tax rates, regions, calculations
- **Multi-language** - Supported languages and regions
- **SEO** - URL structure, meta tags, sitemaps

### 1.2 Environment Setup

**Prerequisites:**
```bash
# System requirements
- Evolution CMS 3.7+
- PHP 8.3+
- MySQL 8.0+ / MariaDB 10.5+ / PostgreSQL 10+ / SQLite 3.25+
- Composer 2.2+
- Node.js 18+ (for frontend build tools)
```

**Installation:**
```bash
# 1. Install Evolution CMS
composer create-project evolution-cms/evolution my-ecommerce-site

# 2. Install sCommerce
cd core
composer update
php artisan package:installrequire seiger/scommerce "*"

# 3. Publish assets
php artisan vendor:publish --tag=scommerce

# 4. Run migrations
php artisan migrate

# 5. Clear cache
php artisan cache:clear
```

### 1.3 Project Structure

**Recommended directory structure:**
```
my-ecommerce-site/
├── core/                           # Evolution CMS core
│   ├── vendor/seiger/scommerce/    # sCommerce package
│   └── custom/                     # Custom code
├── assets/                         # Static assets
│   ├── css/                        # Stylesheets
│   ├── js/                         # JavaScript
│   ├── images/                     # Images
│   └── modules/scommerce/          # Custom sCommerce assets
├── views/                          # Frontend templates
│   ├── layout.blade.php            # Main layout
│   ├── home.blade.php              # Homepage
│   ├── catalog.blade.php           # Product catalog
│   ├── product.blade.php           # Product details
│   ├── cart.blade.php              # Shopping cart
│   └── checkout.blade.php          # Checkout process
└── manager/                        # Admin interface
```

---

## Phase 2: Design & Architecture

### 2.1 UI/UX Design

**Key pages to design:**

1. **Homepage** - Hero section, featured products, categories
2. **Product Catalog** - Product grid, filters, pagination
3. **Product Details** - Images, descriptions, variants, reviews
4. **Shopping Cart** - Cart items, quantities, totals
5. **Checkout** - Customer info, shipping, payment
6. **User Account** - Profile, orders, addresses
7. **Admin Dashboard** - Orders, products, customers

**Design considerations:**
- Mobile-first responsive design
- Fast loading times
- Intuitive navigation
- Clear call-to-action buttons
- Accessible design (WCAG 2.1)

### 2.2 Database Architecture

**Core tables (automatically created by sCommerce):**

```sql
-- Products
s_products (id, name, alias, description, price_regular, price_special, ...)
s_product_images (id, product_id, image, alt, sort)
s_product_attributes (id, product_id, attribute, value, price_modifier)

-- Categories
s_categories (id, name, alias, description, parent_id, ...)
s_product_category (product_id, category_id, position, scope)

-- Orders
s_orders (id, customer_id, status, total, currency, ...)
s_order_items (id, order_id, product_id, quantity, price, total)

-- Customers
s_customers (id, user_id, first_name, last_name, email, ...)
s_addresses (id, customer_id, type, name, address, city, ...)
```

### 2.3 API Architecture

**RESTful API endpoints:**

```php
// Product API
GET    /api/products              # List products
GET    /api/products/{id}         # Get product details
POST   /api/products              # Create product (admin)
PUT    /api/products/{id}         # Update product (admin)
DELETE /api/products/{id}         # Delete product (admin)

// Cart API
GET    /api/cart                  # Get cart contents
POST   /api/cart/add              # Add item to cart
PUT    /api/cart/update           # Update cart item
DELETE /api/cart/remove           # Remove cart item

// Order API
POST   /api/orders                # Create order
GET    /api/orders/{id}           # Get order details
PUT    /api/orders/{id}/status    # Update order status
```

---

## Phase 3: Backend Development

### 3.1 Product Management

**Create product categories:**

```php
use Seiger\sCommerce\Models\sCategory;

// Create main categories
$electronics = sCategory::create([
    'name' => 'Electronics',
    'alias' => 'electronics',
    'description' => 'Electronic devices and accessories',
    'published' => 1,
    'position' => 1
]);

// Create subcategories
$smartphones = sCategory::create([
    'name' => 'Smartphones',
    'alias' => 'smartphones',
    'description' => 'Mobile phones and accessories',
    'parent_id' => $electronics->id,
    'published' => 1,
    'position' => 1
]);
```

**Add products:**

```php
use Seiger\sCommerce\Models\sProduct;

$product = sProduct::create([
    'name' => 'iPhone 15 Pro',
    'alias' => 'iphone-15-pro',
    'description' => 'Latest iPhone with advanced features',
    'short_description' => 'Premium smartphone with Pro camera system',
    'price_regular' => 999.00,
    'price_special' => 899.00,
    'category' => $smartphones->id,
    'sku' => 'IPH15-PRO-128',
    'in_stock' => 50,
    'published' => 1
]);

// Add product images
$product->images()->create([
    'image' => 'iphone-15-pro-main.jpg',
    'alt' => 'iPhone 15 Pro Front View',
    'sort' => 1
]);

// Add product attributes
$product->attributes()->create([
    'attribute' => 'color',
    'value' => 'Space Black',
    'price_modifier' => 0
]);
```

### 3.2 Order Processing

**Order creation workflow:**

```php
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderItem;

// Create order
$order = sOrder::create([
    'customer_id' => $customer->id,
    'status' => 'pending',
    'total' => 899.00,
    'currency' => 'USD',
    'shipping_address' => [
        'name' => 'John Doe',
        'address' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'zip' => '10001',
        'country' => 'US'
    ],
    'billing_address' => [
        'name' => 'John Doe',
        'address' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'zip' => '10001',
        'country' => 'US'
    ]
]);

// Add order items
$order->items()->create([
    'product_id' => $product->id,
    'quantity' => 1,
    'price' => 899.00,
    'total' => 899.00
]);

// Process payment
$paymentResult = $order->processPayment([
    'method' => 'credit_card',
    'transaction_id' => 'txn_123456',
    'status' => 'completed'
]);

if ($paymentResult) {
    $order->update(['status' => 'processing']);
}
```

### 3.3 Custom Services

**Create custom business logic:**

```php
// app/Services/ProductService.php
namespace App\Services;

use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sCategory;

class ProductService
{
    public function getFeaturedProducts(int $limit = 8): Collection
    {
        return sProduct::where('featured', 1)
            ->where('published', 1)
            ->where('in_stock', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getProductsByCategory(int $categoryId, array $filters = []): Collection
    {
        $query = sProduct::whereHas('categories', function($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        })->where('published', 1);

        // Apply filters
        if (isset($filters['price_min'])) {
            $query->where('price_regular', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('price_regular', '<=', $filters['price_max']);
        }

        return $query->paginate(12);
    }

    public function searchProducts(string $query): Collection
    {
        return sProduct::where('published', 1)
            ->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('short_description', 'LIKE', "%{$query}%");
            })
            ->get();
    }
}
```

---

## Phase 4: Frontend Development

### 4.1 Template Structure

**Main layout template (`views/layout.blade.php`):**

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My E-commerce Store')</title>
    <meta name="description" content="@yield('description', 'Online store with quality products')">
    
    <!-- CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <!-- Header -->
    <header class="header">
        @include('partials.header')
    </header>

    <!-- Main Content -->
    <main class="main">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer">
        @include('partials.footer')
    </footer>

    <!-- JavaScript -->
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
```

**Product catalog template (`views/catalog.blade.php`):**

```blade
@extends('layout')

@section('title', 'Products - ' . ($category->name ?? 'All Products'))
@section('description', $category->description ?? 'Browse our product catalog')

@section('content')
<div class="catalog-page">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs">
            <a href="/">Home</a>
            @if($category)
                <span>/</span>
                <span>{{ $category->name }}</span>
            @endif
        </nav>

        <!-- Filters -->
        <div class="filters">
            @include('partials.filters', ['filters' => $filters])
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            @foreach($products as $product)
                <div class="product-card">
                    <a href="/product/{{ $product->alias }}" class="product-link">
                        @if($product->images->count() > 0)
                            <img src="{{ $product->images->first()->image }}" 
                                 alt="{{ $product->name }}" 
                                 class="product-image">
                        @endif
                        
                        <h3 class="product-name">{{ $product->name }}</h3>
                        
                        <div class="product-price">
                            @if($product->special_price)
                                <span class="special-price">${{ number_format($product->special_price, 2) }}</span>
                                <span class="regular-price">${{ number_format($product->price_regular, 2) }}</span>
                            @else
                                <span class="price">${{ number_format($product->price_regular, 2) }}</span>
                            @endif
                        </div>
                    </a>
                    
                    <button class="btn btn-primary add-to-cart" 
                            data-product-id="{{ $product->id }}">
                        Add to Cart
                    </button>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="pagination">
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Add to cart functionality
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', async function() {
        const productId = this.dataset.productId;
        
        try {
            const response = await fetch('/api/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                showNotification('Product added to cart!', 'success');
                // Update cart counter
                updateCartCounter();
            } else {
                showNotification('Error adding product to cart', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Network error', 'error');
        }
    });
});
</script>
@endpush
```

### 4.2 Shopping Cart

**Cart template (`views/cart.blade.php`):**

```blade
@extends('layout')

@section('title', 'Shopping Cart')
@section('description', 'Review your cart items and proceed to checkout')

@section('content')
<div class="cart-page">
    <div class="container">
        <h1>Shopping Cart</h1>
        
        @if($cart->items->count() > 0)
            <div class="cart-items">
                @foreach($cart->items as $item)
                    <div class="cart-item" data-item-id="{{ $item->id }}">
                        <div class="product-info">
                            <img src="{{ $item->product->images->first()->image ?? '/images/no-image.png' }}" 
                                 alt="{{ $item->product->name }}" 
                                 class="product-image">
                            
                            <div class="product-details">
                                <h3>{{ $item->product->name }}</h3>
                                <p class="product-sku">SKU: {{ $item->product->sku }}</p>
                                
                                @if($item->product->attributes->count() > 0)
                                    <div class="product-attributes">
                                        @foreach($item->product->attributes as $attr)
                                            <span class="attribute">
                                                {{ $attr->attribute }}: {{ $attr->value }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="quantity-controls">
                            <button class="btn btn-sm quantity-decrease" data-item-id="{{ $item->id }}">-</button>
                            <input type="number" 
                                   class="quantity-input" 
                                   value="{{ $item->quantity }}" 
                                   min="1" 
                                   max="{{ $item->product->in_stock }}"
                                   data-item-id="{{ $item->id }}">
                            <button class="btn btn-sm quantity-increase" data-item-id="{{ $item->id }}">+</button>
                        </div>
                        
                        <div class="item-price">
                            ${{ number_format($item->total, 2) }}
                        </div>
                        
                        <button class="btn btn-danger remove-item" data-item-id="{{ $item->id }}">
                            Remove
                        </button>
                    </div>
                @endforeach
            </div>
            
            <div class="cart-summary">
                <div class="subtotal">
                    <span>Subtotal:</span>
                    <span>${{ number_format($cart->subtotal, 2) }}</span>
                </div>
                
                <div class="tax">
                    <span>Tax:</span>
                    <span>${{ number_format($cart->tax, 2) }}</span>
                </div>
                
                <div class="shipping">
                    <span>Shipping:</span>
                    <span>${{ number_format($cart->shipping, 2) }}</span>
                </div>
                
                <div class="total">
                    <span>Total:</span>
                    <span>${{ number_format($cart->total, 2) }}</span>
                </div>
                
                <a href="/checkout" class="btn btn-primary btn-lg checkout-btn">
                    Proceed to Checkout
                </a>
            </div>
        @else
            <div class="empty-cart">
                <h2>Your cart is empty</h2>
                <p>Add some products to get started!</p>
                <a href="/catalog" class="btn btn-primary">Continue Shopping</a>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Cart functionality
document.addEventListener('DOMContentLoaded', function() {
    // Quantity controls
    document.querySelectorAll('.quantity-decrease').forEach(button => {
        button.addEventListener('click', updateQuantity);
    });
    
    document.querySelectorAll('.quantity-increase').forEach(button => {
        button.addEventListener('click', updateQuantity);
    });
    
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', updateQuantity);
    });
    
    // Remove item
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', removeItem);
    });
});

async function updateQuantity(event) {
    const itemId = event.target.dataset.itemId;
    let quantity = 1;
    
    if (event.target.classList.contains('quantity-decrease')) {
        quantity = Math.max(1, parseInt(event.target.nextElementSibling.value) - 1);
    } else if (event.target.classList.contains('quantity-increase')) {
        quantity = parseInt(event.target.previousElementSibling.value) + 1;
    } else {
        quantity = parseInt(event.target.value);
    }
    
    await updateCartItem(itemId, quantity);
}

async function removeItem(event) {
    const itemId = event.target.dataset.itemId;
    await removeCartItem(itemId);
}

async function updateCartItem(itemId, quantity) {
    try {
        const response = await fetch('/api/cart/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                item_id: itemId,
                quantity: quantity
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Refresh page to show updated totals
        } else {
            showNotification('Error updating cart', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Network error', 'error');
    }
}

async function removeCartItem(itemId) {
    try {
        const response = await fetch('/api/cart/remove', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                item_id: itemId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            showNotification('Error removing item', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Network error', 'error');
    }
}
</script>
@endpush
```

### 4.3 Checkout Process

**Checkout template (`views/checkout.blade.php`):**

```blade
@extends('layout')

@section('title', 'Checkout')
@section('description', 'Complete your order')

@section('content')
<div class="checkout-page">
    <div class="container">
        <h1>Checkout</h1>
        
        <form id="checkout-form" method="POST" action="/checkout/process">
            @csrf
            
            <div class="checkout-content">
                <!-- Customer Information -->
                <div class="checkout-section">
                    <h2>Customer Information</h2>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="{{ old('email', $customer->email ?? '') }}">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="{{ old('first_name', $customer->first_name ?? '') }}">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="{{ old('last_name', $customer->last_name ?? '') }}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="{{ old('phone', $customer->phone ?? '') }}">
                    </div>
                </div>
                
                <!-- Shipping Address -->
                <div class="checkout-section">
                    <h2>Shipping Address</h2>
                    
                    <div class="form-group">
                        <label for="shipping_address">Address *</label>
                        <input type="text" id="shipping_address" name="shipping_address" required 
                               value="{{ old('shipping_address', $customer->shipping_address ?? '') }}">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="shipping_city">City *</label>
                            <input type="text" id="shipping_city" name="shipping_city" required 
                                   value="{{ old('shipping_city', $customer->shipping_city ?? '') }}">
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_state">State/Province</label>
                            <input type="text" id="shipping_state" name="shipping_state" 
                                   value="{{ old('shipping_state', $customer->shipping_state ?? '') }}">
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_zip">ZIP/Postal Code *</label>
                            <input type="text" id="shipping_zip" name="shipping_zip" required 
                                   value="{{ old('shipping_zip', $customer->shipping_zip ?? '') }}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="shipping_country">Country *</label>
                        <select id="shipping_country" name="shipping_country" required>
                            <option value="">Select Country</option>
                            <option value="US" {{ old('shipping_country', $customer->shipping_country ?? '') == 'US' ? 'selected' : '' }}>United States</option>
                            <option value="CA" {{ old('shipping_country', $customer->shipping_country ?? '') == 'CA' ? 'selected' : '' }}>Canada</option>
                            <option value="GB" {{ old('shipping_country', $customer->shipping_country ?? '') == 'GB' ? 'selected' : '' }}>United Kingdom</option>
                            <!-- Add more countries as needed -->
                        </select>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="checkout-section">
                    <h2>Payment Method</h2>
                    
                    <div class="payment-methods">
                        @foreach($paymentMethods as $method)
                            <div class="payment-method">
                                <input type="radio" id="payment_{{ $method->id }}" 
                                       name="payment_method" value="{{ $method->id }}" 
                                       {{ old('payment_method') == $method->id ? 'checked' : '' }}>
                                <label for="payment_{{ $method->id }}">
                                    {{ $method->title }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="checkout-section">
                    <h2>Order Summary</h2>
                    
                    <div class="order-items">
                        @foreach($cart->items as $item)
                            <div class="order-item">
                                <div class="item-info">
                                    <span class="item-name">{{ $item->product->name }}</span>
                                    <span class="item-quantity">Qty: {{ $item->quantity }}</span>
                                </div>
                                <div class="item-price">${{ number_format($item->total, 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="order-totals">
                        <div class="subtotal">
                            <span>Subtotal:</span>
                            <span>${{ number_format($cart->subtotal, 2) }}</span>
                        </div>
                        
                        <div class="tax">
                            <span>Tax:</span>
                            <span>${{ number_format($cart->tax, 2) }}</span>
                        </div>
                        
                        <div class="shipping">
                            <span>Shipping:</span>
                            <span>${{ number_format($cart->shipping, 2) }}</span>
                        </div>
                        
                        <div class="total">
                            <span>Total:</span>
                            <span>${{ number_format($cart->total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="checkout-actions">
                <a href="/cart" class="btn btn-secondary">Back to Cart</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    Complete Order
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('checkout-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form
    if (!validateCheckoutForm()) {
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Processing...';
    submitBtn.disabled = true;
    
    // Submit form
    this.submit();
});

function validateCheckoutForm() {
    const requiredFields = [
        'email', 'first_name', 'last_name',
        'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_country',
        'payment_method'
    ];
    
    let isValid = true;
    
    requiredFields.forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    if (!isValid) {
        showNotification('Please fill in all required fields', 'error');
    }
    
    return isValid;
}
</script>
@endpush
```

---

## Phase 5: Integration & Testing

### 5.1 Payment Integration

**Payment gateway integration:**

```php
// app/Services/PaymentService.php
namespace App\Services;

use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sPayment;

class PaymentService
{
    public function processPayment(sOrder $order, array $paymentData): array
    {
        $paymentMethod = $order->paymentMethod;
        
        switch ($paymentMethod->identifier) {
            case 'stripe':
                return $this->processStripePayment($order, $paymentData);
                
            case 'paypal':
                return $this->processPayPalPayment($order, $paymentData);
                
            case 'bank_invoice':
                return $this->processBankInvoicePayment($order, $paymentData);
                
            default:
                throw new \Exception('Unsupported payment method');
        }
    }
    
    private function processStripePayment(sOrder $order, array $paymentData): array
    {
        // Stripe integration logic
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $order->total * 100, // Convert to cents
                'currency' => strtolower($order->currency),
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id
                ]
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $intent->id,
                'status' => 'completed'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

### 5.2 Email Notifications

**Order confirmation emails:**

```php
// app/Mail/OrderConfirmation.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Seiger\sCommerce\Models\sOrder;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;
    
    public $order;
    
    public function __construct(sOrder $order)
    {
        $this->order = $order;
    }
    
    public function build()
    {
        return $this->subject('Order Confirmation #' . $this->order->id)
                    ->view('emails.order-confirmation')
                    ->with(['order' => $this->order]);
    }
}
```

**Email template (`views/emails/order-confirmation.blade.php`):**

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .order-details { background: #f8f9fa; padding: 15px; margin: 20px 0; }
        .order-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .total { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>Thank you for your order!</p>
        </div>
        
        <div class="content">
            <h2>Order #{{ $order->id }}</h2>
            <p>Order Date: {{ $order->created_at->format('F j, Y') }}</p>
            
            <div class="order-details">
                <h3>Order Items</h3>
                @foreach($order->items as $item)
                    <div class="order-item">
                        <div>
                            <strong>{{ $item->product->name }}</strong><br>
                            <small>SKU: {{ $item->product->sku }}</small>
                        </div>
                        <div>
                            {{ $item->quantity }} × ${{ number_format($item->price, 2) }}<br>
                            <strong>${{ number_format($item->total, 2) }}</strong>
                        </div>
                    </div>
                @endforeach
                
                <div class="order-item total">
                    <div>Total:</div>
                    <div>${{ number_format($order->total, 2) }}</div>
                </div>
            </div>
            
            <h3>Shipping Address</h3>
            <p>
                {{ $order->shipping_address['name'] }}<br>
                {{ $order->shipping_address['address'] }}<br>
                {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['zip'] }}<br>
                {{ $order->shipping_address['country'] }}
            </p>
            
            <p>We'll send you another email when your order ships.</p>
        </div>
    </div>
</body>
</html>
```

### 5.3 Testing

**Unit tests for services:**

```php
// tests/Unit/ProductServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ProductService;
use Seiger\sCommerce\Models\sProduct;
use Seiger\sCommerce\Models\sCategory;

class ProductServiceTest extends TestCase
{
    public function test_get_featured_products()
    {
        // Create test products
        $featuredProduct = sProduct::factory()->create(['featured' => 1, 'published' => 1]);
        $regularProduct = sProduct::factory()->create(['featured' => 0, 'published' => 1]);
        
        $service = new ProductService();
        $products = $service->getFeaturedProducts();
        
        $this->assertCount(1, $products);
        $this->assertEquals($featuredProduct->id, $products->first()->id);
    }
    
    public function test_search_products()
    {
        $product1 = sProduct::factory()->create(['name' => 'iPhone 15']);
        $product2 = sProduct::factory()->create(['name' => 'Samsung Galaxy']);
        
        $service = new ProductService();
        $results = $service->searchProducts('iPhone');
        
        $this->assertCount(1, $results);
        $this->assertEquals($product1->id, $results->first()->id);
    }
}
```

---

## Phase 6: Deployment & Launch

### 6.1 Production Setup

**Environment configuration:**

```bash
# .env.production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourstore.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=yourstore_production
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourstore.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

STRIPE_KEY=pk_live_your_stripe_key
STRIPE_SECRET=sk_live_your_stripe_secret
```

### 6.2 Performance Optimization

**Caching configuration:**

```php
// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'redis'),
    
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', 'scommerce'),
];
```

**Database optimization:**

```sql
-- Add indexes for better performance
CREATE INDEX idx_products_published ON s_products(published);
CREATE INDEX idx_products_category ON s_products(category);
CREATE INDEX idx_products_price ON s_products(price_regular);
CREATE INDEX idx_orders_status ON s_orders(status);
CREATE INDEX idx_orders_customer ON s_orders(customer_id);
CREATE INDEX idx_order_items_order ON s_order_items(order_id);
```

### 6.3 Security Hardening

**Security middleware:**

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");
        
        return $response;
    }
}
```

---

## Phase 7: Maintenance & Optimization

### 7.1 Monitoring

**Application monitoring:**

```php
// app/Services/MonitoringService.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Seiger\sCommerce\Models\sOrder;

class MonitoringService
{
    public function logOrderMetrics()
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        
        $todayOrders = sOrder::where('created_at', '>=', $today)->count();
        $yesterdayOrders = sOrder::whereBetween('created_at', [$yesterday, $today])->count();
        
        Log::info('Daily Order Metrics', [
            'today_orders' => $todayOrders,
            'yesterday_orders' => $yesterdayOrders,
            'growth_rate' => $yesterdayOrders > 0 ? (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100 : 0
        ]);
    }
    
    public function checkLowStock()
    {
        $lowStockProducts = sProduct::where('in_stock', '<=', 10)
            ->where('published', 1)
            ->get();
            
        if ($lowStockProducts->count() > 0) {
            Log::warning('Low Stock Alert', [
                'products' => $lowStockProducts->pluck('name', 'id')->toArray()
            ]);
        }
    }
}
```

### 7.2 Backup Strategy

**Automated backups:**

```bash
#!/bin/bash
# backup.sh

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > /backups/db_$(date +%Y%m%d_%H%M%S).sql

# File backup
tar -czf /backups/files_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/your/site

# Clean old backups (keep last 30 days)
find /backups -name "*.sql" -mtime +30 -delete
find /backups -name "*.tar.gz" -mtime +30 -delete
```

### 7.3 Performance Monitoring

**Performance metrics:**

```php
// app/Http/Middleware/PerformanceMonitoring.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;
        
        if ($executionTime > 1000) { // Log slow requests
            Log::warning('Slow Request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed
            ]);
        }
        
        return $response;
    }
}
```

---

## Best Practices

### Code Organization

1. **Follow PSR-12 coding standards**
2. **Use meaningful variable and function names**
3. **Write comprehensive tests**
4. **Document your code**
5. **Use version control effectively**

### Performance

1. **Implement caching strategies**
2. **Optimize database queries**
3. **Use CDN for static assets**
4. **Minimize HTTP requests**
5. **Compress images and assets**

### Security

1. **Validate all user inputs**
2. **Use HTTPS everywhere**
3. **Implement proper authentication**
4. **Keep dependencies updated**
5. **Regular security audits**

### User Experience

1. **Mobile-first design**
2. **Fast loading times**
3. **Intuitive navigation**
4. **Clear error messages**
5. **Accessible design**

---

## Conclusion

This development workflow provides a comprehensive guide for building e-commerce websites with sCommerce. By following these phases and best practices, you can create robust, scalable, and maintainable online stores that provide excellent user experiences and drive business growth.

Remember to adapt this workflow to your specific project requirements and always prioritize security, performance, and user experience in your development process.
