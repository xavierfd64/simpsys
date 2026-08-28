# SMALL FOOD BUSINESS MANAGEMENT SAAS
## COMPLETE MASTER INSTRUCTION FOR CLAUDE

**Version:** Web + Mobile Web Version 1  
**Future:** Android Application with Offline Sync  
**Primary Deployment:** Z.com Shared Hosting  
**Architecture:** Laravel Multi-Tenant SaaS  
**Project Goal:** Build a professional, simple, modern business management system for small food vendors.

---

# 1. IMPORTANT INSTRUCTIONS TO CLAUDE

You are implementing an approved system specification.

Before changing the architecture, database design, user flows, or functionality:

1. Read this entire document.
2. Review all UI illustrations in `/ui-references/`.
3. Treat this document as the functional and technical authority.
4. Treat the UI illustrations as the visual authority.
5. Do not invent major features without approval.
6. Do not remove approved functionality without approval.
7. Do not simplify business rules without approval.
8. Build incrementally and keep the codebase production-ready.
9. Prefer clarity and maintainability over clever or unnecessarily complex code.
10. The system must remain compatible with Z.com shared hosting.

The UI illustrations are **visual references**, not screenshots that must be copied pixel-for-pixel. Recreate their design language professionally and responsively using the approved technology stack.

---

# 2. PROJECT IDENTITY

This is a **multi-tenant SaaS subscription system** for small food businesses such as:

- Fishball vendors
- Siomai vendors
- Street food vendors
- Kanto fried chicken vendors
- Plaza food stalls
- Small food carts
- Snack vendors
- Small beverage businesses
- Milk tea vendors
- Other small ready-to-sell or made-to-order food businesses

This system is intentionally simpler than a full restaurant ERP or large sari-sari store POS.

## Core philosophy

The system must be:

- Fast
- Simple
- Professional
- Modern
- Easy to learn
- Touch-friendly
- Mobile-responsive
- Low-click
- Clear for non-technical users

Avoid unnecessary enterprise features, complicated accounting, complex warehouse management, and excessive configuration in Version 1.

---

# 3. PLATFORM SCOPE

## Version 1

One responsive web application supporting:

- Desktop computers
- Laptops
- Tablets
- Mobile browsers

The desktop and mobile versions must use one shared design system and one backend. Mobile is not a separate application; it is a responsive experience optimized for smaller screens.

## Future Version

Android application with:

- Kotlin
- Jetpack Compose
- Room local database
- Offline transaction storage
- Background sync queue
- API synchronization

The Android app is **not part of the current Version 1 implementation**, but Version 1 must prepare for it architecturally.

---

# 4. HOSTING REQUIREMENT

The production application must run on Z.com shared hosting.

The application must not require:

- Docker
- Permanent Node.js server
- Redis as a mandatory dependency
- Supervisor
- VPS-only services
- Dedicated WebSocket server
- Persistent queue worker

Use shared-hosting-friendly alternatives such as:

- PHP
- Laravel
- MySQL/MariaDB
- Apache or LiteSpeed
- Laravel Scheduler
- Cron jobs
- Polling where necessary

---

# 5. REQUIRED TECHNOLOGY STACK

Use:

- PHP 8.3+
- Laravel
- MySQL 8+ or compatible MariaDB
- Blade
- Livewire
- Alpine.js
- Tailwind CSS
- Lucide icons
- Laravel authentication
- Laravel Sanctum prepared for future API access

Do not introduce React, Vue, a separate Node backend, or unnecessary microservices unless explicitly approved.

## Architectural principle

One Laravel application.

```text
Browser / Mobile Web
        |
        v
Laravel Application
        |
        +-- Blade / Livewire UI
        +-- Business Services
        +-- Authorization
        +-- Tenant Context
        +-- Future API
        |
        v
MySQL / MariaDB
```

The web controllers/components and future API controllers must reuse the same business services.

Example:

```text
Web / Livewire
      |
      v
SaleService
      ^
      |
Future API
```

Do not duplicate core business rules between web and API implementations.

---

# 6. MULTI-TENANT SAAS ARCHITECTURE

Use a single Laravel application with a shared multi-tenant database.

Each business is a tenant.

All tenant-owned records must be isolated through a server-side tenant context.

