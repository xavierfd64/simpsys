# COMPLETE SYSTEM OVERVIEW
## Small Food Business Management SaaS

This document describes the whole platform as one complete system.

# 1. PLATFORM AT A GLANCE

The product is a multi-tenant SaaS for small food businesses, especially:

- Fishball vendors
- Siomai vendors
- Street-food vendors
- Kanto fried chicken vendors
- Plaza food stalls
- Food carts
- Snack vendors
- Small beverage businesses
- Milk tea vendors
- Other small ready-to-sell or made-to-order food businesses

The platform has three major application areas:

1. PUBLIC SAAS
2. TENANT / BUSINESS APPLICATION
3. SUPER ADMIN PLATFORM

Version 1 is a responsive web application for:

- Desktop
- Laptop
- Tablet
- Mobile browser

A future Android application will support offline transactions and synchronization.

---

# 2. PUBLIC SAAS SIDE

The public SaaS side is the customer-facing entry point.

## Main screens

- Landing Page
- Features
- Pricing
- Login
- Sign Up
- Forgot Password
- Reset Password
- Business Setup / Onboarding

## Main flow

Visitor
    ↓
Learn about the platform
    ↓
Choose a plan / start trial
    ↓
Create account
    ↓
Create business
    ↓
Complete business setup
    ↓
Enter tenant application

The public side must communicate that the product is simple, professional, and designed for small food businesses.

---

# 3. TENANT / OWNER SIDE

Every business that subscribes becomes a tenant.

The tenant application is the business operating system used by the owner and authorized staff.

## Main tenant modules

1. Dashboard
2. POS
3. Products
4. Product Inventory
5. Supplies
6. Supply Inventory
7. Kitchen
8. Sales History
9. Transaction Details
10. Expenses
11. Reports and Analytics
12. Users / Staff
13. Business Settings
14. Payment Methods
15. Subscription

## Tenant roles

### Owner

Full business access.

### Cashier

POS and authorized transaction access.

### Kitchen Staff

Kitchen operations only.

---

# 4. TENANT DASHBOARD

The dashboard gives a quick business summary.

It should show:

- Today's Sales
- Today's Transactions
- Today's Expenses
- Estimated Net Income
- Low Product Inventory
- Low Supplies
- Recent Transactions
- Optional compact sales trend

Estimated Net Income for Version 1:

Completed Sales
-
Expenses

This is not formal accounting profit.

The dashboard must use the tenant's configured timezone.

---

# 5. POS

The POS must be fast and simple.

## Main flow

Select Product
    ↓
Adjust Quantity using - / quantity / +
    ↓
Choose DINE-IN or TO-GO when enabled
    ↓
Record Sale
    ↓
Choose Payment Method
    ↓
Enter Amount Received
    ↓
Automatic Change Calculation
    ↓
Confirm

## POS features

- Product image
- Product name
- Price
- Product search
- Category filters
- Touch-friendly product cards
- Quantity controls
- Cart
- Automatic totals
- Clear cart
- Payment methods
- Amount received
- Change calculation

## Order types

Optional and controlled in Settings:

- DINE-IN
- TO-GO

These are prepared for future delivery integration.

---

# 6. PRODUCTS AND PRODUCT INVENTORY

Products are items sold to customers.

Each product may have:

- Image
- Name
- Category
- Selling price
- Low stock threshold
- Product type
- Active/inactive status

## Product types

### Ready to Sell

Examples:

- Fishball
- Siomai
- Lumpiang gulay
- Fried chicken

Stock is added after cooking/production and deducted through POS sales.

### Made to Order

Examples:

- Milk tea
- Freshly prepared drinks
- Other prepared-on-order items

A POS sale creates a kitchen order.

---

# 7. PRODUCT INVENTORY

Product inventory means the quantity of sellable products.

Examples:

- 120 fishballs ready for sale
- 20 siomai ready for sale

Movement types include:

- Stock Added
- Batch Produced
- Sale
- Spoilage
- Damage
- Personal Consumption
- Missing
- Adjustment
- Void Reversal

Rules:

- Negative sellable inventory is not allowed.
- Every inventory change creates a movement record.
- Stock must never change silently.

---

# 8. SUPPLIES

Supplies are different from products.

Supplies are things used to prepare or support the business.

Examples:

- Cooking oil
- Sugar
- Milk
- Plastic cups
- Plastic bags
- Packaging

Version 1 uses manual supply deduction.

Possible supply movements:

- Stock Added
- Manual Usage
- Spoilage
- Adjustment

Automatic recipe/BOM deduction is a future feature.

---

# 9. KITCHEN

Kitchen is optional.

It is mainly for made-to-order products.

Kitchen order flow:

PENDING
    ↓
PREPARING
    ↓
READY
    ↓
COMPLETED

The kitchen screen should show:

- Order number
- Products
- Quantity
- Order type
- Order time
- Elapsed timer
- Current status

The design must prioritize:

- Large text
- Large controls
- Clear status
- Minimal distractions

Version 1 may use polling instead of WebSockets.

---

# 10. MIXED ORDERS

A sale may contain both ready-to-sell and made-to-order products.

Example:

Fishball x2
Milk Tea x1

System behavior:

Fishball
→ Deduct sellable inventory

Milk Tea
→ Create kitchen order

There is still one customer sale and one payment.

---

# 11. SALES AND TRANSACTIONS

The sales module stores completed and voided transactions.

Show:

- Sale number
- Date and time
- Cashier
- Order type
- Payment method
- Total
- Status

Transaction details show:

- Products
- Quantities
- Prices
- Amount received
- Change
- Void information where applicable

