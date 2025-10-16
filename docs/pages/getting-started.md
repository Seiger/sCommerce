---
id: getting-started
title: Getting Started
sidebar_position: 2
---

# Getting Started with sCommerce

This guide will help you install and configure sCommerce for your Evolution CMS project.

## Prerequisites

Before installing sCommerce, ensure you have:

- Evolution CMS **3.7+**
- PHP **8.3** or higher
- Composer **2.2** or higher
- One of the supported databases:
  - MySQL **8.0+**
  - MariaDB **10.5+**
  - PostgreSQL **10+**
  - SQLite **3.25+**

## Installation

### Step 1: Install via Composer

Navigate to your Evolution CMS `core` directory and install sCommerce:

```bash
cd core
composer require seiger/scommerce
```

### Step 2: Publish Assets

Publish the package assets and configuration:

```bash
php artisan vendor:publish --tag=scommerce
```

### Step 3: Run Migrations

Create the necessary database tables:

```bash
php artisan migrate
```

### Step 4: Clear Cache

Clear the application cache:

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

## Basic Configuration

### Step 1: Access Admin Panel

1. Log in to your Evolution CMS manager
2. Navigate to **Tools** → **sCommerce**
3. You should see the sCommerce dashboard

### Step 2: Configure Basic Settings

1. Go to **Settings** in the sCommerce admin panel
2. Configure the following basic settings:

#### Store Information
- **Store Name**: Your store's name
- **Store Email**: Contact email address
- **Store Phone**: Contact phone number
- **Store Address**: Physical address

#### Currency Settings
- **Default Currency**: USD, EUR, GBP, etc.
- **Currency Symbol**: $, €, £, etc.
- **Currency Position**: Before or after price

#### General Settings
- **Enable Guest Checkout**: Allow customers to checkout without registration
- **Require Email Verification**: Require email verification for new accounts
- **Enable Product Reviews**: Allow customers to review products

### Step 3: Create Your First Category

1. Go to **Products** → **Categories**
2. Click **Add Category**
3. Fill in the category details:
   - **Name**: Category name (e.g., "Electronics")
   - **Alias**: URL-friendly name (e.g., "electronics")
   - **Description**: Category description
   - **Parent Category**: Leave empty for root category
   - **Published**: Check to make it visible

### Step 4: Create Your First Product

1. Go to **Products** → **Products**
2. Click **Add Product**
3. Fill in the product details:

#### Basic Information
- **Name**: Product name (e.g., "iPhone 15")
- **Alias**: URL-friendly name (e.g., "iphone-15")
- **Description**: Detailed product description
- **Short Description**: Brief product summary
- **Category**: Select the category you created

#### Pricing
- **Regular Price**: $999.00
- **Special Price**: $899.00 (optional)
- **Currency**: USD

#### Inventory
- **SKU**: Product code (e.g., "IPH15-128-BLK")
- **Stock Quantity**: 100
- **Low Stock Threshold**: 10
- **Track Inventory**: Yes

#### SEO
- **Meta Title**: SEO-optimized title
- **Meta Description**: SEO description
- **Keywords**: Relevant keywords

4. Click **Save Product**

## Frontend Integration

### Step 1: Create Product Template

Create a new template in Evolution CMS for displaying products:

```php
<?php
// Get product data
$product = sCommerce::getProduct($_GET['product']);

if (!$product) {
    // Handle product not found
    return;
}

// Set page title
$modx->setPlaceholder('pagetitle', $product->name);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $product->name ?></title>
    <meta name="description" content="<?= $product->meta_description ?>">
</head>
<body>
    <div class="product-details">
        <h1><?= $product->name ?></h1>
        
        <?php if ($product->images->count() > 0): ?>
            <div class="product-images">
                <?php foreach ($product->images as $image): ?>
                    <img src="<?= $image->image ?>" alt="<?= $image->alt ?>">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="product-info">
            <div class="price">
                <?php if ($product->special_price): ?>
                    <span class="special-price">$<?= number_format($product->special_price, 2) ?></span>
                    <span class="regular-price">$<?= number_format($product->price_regular, 2) ?></span>
                <?php else: ?>
                    <span class="price">$<?= number_format($product->price_regular, 2) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="description">
                <?= $product->description ?>
            </div>
            
            <div class="add-to-cart">
                <form method="POST" action="/cart/add">
                    <input type="hidden" name="product_id" value="<?= $product->id ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product->in_stock ?>">
                    <button type="submit">Add to Cart</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
```