## Critical security rule

Never trust `tenant_id` from browser or client input.

Tenant identity must be determined by:

```text
Authenticated User
        |
        v
Tenant Membership / Tenant Context
        |
        v
Server-side authorization and query filtering
```

Tenant-owned data includes, where applicable:

- Products
- Product inventory
- Product inventory movements
- Supplies
- Supply inventory
- Supply movements
- Sales
- Sale items
- Kitchen orders
- Expenses
- Payment methods
- Settings
- Notifications
- Users / memberships
- Reports data

Use UUIDs where useful for public identifiers and future sync compatibility.

---

# 7. USER TYPES AND ROLES

## 7.1 Super Admin

Controls the entire SaaS platform.

Access includes:

- Super Admin dashboard
- Businesses
- Subscriptions
- Plans
- Billing records
- Platform users where applicable
- Promotions
- Platform notifications
- Platform settings
- Audit information

Super Admin is not a normal tenant user.

Admin routes should be separated, for example:

```text
/admin/*
```

## 7.2 Tenant Owner

Controls one business tenant.

Access:

- Dashboard
- POS
- Products
- Product inventory
- Supplies
- Supply inventory
- Kitchen
- Sales
- Expenses
- Reports
- Tenant users
- Business settings
- Subscription information

## 7.3 Cashier

Primary access:

- POS
- Own permitted sales history

Cashier must not manage:

- Tenant settings
- Users
- Products
- Inventory
- Expenses
- Subscription
- Other protected owner functions

Initial policy: cashier cannot void sales.

## 7.4 Kitchen Staff

Primary access:

- Kitchen screen
- Kitchen orders

Kitchen staff must not access:

- POS
- Sales reports
- Expenses
- Settings
- Subscription
- Super Admin functions

---

# 8. UI / UX AUTHORITY

Review these files before implementing UI:

- `ui-references/01_UI_Master_Reference_Board.png`
- `ui-references/02_UI_Complete_System_Board.png`

## Required visual direction

The UI should feel like:

- Modern SaaS
- Professional business software
- Clean POS
- Bright and trustworthy
- Minimal but not empty
- Easy for small business owners

Avoid making the interface look like:

- A generic restaurant ordering app
- A colorful game
- An overly dark dashboard
- A cluttered enterprise ERP

## Desktop layout

Recommended structure:

```text
+-----------------------------------------------------------+
| Sidebar      | Top Header                                 |
|              +--------------------------------------------+
| Navigation   | Main Page Content                          |
|              |                                            |
|              |                                            |
+-----------------------------------------------------------+
```

Recommended sidebar:

- Approximately 240–260px
- White or very light neutral background
- Subtle right border
- Lucide icons
- Clear active state
- Business branding at top
- Settings and user controls in predictable locations

## Mobile layout

Do not simply shrink desktop.

Use responsive reorganization.

Recommended:

- Top mobile header
- Drawer / hamburger for full navigation
- Bottom navigation for the most important areas where appropriate
- POS should be quick to reach
- Cards instead of wide tables when necessary
- Large touch targets
- Bottom sheets for mobile cart and checkout flows

## Design system

Recommended palette:

```text
Primary:        #2563EB
Primary Dark:   #1D4ED8
Accent:         #0F766E
Background:     #F8FAFC
Surface:        #FFFFFF
Text:           #0F172A
Secondary Text: #64748B
Border:         #E2E8F0
```

Status colors should be semantic:

- Success: green
- Warning: amber
- Danger: red
- Info: blue

Do not overload screens with color.

Recommended font:

- Inter or another professional sans-serif available through the application setup

Recommended components:

- Buttons
- Icon buttons
- Cards
- Inputs
- Selects
- Search bars
- Tables
- Mobile list cards
- Status badges
- Tabs
- Dropdowns
- Modals
- Confirmation dialogs
- Empty states
- Loading states
- Toast / inline feedback

Keep border radius moderate, shadows subtle, spacing consistent, and typography clear.

---

# 9. PUBLIC SAAS UI

## 9.1 Landing Page

Public page describing the system.

Include:

- Professional hero section
- Clear value proposition for small food businesses
- Key features
- Product screenshots or UI references
- Pricing call-to-action
- Sign-up call-to-action
- Login access
- Footer

