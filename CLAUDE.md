# BizManager — Small Food Business Management SaaS

Multi-tenant Laravel SaaS for small food vendors (fishball/siomai carts, milk tea
stalls, etc.): POS, inventory, kitchen orders, expenses, reports, subscriptions,
and a Super Admin platform. Must deploy on Z.com shared hosting via a
WordPress-style installer.

## Authority

The full specification lives in `spec/Small_Food_Business_SaaS_COMPLETE_PACKAGE/`.
In order of authority:

1. `spec/.../00_DOCUMENTATION/02_CLAUDE_MASTER_INSTRUCTION.md` — functional/technical spec
2. `spec/.../00_DOCUMENTATION/01_COMPLETE_SYSTEM_OVERVIEW.md` — whole-system map
3. `spec/.../01_UI_REFERENCES/*.png` — visual authority (design language, not pixel-exact)

Read the master instruction before changing architecture, data model, or business
rules. Do not invent features, remove approved functionality, or simplify business
rules without approval. Build incrementally, one stage at a time (see Development
Order, section 35 of the master instruction), and don't move to the next stage
without review.

## Tech stack

PHP 8.3+, Laravel, Blade, **Livewire 4 single-file components** (SFC — class +
view in one `.blade.php` file under `resources/views/components/` or
`resources/views/pages/`; emoji filename prefix disabled via
`config/livewire.php` `make_command.emoji = false` for shared-hosting-friendly
filenames), Alpine.js (bundled with Livewire — do not add a separate Alpine
package), Tailwind CSS v4 (CSS-based theme in `resources/css/app.css`, no
`tailwind.config.js`), Lucide icons via `mallardduck/blade-lucide-icons`
(`<x-lucide-{name} />`), MySQL/MariaDB in production, SQLite for local dev.

Full-page Livewire routes use the native `Route::livewire($uri, $componentName)`
macro (no Volt package needed). Layout is picked per-component via
`#[Layout('layouts.app')]` / `layouts.guest` / `layouts.admin`.

Fonts are self-hosted via `@fontsource/inter` (imported in `app.css`) — the
`bunny()` remote-font Vite helper was tried first but requires fetching
`fonts.bunny.net` at build time, which isn't guaranteed to be reachable from
every build/deploy environment. Self-hosting is also simply more
shared-hosting-appropriate.

## Architecture decisions

- **Tenant identity is never trusted from the client.** `App\Services\TenantContext`
  (request-scoped singleton) is populated only by `App\Http\Middleware\IdentifyTenant`
  from the authenticated user's active `TenantMembership` row. Never resolve tenant
  from route params, query strings, or session values the client could tamper with.
- **Two account kinds share one `users` table**: `is_platform_admin` boolean for
  Super Admin accounts (routes under `/admin`, `platform.admin` middleware), and
  tenant members linked via `tenant_memberships` (role: `owner` / `cashier` /
  `kitchen_staff`, enforced with the `role:` middleware alias and
  `TenantContext::hasRole()`). A user is expected to be one or the other, not both.
- **No spatie/laravel-permission.** Roles are a fixed 3-value enum
  (`App\Enums\TenantMembershipRole`) per the spec's explicitly small role set —
  a full permissions package would be unnecessary complexity for V1.
- **UUIDs**: every tenant-facing model uses the `App\Concerns\HasUuid` trait
  (auto-generates on create, used as the route key) for public identifiers and
  future API/sync compatibility, while auto-increment `id` stays internal.
- Business services (e.g. future `SaleService`, `InventoryService`) must be
  reusable from both web/Livewire and the future `/api/v1` — no duplicated
  business logic between them.
- **`BelongsToTenant` trait** (`app/Concerns/BelongsToTenant.php`) puts a global
  `tenant_id` scope + auto-stamp-on-create on tenant-owned models (`Product`,
  `ProductCategory`, `PaymentMethod`, and everything added the same way going
  forward). Defense in depth on top of the `tenant` middleware, not a
  replacement for it.
- **Route-bound tenant models need re-fetching in `mount()`.** Implicit route
  model binding runs in `SubstituteBindings`, part of the `web` middleware
  group, which executes *before* the route's own `tenant` middleware — so
  `BelongsToTenant`'s scope isn't active yet when the route parameter binds.
  Any full-page Livewire component that takes a tenant-owned model as a route
  parameter must re-fetch it inside `mount()` (e.g.
  `Product::findOrFail($product->id)`) rather than trust the bound instance,
  or a crafted URL can load another tenant's record. See
  `resources/views/pages/tenant/products/show.blade.php` for the pattern.