### Step 2: Create Category Template

Create a template for displaying product categories:

```php
<?php
// Get category data
$category = sCommerce::getCategory($_GET['category']);

if (!$category) {
    // Handle category not found
    return;
}

// Get products in this category
$products = sCommerce::getProducts([
    'category' => $category->id,
    'published' => true
]);

// Set page title
$modx->setPlaceholder('pagetitle', $category->name);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $category->name ?></title>
    <meta name="description" content="<?= $category->meta_description ?>">
</head>
<body>
    <div class="category-page">
        <h1><?= $category->name ?></h1>
        
        <?php if ($category->description): ?>
            <div class="category-description">
                <?= $category->description ?>
            </div>
        <?php endif; ?>
        
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <a href="/product/<?= $product->alias ?>">
                        <?php if ($product->images->count() > 0): ?>
                            <img src="<?= $product->images->first()->image ?>" alt="<?= $product->name ?>">
                        <?php endif; ?>
                        
                        <h3><?= $product->name ?></h3>
                        
                        <div class="price">
                            <?php if ($product->special_price): ?>
                                <span class="special-price">$<?= number_format($product->special_price, 2) ?></span>
                                <span class="regular-price">$<?= number_format($product->price_regular, 2) ?></span>
                            <?php else: ?>
                                <span class="price">$<?= number_format($product->price_regular, 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
```

### Step 3: Create Cart Template

Create a template for the shopping cart:

```php
<?php
// Get cart data
$cart = sCommerce::getCart();

// Set page title
$modx->setPlaceholder('pagetitle', 'Shopping Cart');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shopping Cart</title>
</head>
<body>
    <div class="cart-page">
        <h1>Shopping Cart</h1>
        
        <?php if ($cart->items->count() > 0): ?>
            <div class="cart-items">
                <?php foreach ($cart->items as $item): ?>
                    <div class="cart-item">
                        <div class="product-info">
                            <h3><?= $item->product->name ?></h3>
                            <p>SKU: <?= $item->product->sku ?></p>
                        </div>
                        
                        <div class="quantity">
                            <form method="POST" action="/cart/update">
                                <input type="hidden" name="item_id" value="<?= $item->id ?>">
                                <input type="number" name="quantity" value="<?= $item->quantity ?>" min="1">
                                <button type="submit">Update</button>
                            </form>
                        </div>
                        
                        <div class="price">
                            $<?= number_format($item->total, 2) ?>
                        </div>
                        
                        <div class="remove">
                            <form method="POST" action="/cart/remove">
                                <input type="hidden" name="item_id" value="<?= $item->id ?>">
                                <button type="submit">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="cart-total">
                <h3>Total: $<?= number_format($cart->total, 2) ?></h3>
                <a href="/checkout" class="checkout-btn">Proceed to Checkout</a>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <p>Your cart is empty</p>
                <a href="/products">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
```

## Next Steps

Now that you have sCommerce installed and configured:

1. **Add more products** to your catalog
2. **Create additional categories** to organize your products
3. **Configure payment methods** in the admin panel
4. **Set up shipping options** for your store
5. **Customize the frontend templates** to match your design
6. **Test the shopping cart and checkout process**

## Troubleshooting

### Common Issues

#### Products not displaying
- Check if the product is published
- Verify the category is published
- Clear the cache: `php artisan cache:clear`

#### Cart not working
- Ensure sessions are properly configured
- Check if the cart table exists in the database
- Verify the cart routes are set up correctly

#### Images not loading
- Check file permissions on the uploads directory
- Verify the image paths are correct
- Ensure the web server can access the files

### Getting Help

If you encounter issues:

1. Check the [GitHub Issues](https://github.com/Seiger/sCommerce/issues)
2. Join the [GitHub Discussions](https://github.com/Seiger/sCommerce/discussions)
3. Review the [Developer Guide](./developers.md) for advanced configuration

## What's Next?

- **[Developer Guide](./developers.md)** - Advanced configuration and customization
- **[Admin Guide](./admin.md)** - Managing your store through the admin panel