Suggested core message:

A simple business management system for small food businesses to manage sales, products, inventory, kitchen orders, expenses, and business performance.

## 9.2 Pricing Page

Public pricing page.

Support:

- Monthly / yearly display where plans support it
- Plan comparison
- User limits
- Feature access
- Free trial or promotional messaging if configured
- Clear subscribe/start buttons

## 9.3 Sign Up / Registration

Collect only necessary information.

Possible flow:

```text
Create Account
    |
    v
Create / Name Business
    |
    v
Choose Plan or Start Trial
    |
    v
Business Setup
    |
    v
Dashboard
```

## 9.4 Login

Professional, minimal login page.

Support:

- Email
- Password
- Remember session where appropriate
- Forgot password
- Sign-up link

---

# 10. TENANT APPLICATION MODULES

The tenant application must contain:

1. Dashboard
2. POS
3. Products
4. Product Inventory
5. Supplies
6. Supply Inventory
7. Kitchen
8. Sales and Transaction History
9. Expenses
10. Reports and Analytics
11. Users
12. Business Settings
13. Subscription

---

# 11. TENANT DASHBOARD

The dashboard must provide a fast summary of the business.

Show:

- Today's Sales
- Today's Transactions
- Today's Expenses
- Estimated Net Income
- Low Product Inventory alerts
- Low Supplies alerts
- Recent Transactions
- Optional compact sales trend

The user should understand the current condition of the business within a few seconds.

Use the tenant's configured timezone.

## Important calculation

For Version 1:

```text
Estimated Net Income
=
Completed Sales
-
Expenses
```

Do not label this as formal accounting profit.

---

# 12. POS MODULE

The POS is the most important operational screen.

It must prioritize:

- Speed
- Large controls
- Touch-friendly interaction
- Few steps
- Clear totals
- Minimal distractions

## Product area

Show active products with:

- Image
- Name
- Price

Support:

- Product search
- Product category filtering
- Click/tap to add product

## Cart

Support:

- Selected products
- Quantity adjustment
- Minus button
- Current quantity
- Plus button
- Automatic line totals
- Remove item
- Clear cart
- Subtotal
- Total

## Order type

Optional feature controlled by Settings.

Supported initial order types:

- DINE-IN
- TO-GO

If disabled, do not show the controls.

If enabled, show large touch-friendly tabs.

This is intentionally designed for future integration with delivery and other order sources.

## Checkout flow

```text
Click RECORD SALE
        |
        v
Checkout Modal / Screen
        |
        v
Select Payment Method
        |
        v
Enter Amount Received
        |
        v
Automatically Calculate Change
        |
        v
Confirm Sale
```

Only enabled tenant payment methods can be selected.

Examples:

- Cash
- GCash
- Maya
- Bank Transfer

## POS validation

Validate:

- Cart cannot be empty
- Payment method must be enabled
- Payment amount must be sufficient where required
- Ready-to-sell inventory must be sufficient

## POS transaction rule

Use a database transaction.

```text
Create Sale
Create Sale Items
Deduct Ready-to-Sell Inventory
Create Inventory Movements
Create Kitchen Orders when required
Commit
```

If any critical operation fails:

```text
Rollback everything
```

---

# 13. PRODUCT SYSTEM

Products are the items sold to customers.

Each product supports:

- Image
- Name
- Category
- Selling price
- Low stock alert
- Product type
- Active / inactive status

## Product types

### READY TO SELL

Examples:

- Fishball
- Siomai
- Lumpiang gulay
- Fried chicken

Flow:

```text
Add / Produce Stock
        |
        v
POS Sale
        |
        v
Inventory Deducted
```

### MADE TO ORDER

Examples:

- Milk tea
- Freshly prepared beverages
- Other made-to-order food

Flow:

```text
POS Sale
        |
        v
Kitchen Order Created
        |
        v
Kitchen Staff Prepares
```

---

# 14. PRODUCT INVENTORY

Product inventory is separate from supplies.

Product inventory represents sellable stock.

Examples:

- 120 fishballs ready for sale
- 20 siomai ready for sale

Maintain:

- Current stock
- Low stock threshold
- Inventory history

Movement types:

- Stock Added
- Batch Produced
- Sale
- Spoilage
- Damage
- Personal Consumption
- Missing
- Adjustment
- Void Reversal

## Critical inventory rule

Negative inventory is not allowed.

Every stock change must create an inventory movement record.

Never silently change stock.

## Batch production

For products cooked or prepared in batches:

Example:

```text
Cooked 100 lumpiang gulay
        |
        v
Add 100 to sellable product inventory
```

The quantity is then deducted through sales.

---

# 15. SUPPLIES SYSTEM

Supplies are separate from sellable product inventory.

Examples:

- Cooking oil
- Sugar
- Milk
- Plastic cups
- Plastic bags
- Packaging materials

Each supply supports:

- Name
- Image
- Unit
- Low stock alert
- Active status
- Category where useful

Supply inventory supports:

- Stock added
- Manual usage
- Spoilage
- Adjustment
- Low supply alerts
- Movement history

## Version 1 supply rule

Supply deduction is manual.

Do not implement automatic recipe/BOM deduction in Version 1.

Recipes and automatic ingredient deduction belong to future advanced features.

---

# 16. KITCHEN MODULE

Kitchen is optional and configurable.

It is primarily for made-to-order products.

Kitchen workflow:

```text
PENDING
   |
   v
PREPARING
   |
   v
READY
   |
   v
COMPLETED
```

Kitchen orders must clearly show:

- Order number
- Products
- Quantities
- Order type
- Order time
- Elapsed time / timer
- Current status

The screen should prioritize:

- Large text
- Large buttons
- High visibility
- Minimal distractions
- Fast status changes

Version 1 may use polling instead of WebSockets.

Kitchen access:

- Owner
- Kitchen Staff

## Mixed orders

A single sale may contain both ready-to-sell and made-to-order products.

Example:

```text
Fishball x2
Milk Tea x1
```

System behavior:

```text
Fishball
    -> deduct sellable inventory

Milk Tea
    -> create kitchen order
```

There is still one customer sale and one payment.

---

# 17. SALES AND TRANSACTION HISTORY

Show:

- Sale number
- Date and time
- Cashier
- Order type
- Payment method
- Total
- Status

Sale details should show:

- Products
- Quantity
- Price
- Payment amount
- Change
- Void information when applicable

Sale statuses:

- Completed
- Voided

Voided sales:

- Must remain in history
- Must not count as active revenue
- Must never be permanently deleted

---

# 18. VOID SALE

Initial policy: only Owner can void a sale.

Flow:

```text
Open Transaction
        |
        v
Click VOID
        |
        v
Enter Reason
        |
        v
Confirm
```

System behavior:

- Mark sale as voided
- Restore eligible ready-to-sell inventory
- Create Void Reversal movement
- Cancel or appropriately update related kitchen orders when applicable
- Create audit log

Use a database transaction.

---

# 19. EXPENSES

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

Support:

- Expense categories
- Expense list/history
- Date filtering
- Tenant timezone display

---

# 20. REPORTS AND ANALYTICS

Version 1 reports:

- Sales Report
- Product Report
- Product Inventory Report
- Supplies Report
- Expense Report
- Estimated Net Income

Support:

- Date range filters
- Tenant timezone
- Pagination where needed
- Responsive presentation
- Export preparation where practical

Do not load entire historical datasets into memory.

---

# 21. USERS

Owner can:

- Add users
- Edit users
- Deactivate users
- Reset passwords through appropriate secure flows

Tenant roles:

- Owner
- Cashier
- Kitchen Staff

Plan user limits must be enforced before allowing new users.

---

# 22. BUSINESS SETTINGS

Include:

- Business information
- Business logo
- Payment methods
- Order type settings
- Kitchen settings
- Timezone
- Users
- Subscription information

## Payment methods

Owner can:

- Enable
- Disable
- Add
- Edit

Disabled methods cannot be used for new transactions.

Historical sales retain their original payment method.

## Order type settings

Owner can:

- Enable DINE-IN
- Enable TO-GO
- Configure default order type

## Timezone

Store application timestamps consistently in UTC where appropriate.

Display and calculate business-local dates using the tenant timezone.

Timezone affects:

- Dashboard
- "Today" calculations
- Reports
- Sales display
- Kitchen timing display
- Expenses

---