- **Livewire action calls need `Livewire::addPersistentMiddleware()`.** A
  full-page Livewire component's initial GET goes through the route's real
  middleware, but every subsequent `wire:click`/`wire:submit` hits Livewire's
  own `/livewire/update` endpoint, which only replays a hardcoded allowlist of
  framework middleware (auth, `SubstituteBindings`, ...) — custom middleware
  like `tenant`/`role`/`platform.admin` is skipped by default, so
  `TenantContext` is empty on every action call unless registered via
  `Livewire::addPersistentMiddleware([...])` in `AppServiceProvider::boot()`
  (already done). **`Livewire::test()` in PHPUnit bypasses this code path
  entirely** (Livewire's own source explicitly skips middleware replay for
  "fake requests such as a test"), so a passing component test proves nothing
  about this — any component action touching `TenantContext` must also be
  checked against a real running server (`php artisan serve` + a real HTTP
  client or Playwright), not just `php artisan test`.
- **Money columns** (`products.selling_price`, sale/expense amounts, etc.) are
  stored as integer centavos — use `App\Support\Money::format()`/`toCents()`.
  Subscription plan prices are a deliberate exception: whole-peso integers,
  since those are simple admin-set numbers with no centavo input.

## Stage progress

Tracking the master instruction's Development Order (section 35):

- [x] **Stage 1 — Foundation**: Laravel setup, SQLite dev env, base migrations
      (`users`, `tenants`, `tenant_memberships`, `audit_logs`), authentication
      (login/logout/forgot/reset password as Livewire SFCs), tenant context +
      role/platform-admin middleware, shared Tailwind design tokens, guest/app/admin
      layouts (desktop sidebar + mobile drawer/bottom nav), Lucide icons, seeded
      demo accounts (`database/seeders/DatabaseSeeder.php`).
- [x] **Stage 2 — Tenant Setup**: public landing/pricing pages, registration →
      `TenantOnboardingService` (tenant + owner + membership + trial subscription +
      default settings/payment method in one transaction), `subscription_plans`,
      `subscriptions`, `tenant_settings`, `payment_methods` tables, owner-only
      business settings shell (name/timezone).
- [x] **Stage 3 — Products**: product categories, products (ready-to-sell /
      made-to-order), `ProductInventoryService` (row-locked, transactional,
      rejects negative stock) + full movement history, Products CRUD with
      image upload, dedicated Inventory page for stock adjustments/low-stock
      view, `BelongsToTenant` global-scope trait introduced here.
- [x] **Stage 4 — Supplies**: supply categories, supplies (with unit + decimal
      stock), `SupplyInventoryService` (same locked/transactional/non-negative
      pattern as products, decimal quantities), CRUD + adjust + history UI.
      Manual deduction only — no recipe/BOM auto-deduction, per spec V1 scope.
- [x] **Stage 5 — POS**: sales/sale_items/kitchen_orders/kitchen_order_items
      tables, `SaleService` (one DB transaction: sale + line items + inventory
      deduction for ready-to-sell + one kitchen order for made-to-order items,
      full rollback on insufficient stock/payment), POS UI (search, category
      filter, cart with qty controls, DINE-IN/TO-GO tabs, checkout modal,
      mobile bottom-sheet cart). **Found and fixed a critical bug here — see
      the `Livewire::addPersistentMiddleware()` note above** — this also
      means Stage 3/4 save flows were silently broken until this fix and have
      since been re-verified working.
- [x] **Stage 6 — Sales**: sales history (date range + status filters, cashiers
      see only their own sales, owners see all), sale detail with items and
      void info, `VoidSaleService` (restores ready-to-sell inventory, cancels
      a non-completed kitchen order, writes an audit log; owner-only both in
      the UI and re-checked server-side inside the action method).
- [x] **Stage 7 — Kitchen**: `KitchenService` enforces the
      pending→preparing→ready→completed pipeline (cancelled/completed orders
      can't advance further), tabbed board (owner + kitchen staff) with
      per-card live elapsed timers (Alpine, ticks between polls) and an
      opt-out `wire:poll.10s` auto-refresh.
- [x] **Stage 8 — Expenses**: expense categories, expenses (amount/date/
      category/payment method/receipt image/notes), date-range filtered
      history with a running total, owner-only.
- [ ] Stage 9 — Dashboard and Reports
- [ ] Stage 10 — Users and Settings
- [ ] Stage 11 — SaaS and Super Admin
- [ ] Stage 12 — QA and Security
- [ ] Stage 13 — Z.com Deployment (WordPress-style installer)

## Demo accounts (seeded, password `password`)

- `admin@bizmanager.test` — platform (Super) Admin
- `owner@bizmanager.test` — Owner of "Juan's Fishball Station"
- `cashier@bizmanager.test` — Cashier of the same tenant
- `kitchen@bizmanager.test` — Kitchen Staff of the same tenant

## Local development

```sh
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm run build   # or: npm run dev
php artisan serve
```
