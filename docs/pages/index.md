---
id: intro
title: sCommerce for Evolution CMS
slug: /
sidebar_position: 1
---

![sCommerce](https://github.com/user-attachments/assets/1431d4ab-c2ab-4b16-b14d-ceb49227930b)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/scommerce?label=version)](https://packagist.org/packages/seiger/scommerce)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/scommerce)
[![License](https://img.shields.io/packagist/l/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Issues](https://img.shields.io/github/issues/Seiger/scommerce)](https://github.com/Seiger/scommerce/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/scommerce)](https://packagist.org/packages/seiger/scommerce)

## Welcome to sCommerce!

**sCommerce** is a comprehensive e-commerce solution designed specifically for Evolution CMS.
It provides a complete framework for building online stores with products, categories, orders,
payments, inventory management, and much more.

Whether you need a simple product catalog or a complex multi-vendor marketplace, **sCommerce**
gives you the tools to create powerful e-commerce experiences with full control over your
business logic and customer data.

👉 Start with **[Getting Started](./getting-started.md)** or explore **[Developer Guide](./developers.md)**.

## Key Features

### ✅ Product Management
- **Complete product catalog** - Products with variants, attributes, and specifications
- **Category management** - Hierarchical category structure with unlimited depth
- **Product variants** - Size, color, material, and custom attribute combinations
- **Inventory tracking** - Real-time stock management with low stock alerts
- **Product images** - Multiple images per product with gallery support
- **SEO optimization** - Meta tags, URLs, and structured data for search engines
- **Bulk operations** - Import/export products via Excel/CSV files

### ✅ Order Management
- **Order processing** - Complete order lifecycle from cart to delivery
- **Order status tracking** - Pending, processing, shipped, delivered, cancelled
- **Order history** - Complete customer order history and admin order management
- **Order notifications** - Email notifications for order status changes
- **Order analytics** - Sales reports and order statistics
- **Order search** - Advanced filtering and search capabilities

### ✅ Payment Integration
- **Multiple payment methods** - Credit cards, PayPal, bank transfers, and more
- **Payment gateways** - Integration with popular payment processors
- **Secure payments** - PCI-compliant payment processing
- **Payment status tracking** - Real-time payment status updates
- **Refund management** - Process refunds and returns
- **Payment analytics** - Payment method performance and success rates

### ✅ Customer Management
- **Customer accounts** - Registration, login, and profile management
- **Customer groups** - VIP customers, wholesale, and custom pricing
- **Address book** - Multiple shipping and billing addresses
- **Order history** - Complete purchase history and order tracking
- **Customer communication** - Email notifications and marketing tools
- **Customer analytics** - Purchase behavior and customer insights

### ✅ Shopping Cart & Checkout
- **Shopping cart** - Persistent cart with session management
- **Guest checkout** - Checkout without registration
- **Multiple currencies** - Support for different currencies and exchange rates
- **Tax calculation** - Automatic tax calculation based on location
- **Shipping calculation** - Real-time shipping cost calculation
- **Coupon system** - Discount codes and promotional offers

### ✅ Admin Interface
- **Dashboard** - Sales overview, recent orders, and key metrics
- **Product management** - Easy product creation and editing
- **Order management** - Process orders and update statuses
- **Customer management** - View and manage customer accounts
- **Reports** - Sales reports, product performance, and analytics
- **Settings** - Configure store settings, payment methods, and shipping

### ✅ Developer Features
- **REST API** - Complete API for mobile apps and integrations
- **Webhooks** - Real-time notifications for external systems
- **Custom fields** - Extend products and orders with custom data
- **Event system** - Hook into order and product events
- **Template system** - Customizable product and category templates
- **Plugin architecture** - Extend functionality with custom plugins

## Architecture Overview

```
┌──────────────────────────────────────────┐
│            sCommerce Architecture        │
├──────────────────────────────────────────┤
│                                          │
│  ┌──────────────┐      ┌──────────────┐  │
│  │   Products   │      │    Orders    │  │
│  ├──────────────┤      ├──────────────┤  │
│  │ Categories   │─────>│ Order Items  │  │
│  │ Attributes   │─────>│ Payments     │  │
│  │ Inventory    │─────>│ Shipping     │  │
│  │ Images       │─────>│ Status       │  │
│  └──────────────┘      └──────────────┘  │
│         │                      │         │
│         v                      v         │
│   ┌──────────────────────────────────┐   │
│   │        sCommerce Core            │   │
│   ├──────────────────────────────────┤   │
│   │ - Product Management             │   │
│   │ - Order Processing               │   │
│   │ - Payment Integration            │   │
│   │ - Customer Management            │   │
│   │ - Cart & Checkout                │   │
│   └──────────────────────────────────┘   │
│         │                      │         │
│         v                      v         │
│  ┌──────────────┐      ┌──────────────┐  │
│  │   Frontend   │      │    Admin     │  │
│  │   Templates  │      │  Interface   │  │
│  ├──────────────┤      ├──────────────┤  │
│  │ Product      │      │ Dashboard    │  │
│  │ Category     │      │ Orders       │  │
│  │ Cart         │      │ Products     │  │
│  │ Checkout     │      │ Customers    │  │
│  └──────────────┘      └──────────────┘  │
│                                          │
└──────────────────────────────────────────┘
```

## Quick Example

### Create a Product

```php
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sProduct;

// Create a new product
$product = sProduct::create([
    'name' => 'Premium T-Shirt',
    'alias' => 'premium-t-shirt',
    'price_regular' => 29.99,
    'description' => 'High-quality cotton t-shirt',
    'category' => 1, // Category ID
    'published' => 1,
    'in_stock' => 100
]);

// Add product images
$product->images()->create([
    'image' => 'tshirt-main.jpg',
    'alt' => 'Premium T-Shirt Front View',
    'sort' => 1
]);

// Add product attributes
$product->attributes()->create([
    'attribute' => 'color',
    'value' => 'Blue',
    'price_modifier' => 0
]);
```

### Process an Order

```php
use Seiger\sCommerce\Models\sOrder;
use Seiger\sCommerce\Models\sOrderItem;

// Create order
$order = sOrder::create([
    'customer_id' => 1,
    'status' => 'pending',
    'total' => 59.98,
    'currency' => 'USD',
    'shipping_address' => [
        'name' => 'John Doe',
        'address' => '123 Main St',
        'city' => 'New York',
        'zip' => '10001',
        'country' => 'US'
    ]
]);

// Add order items
$order->items()->create([
    'product_id' => 1,
    'quantity' => 2,
    'price' => 29.99,
    'total' => 59.98
]);

// Process payment
$order->processPayment([
    'method' => 'credit_card',
    'transaction_id' => 'txn_123456',
    'status' => 'completed'
]);

// Update order status
$order->update(['status' => 'processing']);
```

### Use the API

```php
// Get products
$products = sCommerce::getProducts([
    'category' => 1,
    'published' => true,
    'in_stock' => true
]);

// Get product details
$product = sCommerce::getProduct('premium-t-shirt');

// Add to cart
sCommerce::addToCart($product->id, 2);

// Get cart
$cart = sCommerce::getCart();

// Create order
$order = sCommerce::createOrder([
    'items' => $cart->items,
    'customer_id' => 1,
    'shipping_address' => $address
]);
```

## Use Cases

### E-commerce Stores
- **Online retail** - Complete product catalogs with shopping cart
- **B2B sales** - Wholesale pricing and customer groups
- **Digital products** - Software, ebooks, and digital downloads
- **Subscription services** - Recurring billing and subscription management
- **Multi-vendor marketplaces** - Multiple sellers on one platform

### Business Applications
- **Inventory management** - Track stock levels and reorder points
- **Sales reporting** - Detailed analytics and performance metrics
- **Customer management** - CRM integration and customer insights
- **Order fulfillment** - Warehouse management and shipping
- **Financial reporting** - Revenue tracking and tax reporting

### Integration Scenarios
- **ERP systems** - Sync with enterprise resource planning
- **Accounting software** - Export orders and financial data
- **Marketing tools** - Customer data for email campaigns
- **Analytics platforms** - E-commerce tracking and conversion
- **Mobile apps** - API integration for mobile commerce

## Requirements

- Evolution CMS **3.7+**
- PHP **8.3+**
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**
- **sTask** package for background processing (optional)

## Installation

```console
cd core
composer update
php artisan package:installrequire seiger/scommerce "*"
php artisan vendor:publish --tag=scommerce
php artisan migrate
```

Setup cron for background tasks:
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

See **[Getting Started](./getting-started.md)** for detailed installation instructions.

## Performance

sCommerce is designed for high performance:

- **Optimized queries** - Efficient database queries with proper indexing
- **Caching system** - Product and category caching for fast loading
- **Image optimization** - Automatic image resizing and compression
- **CDN support** - Content delivery network integration
- **Database optimization** - Proper indexing and query optimization
- **Memory management** - Efficient memory usage for large catalogs

### Benchmarks

Typical performance on standard hardware:

| Operation | Speed |
|-----------|-------|
| Product listing | ~50ms |
| Product details | ~30ms |
| Cart operations | ~20ms |
| Order creation | ~100ms |
| Search results | ~80ms |

Processing 10,000 products:
- Product listing: **~200ms**
- Category filtering: **~150ms**
- Search operations: **~300ms**
- Bulk operations: **~2 minutes**

*Performance varies based on catalog size and system resources.*

## Future Features

- [ ] **Multi-language support** - Full internationalization
- [ ] **Advanced analytics** - Detailed sales and customer analytics
- [ ] **AI recommendations** - Product recommendation engine
- [ ] **Mobile app** - Native mobile application
- [ ] **Advanced shipping** - Complex shipping rules and zones
- [ ] **Loyalty program** - Points and rewards system
- [ ] **A/B testing** - Product and page testing
- [ ] **Advanced reporting** - Custom report builder
- [ ] **API v2** - Enhanced REST API
- [ ] **GraphQL support** - Modern API query language

## Community & Support

- **Documentation**: [https://seiger.github.io/sCommerce](https://seiger.github.io/sCommerce)
- **Issues**: [GitHub Issues](https://github.com/Seiger/sCommerce/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Seiger/sCommerce/discussions)
- **Author**: [Seiger](https://github.com/Seiger)
- **License**: [MIT](https://github.com/Seiger/sCommerce/blob/main/LICENSE)

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write tests if applicable
5. Submit a pull request

## License

sCommerce is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Credits

Developed and maintained by [Seiger](https://github.com/Seiger).