# 23. SUPER ADMIN SAAS MODULE

The Super Admin area manages the SaaS platform.

Modules:

1. Super Admin Dashboard
2. Business Management
3. Subscription Management
4. Plans
5. Billing
6. Promotions
7. Notifications
8. Platform Analytics
9. Platform Settings

## 23.1 Super Admin Dashboard

Show:

- Total Businesses
- Active Businesses
- Trial Businesses
- Expired Businesses
- Suspended Businesses
- Subscription / revenue summaries where valid
- Recent registrations
- Plan distribution
- Useful platform activity

## 23.2 Business Management

Admin can:

- View business
- Create business where required
- Edit business
- View owner and subscription information
- Suspend
- Reactivate
- Soft-delete according to approved data policy

## 23.3 Subscription Plans

Admin manages:

- Plan name
- Price
- Billing period
- User limit
- Feature limits
- Active status

Example plans:

- Starter
- Business
- Premium

Plan names may be changed later.

## 23.4 Subscription Management

Support statuses such as:

- Trial
- Active
- Expired
- Cancelled
- Suspended

Admin actions may include:

- Activate
- Extend
- Renew
- Expire
- Suspend
- Cancel

## 23.5 Billing

Version 1 can support manual payment recording.

Flow:

```text
Customer pays externally
        |
        v
Admin verifies / records payment
        |
        v
Billing record created
        |
        v
Subscription updated
```

Future payment gateway integrations can be added later.

## 23.6 Promotions

Support:

- Promo code
- Percentage discount
- Fixed discount
- Start/end dates
- Expiration
- Usage limit
- Active status

## 23.7 Notifications

Admin can target:

- All businesses
- Active businesses
- Trial businesses
- Expired businesses
- Specific tenant

Examples:

- Maintenance notices
- New features
- Promotions
- Subscription reminders

## 23.8 Platform Analytics

Possible summaries:

- Tenant growth
- Subscription growth
- Active businesses
- Plan distribution
- Platform usage trends

Keep analytics practical and avoid excessive complexity.

---

# 24. DATABASE REQUIREMENTS

Expected core tables include:

```text
tenants
users
roles / permissions or equivalent authorization tables
tenant_memberships where architecture requires it

subscription_plans
plan_features
subscriptions
billing_payments or payment records

product_categories
products
product_inventory
product_inventory_movements

supply_categories
supplies
supply_inventory
supply_inventory_movements

sales
sale_items

kitchen_orders
kitchen_order_items

expense_categories
expenses

payment_methods
tenant_settings

notifications
audit_logs
```

Exact naming may vary slightly, but the data model must preserve the approved relationships and business rules.

All relevant tenant-owned records must be properly isolated.

Add appropriate indexes for:

- tenant_id
- public UUIDs
- dates used in reports
- status fields used frequently
- foreign keys used in common joins

---

# 25. SECURITY REQUIREMENTS

Implement:

- CSRF protection
- Secure password hashing
- Authorization policies / gates
- Server-side tenant isolation
- Input validation
- XSS-safe output
- SQL injection protection through Laravel ORM/query binding
- Rate limiting for authentication and sensitive actions
- HTTPS in production
- Secure sessions
- Safe file upload validation

Never:

- Trust tenant_id from the request
- Expose passwords
- Expose API tokens
- Use APP_DEBUG=true in production
- Store sensitive configuration in source control

---

# 26. FILE UPLOAD RULES

Use Laravel Storage.

Recommended structure:

```text
tenants/
    {tenant_uuid}/
        logo/
        products/
        supplies/
        receipts/
```

Validate:

- Maximum file size approximately 5 MB
- JPG/JPEG
- PNG
- WEBP

Use generated filenames.

Never trust original filenames as storage paths.

---

# 27. SERVICE-LAYER AND CODING STANDARDS

Follow:

- PSR standards
- Laravel conventions
- Single Responsibility Principle
- Form Requests or equivalent validation
- Policies / authorization
- Service layer for important business operations
- Database transactions for critical multi-step operations
- Meaningful names
- Clear tests
- Comments only where useful

Avoid:

- Huge controllers
- Huge Livewire components
- Business logic in Blade templates
- Duplicate logic
- N+1 query patterns
- Hidden stock changes
- Direct client-controlled tenant IDs

