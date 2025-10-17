---
id: admin
title: Admin Guide
sidebar_position: 3
---

# Admin Guide

This guide covers managing your sCommerce store through the admin interface.

## Dashboard Overview

The sCommerce dashboard provides a comprehensive overview of your store's performance and key metrics.

### Key Metrics

- **Total Sales** - Revenue for the selected period
- **Orders** - Number of orders placed
- **Products** - Total number of products in catalog
- **Customers** - Number of registered customers
- **Conversion Rate** - Percentage of visitors who make a purchase
- **Average Order Value** - Average amount per order

### Recent Activity

- **Recent Orders** - Latest orders with status and customer info
- **Low Stock Alerts** - Products running low on inventory
- **Recent Reviews** - New product reviews and ratings
- **System Notifications** - Important system messages

## Product Management

### Adding Products

1. Navigate to **Products** → **Products**
2. Click **Add Product**
3. Fill in the product information:

#### Basic Information
- **Name** - Product name (required)
- **Alias** - URL-friendly name (auto-generated from name)
- **Description** - Detailed product description
- **Short Description** - Brief summary for listings
- **Category** - Select primary category
- **Tags** - Keywords for search and filtering

#### Pricing
- **Regular Price** - Standard selling price
- **Special Price** - Discounted price (optional)
- **Cost Price** - Product cost for profit calculation
- **Currency** - Price currency

#### Inventory
- **SKU** - Stock Keeping Unit (unique identifier)
- **Stock Quantity** - Available inventory
- **Low Stock Threshold** - Alert when stock falls below this
- **Track Inventory** - Enable/disable stock tracking
- **Allow Backorders** - Allow orders when out of stock

#### SEO
- **Meta Title** - Page title for search engines
- **Meta Description** - Description for search results
- **Keywords** - Relevant search keywords
- **Canonical URL** - Preferred URL for duplicate content

#### Images
- **Main Image** - Primary product image
- **Gallery Images** - Additional product images
- **Alt Text** - Alternative text for accessibility
- **Image Ordering** - Drag and drop to reorder

### Product Variants

Create product variants for different sizes, colors, or other attributes:

1. Select **Create Variant** from the product page
2. Choose the variant attributes (size, color, etc.)
3. Set variant-specific pricing and inventory
4. Upload variant-specific images

### Bulk Operations

#### Import Products
1. Go to **Products** → **Import/Export**
2. Download the Excel template
3. Fill in your product data
4. Upload the completed file
5. Review and confirm the import

#### Export Products
1. Select products to export
2. Choose export format (Excel, CSV)
3. Select fields to include
4. Download the export file

#### Bulk Edit
1. Select multiple products
2. Choose **Bulk Edit** from actions
3. Update common fields (price, category, status)
4. Apply changes to selected products

## Category Management

### Creating Categories

1. Navigate to **Products** → **Categories**
2. Click **Add Category**
3. Fill in category details:

#### Basic Information
- **Name** - Category name
- **Alias** - URL-friendly name
- **Description** - Category description
- **Parent Category** - Select parent for subcategories
- **Sort Order** - Display order in navigation

#### SEO
- **Meta Title** - SEO-optimized title
- **Meta Description** - Category description for search engines
- **Keywords** - Relevant keywords
- **Image** - Category image/icon

#### Display Settings
- **Published** - Make category visible to customers
- **Show in Navigation** - Include in main menu
- **Featured** - Highlight in homepage sections

### Category Hierarchy

Organize categories in a tree structure:

```
Electronics
├── Computers
│   ├── Laptops
│   ├── Desktops
│   └── Accessories
├── Mobile Phones
│   ├── Smartphones
│   └── Accessories
└── Home Appliances
    ├── Kitchen
    └── Cleaning
```

## Order Management

### Order Processing

1. Navigate to **Orders** → **Orders**
2. Click on an order to view details
3. Update order status as needed:

#### Order Statuses
- **Pending** - Order received, awaiting processing
- **Processing** - Order being prepared
- **Shipped** - Order dispatched to customer
- **Delivered** - Order received by customer
- **Cancelled** - Order cancelled
- **Refunded** - Order refunded

### Order Details

#### Customer Information
- **Name** - Customer name
- **Email** - Contact email
- **Phone** - Contact phone
- **Address** - Shipping and billing addresses

#### Order Items
- **Product** - Product name and SKU
- **Quantity** - Number ordered
- **Price** - Unit price
- **Total** - Line total