Statuses:

- Completed
- Voided

Voided sales must remain in history.

---

# 12. VOID SALES

Initial policy: Owner only.

Flow:

Open Transaction
    ↓
Void
    ↓
Enter reason
    ↓
Confirm

System must:

- Mark sale as voided
- Restore eligible ready-to-sell inventory
- Create inventory reversal movement
- Update related kitchen records where applicable
- Create audit log

---

# 13. EXPENSES

Expenses are separate from supplies.

Required:

- Amount
- Category
- Date

Optional:

- Payment method
- Description
- Receipt image
- Notes

---

# 14. REPORTS

Version 1 reports:

- Sales Report
- Product Report
- Product Inventory Report
- Supplies Report
- Expense Report
- Estimated Net Income

Reports support:

- Date ranges
- Tenant timezone
- Responsive display
- Pagination where needed
- Export preparation where practical

---

# 15. SETTINGS

Business settings include:

- Business information
- Logo
- Payment methods
- Order type activation
- Kitchen activation
- Timezone
- Users
- Subscription information

## Payment methods

The Owner can:

- Add
- Edit
- Enable
- Disable

Disabled methods cannot be used for new sales.

Historical transactions retain their original payment method.

## Timezone

All business-local reporting and "today" calculations must respect the tenant timezone.

---

# 16. SUPER ADMIN SAAS SIDE

The Super Admin manages the entire SaaS platform.

## Main modules

1. Super Admin Dashboard
2. Business / Tenant Management
3. Business Details
4. Plans
5. Subscription Management
6. Billing / Payment Records
7. Promotions
8. Notifications
9. Platform Analytics
10. Platform Settings

## Super Admin Dashboard

Show platform-level summaries such as:

- Total Businesses
- Active Businesses
- Trial Businesses
- Expired Businesses
- Suspended Businesses
- Subscription/revenue summaries where applicable
- Recent registrations
- Plan distribution
- Platform activity

## Business Management

Admin can:

- View businesses
- View owners
- View subscriptions
- Suspend
- Reactivate
- Manage business status

## Plans

Manage:

- Plan name
- Price
- Billing period
- User limit
- Feature limits
- Active status

## Subscriptions

Statuses may include:

- Trial
- Active
- Expired
- Cancelled
- Suspended

## Billing

Version 1 may use manual payment recording.

## Promotions

Support:

- Promo code
- Percentage or fixed discount
- Validity dates
- Usage limits
- Active status

## Notifications

Target:

- All businesses
- Specific status groups
- Specific tenant

---

# 17. MULTI-TENANT ARCHITECTURE

One Laravel application serves multiple business tenants.

The database is shared, but tenant-owned records are isolated.

Tenant identity must be determined server-side from authenticated membership/context.

Never trust a tenant_id sent by the browser.

All relevant tenant-owned records must be scoped and authorized.

---

# 18. UI SYSTEM

The design is one shared system for:

- Public SaaS
- Tenant web
- Tenant mobile web
- Super Admin

Visual personality:

- Modern
- Professional
- Clean
- Trustworthy
- Fast
- Touch-friendly
- Business-focused

Recommended structure:

Desktop:
- Sidebar
- Top header
- Main content

Mobile:
- Top header
- Drawer for complete navigation
- Bottom navigation for important areas where appropriate
- Reorganized content rather than simply shrinking desktop

The included UI reference images are the visual design authority.

---

# 19. FUTURE DEDICATED MOBILE APPLICATION

A dedicated mobile application is planned after the current web and mobile web system is completed and stable.

The current project does not include native mobile app development or detailed offline-sync implementation.

The future mobile application is expected to support concepts such as:

- Android access to the same system
- Offline transaction handling
- Synchronization when internet returns
- Server validation
- Duplicate-transaction protection

For now, this is only a future roadmap. The web system should remain architecturally clean enough to support it later.

# 20. WORDPRESS-LIKE INSTALLATION AND DEPLOYMENT

Installation must follow the Sukli sari-sari store system philosophy.

The deployment experience should be:

```text
Upload Files
    ↓
Open Website
    ↓
Installer Automatically Opens
    ↓
Enter Database Details
    ↓
System Tests Connection
    ↓
Automatic Database Setup
    ↓
Create Initial Account
    ↓
Complete
    ↓
Use System
```

The installer must automatically handle:

- Database connection validation
- Database schema creation
- Required migrations/setup
- Default settings
- Default roles/permissions
- Initial platform/business account setup
- Installation locking

The installer/user must not need to:

- Edit `.env`
- Import SQL manually
- Run Composer
- Run Artisan
- Edit `.htaccess`
- Edit server configuration
- Change DocumentRoot
- Move `public/`
- Configure Apache/PHP manually for normal installation

The goal is a WordPress-like installation that works on Z.com shared hosting and similar environments.

# 21. COMPLETE PLATFORM MAP

PUBLIC SAAS
    ├── Landing
    ├── Pricing
    ├── Sign Up
    ├── Login
    └── Onboarding

TENANT APPLICATION
    ├── Dashboard
    ├── POS
    ├── Products
    ├── Product Inventory
    ├── Supplies
    ├── Supply Inventory
    ├── Kitchen
    ├── Sales
    ├── Expenses
    ├── Reports
    ├── Users
    ├── Settings
    └── Subscription

SUPER ADMIN
    ├── Dashboard
    ├── Businesses
    ├── Plans
    ├── Subscriptions
    ├── Billing
    ├── Promotions
    ├── Notifications
    ├── Analytics
    └── Platform Settings

FUTURE
    └── Android Offline Application