Critical business services may include:

- SaleService
- InventoryService
- KitchenService
- SubscriptionService
- TenantContextService

Exact class names may vary.

---

# 28. DATABASE TRANSACTION REQUIREMENTS

Use database transactions for:

- Recording sales
- Voiding sales
- Critical inventory operations
- Subscription changes
- Other multi-step operations where partial completion would corrupt data

Example concept:

```php
DB::transaction(function () {
    // validate state
    // create records
    // update inventory
    // create movement records
});
```

---

# 29. PERFORMANCE REQUIREMENTS

Use:

- Pagination
- Database indexes
- Eager loading
- Query optimization
- Cached configuration
- Cached routes in production
- Efficient dashboard aggregation

Do not load complete:

- Sales history
- Inventory movement history
- Audit logs
- Large reports

at once.

---

# 30. SHARED HOSTING REQUIREMENTS

Production must remain compatible with Z.com shared hosting.

Avoid mandatory reliance on:

- Redis
- Supervisor
- Docker
- Permanent queue workers
- Permanent Node processes
- WebSocket servers

Use:

- MySQL
- Database/file cache as appropriate
- Laravel Scheduler
- Cron jobs
- Polling for kitchen updates

---

# 31. FUTURE API PREPARATION

Prepare a versioned API structure for the future Android app:

```text
/api/v1/
```

Potential endpoints:

```text
/api/v1/auth/login
/api/v1/products
/api/v1/sales
/api/v1/sync
```

Do not build the full Android sync system during Version 1 unless separately approved.

However, avoid architectural decisions that make future API implementation difficult.

---

# 32. FUTURE MOBILE APPLICATION PLAN

A dedicated mobile application is planned **after the web and mobile web system is completed and stable**.

Do not build the native mobile application in the current development scope.

The current system should only remain architecturally ready for a future mobile application. Future development may include:

- Android application
- Offline transaction storage
- Synchronization when internet returns
- API access to the same backend
- Duplicate-transaction protection
- Server-side validation

The current implementation must not delay or complicate future mobile development, but detailed mobile-app coding and offline-sync implementation are outside the present scope.

---

# 33. RESPONSIVE REQUIREMENTS

The following must be explicitly tested:

- Desktop
- Laptop
- Tablet
- Mobile browser

Important responsive screens:

- Dashboard
- POS
- Checkout
- Products
- Inventory
- Kitchen
- Sales
- Expenses
- Reports
- Settings
- Super Admin dashboard
- Business management
- Subscription management

## Mobile-specific rules

- Do not rely on hover
- Large touch targets
- No tiny controls
- Wide tables should become cards or scroll safely
- POS cart may use a bottom sheet
- Important actions should remain reachable
- Avoid excessive modal stacking

---

# 34. TESTING REQUIREMENTS

## Functional testing

Test:

- Authentication
- Tenant creation
- Tenant isolation
- Roles
- Products
- Product inventory
- Supplies
- POS
- Sales
- Kitchen
- Expenses
- Reports
- Settings
- Subscription management

## POS test

```text
Create Product
    |
    v
Add Inventory
    |
    v
Create Sale
    |
    v
Verify Sale
    |
    v
Verify Inventory Deduction
    |
    v
Verify Dashboard
```

## Void test

```text
Create Sale
    |
    v
Void Sale
    |
    v
Verify Status
    |
    v
Verify Inventory Restoration
    |
    v
Verify Audit Trail
```

## Mixed order test

```text
Ready-to-Sell Product
+
Made-to-Order Product
```

Verify:

- Inventory deduction
- Kitchen order creation
- One correct customer sale

## Tenant isolation test

```text
Tenant A creates data
        |
        v
Tenant B logs in
        |
        v
Attempt access by UI, URL, and manipulated request
        |
        v
Must fail
```

## Role test

Verify each role cannot access unauthorized routes or actions.

## Security test

Test:

- Unauthorized access
- CSRF
- XSS-safe rendering
- SQL injection resistance
- File validation
- Rate limits
- Session handling
- Tenant isolation

## User acceptance test

Simulate a real day:

```text
Add morning cooked stock
    |
    v
Open business
    |
    v
Record sales
    |
    v
Kitchen prepares orders
    |
    v
Add expense
    |
    v
Record spoilage
    |
    v
Review dashboard
    |
    v
Review reports
```