#### Order Totals
- **Subtotal** - Sum of item prices
- **Shipping** - Shipping cost
- **Tax** - Tax amount
- **Discount** - Applied discounts
- **Total** - Final order total

### Order Actions

#### Print Documents
- **Invoice** - Customer invoice
- **Packing Slip** - Warehouse packing list
- **Shipping Label** - Shipping label

#### Communication
- **Send Email** - Contact customer
- **Add Note** - Internal order note
- **Update Status** - Change order status

#### Fulfillment
- **Create Shipment** - Generate shipping label
- **Track Package** - Monitor delivery
- **Process Return** - Handle returns

## Customer Management

### Customer Overview

1. Navigate to **Customers** → **Customers**
2. View customer list with key information:
   - **Name** - Customer name
   - **Email** - Contact email
   - **Orders** - Number of orders
   - **Total Spent** - Lifetime value
   - **Last Order** - Most recent purchase
   - **Status** - Active/Inactive

### Customer Details

#### Personal Information
- **Name** - Full name
- **Email** - Primary email address
- **Phone** - Contact phone
- **Date of Birth** - Birthday (optional)
- **Registration Date** - When account was created

#### Addresses
- **Default Shipping** - Primary shipping address
- **Default Billing** - Primary billing address
- **Additional Addresses** - Other saved addresses

#### Order History
- **All Orders** - Complete purchase history
- **Order Details** - Individual order information
- **Returns** - Return history

#### Customer Groups
- **VIP** - High-value customers
- **Wholesale** - Business customers
- **Regular** - Standard customers
- **Custom Groups** - Your defined groups

### Customer Communication

#### Email Marketing
- **Newsletter** - Send promotional emails
- **Order Updates** - Transactional emails
- **Abandoned Cart** - Recover lost sales
- **Birthday** - Special offers

#### Customer Service
- **Support Tickets** - Handle customer issues
- **Live Chat** - Real-time assistance
- **Phone Support** - Direct customer contact

## Inventory Management

### Stock Overview

1. Navigate to **Inventory** → **Stock**
2. View inventory levels:
   - **Product** - Product name and SKU
   - **Current Stock** - Available quantity
   - **Reserved** - Quantity allocated to orders
   - **Available** - Quantity available for sale
   - **Status** - In Stock/Low Stock/Out of Stock

### Low Stock Alerts

Configure automatic alerts when inventory falls below threshold:

1. Go to **Settings** → **Inventory**
2. Set **Low Stock Threshold** (e.g., 10 units)
3. Enable **Email Notifications**
4. Add **Alert Recipients**

### Stock Adjustments

#### Manual Adjustments
1. Select product from inventory list
2. Click **Adjust Stock**
3. Enter adjustment quantity
4. Add reason for adjustment
5. Save changes

#### Bulk Adjustments
1. Select multiple products
2. Choose **Bulk Adjust**
3. Set adjustment amount
4. Apply to selected products

### Stock Transfers

Move inventory between locations:

1. Go to **Inventory** → **Transfers**
2. Create new transfer
3. Select source and destination
4. Choose products and quantities
5. Process transfer

## Reports and Analytics

### Sales Reports

#### Revenue Reports
- **Daily Sales** - Revenue by day
- **Monthly Sales** - Revenue by month
- **Yearly Sales** - Annual revenue
- **Product Performance** - Best-selling products

#### Order Reports
- **Order Volume** - Number of orders
- **Average Order Value** - AOV trends
- **Order Status** - Distribution by status
- **Geographic Sales** - Sales by location

### Customer Analytics

#### Customer Insights
- **Customer Lifetime Value** - CLV analysis
- **Customer Acquisition** - New customer trends
- **Customer Retention** - Repeat purchase rates
- **Customer Segmentation** - Group analysis

#### Behavior Analysis
- **Purchase Patterns** - Buying behavior
- **Product Affinity** - Related products
- **Cart Abandonment** - Lost sales analysis
- **Search Analytics** - Popular searches

### Product Analytics

#### Performance Metrics
- **Top Products** - Best sellers
- **Slow Movers** - Low-performing products
- **Category Performance** - Sales by category
- **Inventory Turnover** - Stock movement

#### Profitability
- **Gross Margin** - Profit per product
- **Cost Analysis** - Product costs
- **Pricing Optimization** - Price performance
- **Discount Impact** - Promotion effectiveness