---

# 35. DEVELOPMENT ORDER

Implement incrementally.

## Stage 1 — Foundation

- Laravel setup
- Environment
- Database
- Authentication
- Base layout
- Tenant architecture
- Roles
- Authorization

## Stage 2 — Tenant Setup

- Tenant creation
- Business settings
- Default settings
- Subscription structure foundation

## Stage 3 — Products

- Categories
- Products
- Images
- Product types
- Product inventory
- Inventory movements

## Stage 4 — Supplies

- Categories
- Supplies
- Supply inventory
- Supply movements

## Stage 5 — POS

- Product grid
- Search
- Cart
- Quantity controls
- Order types
- Payment methods
- Checkout
- Sale recording
- Inventory deduction

## Stage 6 — Sales

- Sales history
- Sale details
- Void sales

## Stage 7 — Kitchen

- Kitchen orders
- Status changes
- Timers
- Polling

## Stage 8 — Expenses

- Categories
- Expense recording
- Receipt uploads

## Stage 9 — Dashboard and Reports

- Dashboard
- Sales reports
- Product reports
- Inventory reports
- Supply reports
- Expense reports

## Stage 10 — Users and Settings

- User management
- Role restrictions
- Payment method settings
- Order type settings
- Timezone
- Kitchen settings

## Stage 11 — SaaS and Super Admin

- Admin authentication/authorization
- Admin dashboard
- Business management
- Plans
- Subscriptions
- Billing
- Promotions
- Notifications

## Stage 12 — QA and Security

- Automated tests
- Manual tests
- Tenant isolation tests
- Role tests
- Responsive tests
- Performance optimization

## Stage 13 — Z.com Deployment

- Production environment
- SSL
- Environment configuration
- Migrations
- Storage
- Cron jobs
- Optimization
- Backup strategy
- Production verification

---

# 36. WORDPRESS-LIKE INSTALLATION AND DEPLOYMENT

The installation experience must follow the same philosophy as the Sukli sari-sari store system.

## Primary requirement

The system must be installable like WordPress.

The customer or deployment administrator should be able to:

```text
1. Upload the application files
2. Open the website URL
3. Automatically enter the installer
4. Enter database details
5. Test the database connection
6. Create the database schema automatically
7. Install default settings and roles automatically
8. Create the first Owner / Super Admin account as required
9. Configure the first business/platform setup
10. Complete installation
11. Be redirected to the application
```

The installer must show real progress and clear step-by-step status.

## Automatic installer flow

Recommended flow:

```text
Uninstalled System Detected
        |
        v
/ install
        |
        v
Step 1: Welcome and System Check
        |
        v
Step 2: Database Configuration
        |
        v
Step 3: Database Connection Test
        |
        v
Step 4: Automatic Database Setup
        |
        +-- Create tables
        +-- Run required migrations
        +-- Install default settings
        +-- Install default roles/permissions
        +-- Install platform defaults
        |
        v
Step 5: Create First Platform / Owner Account
        |
        v
Step 6: Complete Setup
        |
        v
Installation Lock Created
        |
        v
Redirect to Login / Application
```

## Installer requirements

The installer must:

- Detect whether the system is already installed
- Automatically redirect an uninstalled system to the installer
- Test database credentials before continuing
- Create tables automatically
- Install default system data automatically
- Create required roles and permissions automatically
- Create the first authorized account through the setup wizard
- Save generated configuration securely
- Display actual progress, not fake loading messages
- Prevent accidental reinstallation after completion
- Use a secure installation lock
- Show useful error messages when installation fails

## No-manual-configuration requirement

The installation must not require the user to manually:

- Edit `.env`
- Edit `.htaccess`
- Edit `httpd.conf`
- Edit `php.ini`
- Change Apache modules
- Change DocumentRoot
- Move the Laravel `public/` directory manually
- Import SQL files manually
- Run Composer
- Run Artisan commands
- Manually run migrations
- Edit server configuration files

The deployment must work from the uploaded application package with the installer handling normal setup.

## Shared-hosting compatibility

The installation approach must be compatible with:

- Z.com shared hosting
- XAMPP for local installation/testing
- Standard shared hosting environments

Use a root deployment structure that exposes an accessible entry point without requiring the installer/user to reconfigure the web server.

The final production package must be designed so that the application can be uploaded to the hosting account and accessed through the domain in a WordPress-like installation experience.

## Security after installation

After installation:

- Installer routes must be disabled or protected
- Reinstallation must not occur accidentally
- Database credentials/configuration must be protected
- Production debug mode must be disabled
- Sensitive files must not be publicly exposed
- The application must retain normal Laravel security protections

---

# 37. UI SCREEN CHECKLIST

## Public SaaS

- Landing page
- Features section
- Pricing page
- Sign up
- Login
- Forgot password
- Reset password

## Tenant

- Dashboard
- POS
- Checkout
- Products
- Product detail
- Product inventory
- Inventory adjustment
- Supplies
- Supply detail/inventory
- Kitchen
- Sales history
- Sale detail
- Void confirmation
- Expenses
- Reports
- Users
- Business settings
- Payment method settings
- Order type settings
- Timezone settings
- Subscription

## Super Admin

- Admin dashboard
- Businesses list
- Business details
- Plans
- Subscription management
- Billing
- Promotions
- Notifications
- Platform analytics/settings as approved

---

# 38. UI IMPLEMENTATION RULE

Claude must not use the UI illustrations as justification to add functionality that is not in this specification.

The relationship is:

```text
UI illustrations
    =
How the system should look

This master specification
    =
How the system should work
```

When the illustration and written specification appear to conflict, ask for clarification rather than silently inventing behavior.

---

# 39. CLAUDE WORKING METHOD

For each development milestone:

1. State what will be built.
2. List affected files.
3. List migrations/models/services/components involved.
4. Implement the milestone.
5. Explain required installation/setup commands.
6. Explain how to test the feature.
7. Do not move to the next major milestone until the current milestone is reviewed or approved.

Do not refactor unrelated modules without approval.

Do not replace the approved architecture with a different framework.

Do not introduce dependencies incompatible with shared hosting.

---

# 40. FINAL MASTER RULES

> Do not invent major features without approval.

> Do not remove approved functionality.

> Do not compromise tenant security for convenience.

> Do not trust client-provided tenant IDs.

> Do not silently modify inventory.

> Do not allow negative inventory for sellable ready-to-sell products.

> Use database transactions for critical operations.

> Keep the POS fast and simple.

> Keep the interface professional and easy for non-technical users.

> Keep Version 1 compatible with Z.com shared hosting.

> Use a Sukli-style WordPress-like automatic installation wizard with no manual .env, SQL import, Composer, Artisan, or server configuration required from the installer/user.

> Build one responsive web application for desktop and mobile browsers.

> Keep future mobile application plans in mind without implementing the native app or offline sync in the current scope.

> The written specification is the functional authority. The included UI illustrations are the visual authority.

---

# 41. PROJECT COMPLETION ROADMAP

Completed planning:

```text
PHASE 1  System Foundation & Requirements
PHASE 2  Complete UI/UX Design
PHASE 3  Database Architecture
PHASE 4  Multi-Tenant & SaaS Architecture
PHASE 5  User Flows & Functional Specification
PHASE 6  Technical Architecture
PHASE 7  Claude Development Specification
PHASE 7.5 Final Visual UI Reference
```

Current implementation scope:

```text
PHASE 8  Project Initialization & Foundation
PHASE 9  Core Business System Development
PHASE 10 SaaS & Super Admin Development
PHASE 11 Testing, Security & Quality Assurance
PHASE 12 WordPress-Like Installer & Z.com Production Deployment
```

Future roadmap after the current web system is stable:

```text
PHASE 13 Dedicated Mobile Application
PHASE 14 Advanced Features & Integrations
```

The dedicated mobile application is a future project and is not part of the current coding scope.

Version 1 is considered production-ready after successful completion and verification of Phases 8–12.

---

# 42. PACKAGE CONTENTS

This package contains:

```text
README.md / MASTER_INSTRUCTION.md
ui-references/
    01_UI_Master_Reference_Board.png
    02_UI_Complete_System_Board.png
```

Before coding, read this instruction and inspect all included UI reference boards.

END OF MASTER INSTRUCTION.