## Settings and Configuration

### Store Settings

#### General Information
- **Store Name** - Your business name
- **Store Email** - Contact email
- **Store Phone** - Contact phone
- **Store Address** - Physical address
- **Store Logo** - Brand logo

#### Currency and Pricing
- **Default Currency** - Primary currency
- **Currency Symbol** - Display symbol
- **Price Format** - Number formatting
- **Tax Settings** - Tax configuration

#### Checkout Settings
- **Guest Checkout** - Allow without registration
- **Email Verification** - Require verification
- **Terms and Conditions** - Legal requirements
- **Privacy Policy** - Data protection

### Payment Settings

#### Payment Methods
- **Credit Cards** - Card processing
- **PayPal** - PayPal integration
- **Bank Transfer** - Wire transfer
- **Cash on Delivery** - COD option
- **Digital Wallets** - Apple Pay, Google Pay

#### Payment Processing
- **Test Mode** - Sandbox testing
- **Live Mode** - Production processing
- **Webhook URLs** - Payment notifications
- **Security Settings** - Fraud prevention

### Shipping Settings

#### Shipping Methods
- **Standard Shipping** - Regular delivery
- **Express Shipping** - Fast delivery
- **Free Shipping** - Free delivery threshold
- **Local Pickup** - Store pickup option

#### Shipping Zones
- **Domestic** - Local shipping
- **International** - Global shipping
- **Restricted** - Limited areas
- **Free Shipping** - Complimentary delivery

#### Shipping Rates
- **Flat Rate** - Fixed cost
- **Weight Based** - Rate by weight
- **Price Based** - Rate by order value
- **Distance Based** - Rate by location

## System Maintenance

### Database Maintenance

#### Cleanup Operations
- **Old Orders** - Archive completed orders
- **Expired Sessions** - Clear session data
- **Log Files** - Rotate log files
- **Temporary Files** - Clean temp data

#### Optimization
- **Database Indexing** - Optimize queries
- **Query Analysis** - Identify slow queries
- **Table Optimization** - Defragment tables
- **Backup Verification** - Test backups

### Cache Management

#### Cache Types
- **Product Cache** - Product data
- **Category Cache** - Category trees
- **Search Cache** - Search results
- **Page Cache** - Rendered pages

#### Cache Operations
- **Clear All** - Remove all cached data
- **Clear Specific** - Remove selected cache
- **Warm Cache** - Preload cache
- **Cache Statistics** - Monitor usage

### Security

#### Access Control
- **User Permissions** - Role-based access
- **API Keys** - Secure API access
- **IP Restrictions** - Limit access by IP
- **Session Security** - Secure sessions

#### Data Protection
- **Encryption** - Encrypt sensitive data
- **Backup Security** - Secure backups
- **Audit Logs** - Track changes
- **Compliance** - Meet regulations

## Troubleshooting

### Common Issues

#### Performance Problems
- **Slow Loading** - Check server resources
- **Database Issues** - Optimize queries
- **Cache Problems** - Clear and rebuild
- **Image Loading** - Optimize images

#### Order Issues
- **Payment Failures** - Check payment settings
- **Inventory Errors** - Verify stock levels
- **Email Problems** - Check email configuration
- **Shipping Issues** - Verify shipping settings

#### Customer Problems
- **Login Issues** - Check authentication
- **Cart Problems** - Verify session settings
- **Checkout Errors** - Review checkout flow
- **Account Issues** - Check user permissions

### Getting Help

1. **Check Documentation** - Review this guide
2. **Search Issues** - Look for similar problems
3. **Contact Support** - Reach out for help
4. **Community Forum** - Ask the community
5. **Professional Services** - Hire experts

## Best Practices

### Store Management
1. **Regular Backups** - Backup data frequently
2. **Monitor Performance** - Track key metrics
3. **Update Regularly** - Keep system current
4. **Test Changes** - Test in staging first
5. **Document Changes** - Keep records

### Customer Service
1. **Quick Response** - Respond to customers fast
2. **Clear Communication** - Be clear and helpful
3. **Follow Up** - Check on order status
4. **Resolve Issues** - Fix problems quickly
5. **Learn from Feedback** - Improve based on input

### Security
1. **Strong Passwords** - Use complex passwords
2. **Regular Updates** - Keep software current
3. **Monitor Access** - Track user activity
4. **Secure Payments** - Use trusted processors
5. **Data Protection** - Protect customer data
