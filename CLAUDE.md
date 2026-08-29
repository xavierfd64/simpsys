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
- **A public property can't share its name with a route/mount parameter of a
  different type.** `Route::livewire('/businesses/{tenant}', ...)` plus
  `public Tenant $tenant;` plus `mount(string $tenant)` crashes — Livewire
  auto-assigns the raw route value onto any public property matching the
  parameter's name, so a `Tenant`-typed property gets a raw uuid string
  assigned to it and throws a TypeError, independent of what `mount()`
  itself does with the parameter. Fix: name the property something else
  (e.g. `$business`) so it can't collide. See
  `resources/views/pages/admin/businesses/show.blade.php`.
- **Never `whereDate()` a timestamp column against a tenant-local date
  string.** Timestamps (`sales.created_at`, etc.) are stored in UTC;
  `whereDate('created_at', $tenantLocalToday)` compares the *raw UTC* date
  against a *tenant-timezone* date string — wrong for any tenant not in UTC,
  for whichever part of the day local time and UTC land on different
  calendar dates (discovered via a genuinely flaky-looking dashboard test).
  Use `Tenant::localDayBoundsUtc($date)` / `localRangeBoundsUtc($from, $to)`
  (`app/Models/Tenant.php`) to get UTC bounds, then `whereBetween()`. Plain
  `date` columns the user enters directly (`expenses.expense_date`) don't
  have this problem — only timestamp columns being compared against a
  timezone-converted date do.
- **Money columns** (`products.selling_price`, sale/expense amounts, etc.) are
  stored as integer centavos — use `App\Support\Money::format()`/`toCents()`.
  Subscription plan prices are a deliberate exception: whole-peso integers,
  since those are simple admin-set numbers with no centavo input.
- **`User`'s `#[Fillable(['name', 'email', 'password'])]` silently drops
  everything else on mass assignment** — `is_platform_admin`,
  `email_verified_at`, `is_active` are deliberately left out so they can
  never be set from ordinary form/request input, but that also means
  `User::create([...'is_platform_admin' => true])` from *trusted* code
  (found while building the installer's admin-creation step) just discards
  the key with no error and defaults to `false`/`null`. The one exception:
  Laravel's `Seeder` base class unguards models for the duration of a
  seeder run, so `DatabaseSeeder`'s identical-looking `firstOrCreate(...)`
  works fine — this is easy to miss and assume the pattern is safe
  everywhere. Any other trusted call site setting these fields must
  `->forceFill([...])->save()` after the guarded `create()`. Also fixed a
  real latent instance of this: `TenantOnboardingService::register()` was
  silently failing to mark self-registered owners' `email_verified_at`.
- **`composer.json`'s own `"php": "^8.3"` doesn't guarantee the resolved
  dependency tree actually needs only 8.3.** Laravel framework's `composer.json`
  allows either `^7.4.0` or `^8.0.0` for its Symfony components, and an
  unconstrained `composer update` resolved to Symfony 8.1.x — which itself
  requires PHP `>=8.4.1`, silently raising the *real*, Composer-enforced
  floor (`vendor/composer/platform_check.php`) two full minor versions past
  what `composer.json` claims. Found via a real "Composer detected issues
  in your platform… require PHP ">= 8.4.1"" error when a deployment target
  only had PHP 8.3.33. Fixed by explicitly requiring the Symfony components
  at `^7.4` in `composer.json` (a version line Laravel 13 already supports
  and that only needs PHP 8.2+) rather than lowering any stated
  requirement — `composer.json`'s `"php"` constraint was already accurate;
  the *lock file's resolved versions* weren't honoring it. Re-verify this
  any time `composer update` (not `composer install`) touches Symfony
  packages: `php -r '...'` over every `vendor/*/*/composer.json`'s
  `require.php`, or just read the generated
  `vendor/composer/platform_check.php`'s `PHP_VERSION_ID` check — that
  file, not `composer.json`, is the actual enforced floor.
- **Never rely on SQLite to validate a migration that must also run on
  MySQL.** `product_inventory_movements`' composite index on
  `(tenant_id, product_id, created_at)` auto-generates the name
  `product_inventory_movements_tenant_id_product_id_created_at_index` —
  65 characters, one past MySQL's hard 64-character identifier limit.
  SQLite has no such limit, so this passed silently through local dev and
  the entire test suite (every migration in this project had, until this
  point, only ever run on SQLite) and only surfaced as a real
  `SQLSTATE[42000]: ... Identifier name '...' is too long` when the
  installer wizard ran `migrate` against a real MySQL/MariaDB database for
  the first time — genuinely reproduced with a real MariaDB server during
  installer verification, not inferred from reading the code. Fixed by
  giving that index (and `supply_inventory_movements`' matching one, which
  at 63 characters was one column-rename away from the same failure)
  explicit short names. Added
  `MigrationCompatibilityTest::test_no_generated_index_name_exceeds_mysqls_64_character_limit`,
  which inspects `sqlite_master` after the test suite's own migrations run
  — Laravel computes the same index name regardless of driver, so this
  catches the length problem without needing a real MySQL connection in
  CI; confirmed it actually fails against the original migration by
  temporarily reverting the fix and re-running just that test.
- **A Livewire component action that fails mid-install must not surface as
  a bare 500.** `submitDatabase()`/`submitAdmin()` on the installer wizard
  now wrap their actual work in `try`/`catch (\Throwable)`: the connection
  test already confirms the database is reachable before either runs, so a
  failure past that point (the MySQL identifier-length bug above was
  caught exactly this way) is something going wrong while creating the
  schema or account, not a config problem the user can fix by re-entering
  credentials. Each catch calls `report($e)` (so the full exception still
  reaches the server's own logs for real diagnosis) and sets a
  `$setup_error` string shown inline on the current step — "Installation
  could not continue", which step, the exception's own message, and a note
  that retrying is safe since Laravel's migrator only replays migrations
  that didn't already run. Deliberately shows the real exception message
  rather than a generic one: `QueryException`'s message format
  (`SQLSTATE[...]: ... (Connection: ..., SQL: ...)`) doesn't include the
  password, and an installer admin is exactly who should see the real
  reason.
- **A global route-gating middleware must explicitly exempt Livewire's own
  AJAX endpoint, and by header, not by path.** `RedirectIfNotInstalled`
  redirects every request to `/install` until `storage/app/installed.lock`
  exists — but Livewire's update endpoint is registered directly on the
  `web` middleware group (same as any other route in it) at a path Livewire
  4 derives *per-installation* from `APP_KEY`
  (`/livewire-{8-char-hash}/update`, via `EndpointResolver::prefix()`), not
  the fixed `/livewire/update` a naive `$request->is('livewire/*')` check
  would expect. Without an exemption at all, every `wire:click`/
  `wire:submit` on the installer wizard itself — whose initial GET at
  `/install` legitimately passed the gate — hit that hashed endpoint on its
  *next* request, which the middleware saw as "not `/install*`, not
  installed yet" and redirected to `/install`; Livewire's JS can't parse a
  redirect as a component response, so from the browser it just looked like
  the Continue button silently reloaded back to the requirements page
  instead of advancing — reported as exactly that symptom against a real
  deployment. Fixed with `Livewire::isLivewireRequest()` (checks the
  `X-Livewire` header Livewire's own JS client sends, stable regardless of
  the hash) instead of any path pattern. This is the same class of bug as
  the `Livewire::addPersistentMiddleware()` note above — a global/route
  middleware interacting with Livewire's internal endpoint in a
  non-obvious way — and inherits the same testing blind spot:
  `RedirectIfNotInstalled` no-ops entirely under `app()->environment('testing')`
  (needed so the existing suite doesn't get redirected on every request),
  which means exercising its real branches in PHPUnit requires temporarily
  swapping `$this->app['env']` to something else and manually rebinding
  `request()` to a crafted `Illuminate\Http\Request` before each
  `$middleware->handle(...)` call (see
  `InstallerTest::test_livewire_ajax_requests_are_exempt_from_the_install_redirect`)
  — the middleware and `Livewire::isLivewireRequest()` both read the
  container-bound `request()`, not a parameter, so a stale binding silently
  makes assertions pass against the wrong request. Confirmed the actual fix
  against a real running server with Playwright (not just this unit test):
  captured the live POST to the real hashed endpoint, watched it return 200
  instead of a redirect, and watched the wizard's heading genuinely advance
  from "Welcome to BizManager" to "Database Connection".
- **A compatibility gate runs before Composer's autoloader is even
  required.** `bootstrap/preflight.php` (required from `public/index.php`
  before `vendor/autoload.php`) is deliberately zero-dependency plain PHP —
  an incompatible PHP version can fatal the instant a vendor file is merely
  parsed, so this can't reference any Composer/Laravel class. It checks the
  real PHP version floor (read from `composer.json`'s `require.php`, not
  hardcoded, so it can't drift from `InstallerService::requirements()`'s
  own copy of the same read), required extensions, that `vendor/` was
  actually uploaded, and writable storage paths (best-effort auto-creating/
  chmod-ing them first). On failure it renders
  `bootstrap/preflight-error.php` (plain HTML/inline CSS — Blade isn't
  available yet) and exits with 503, instead of the bare, unexplained
  HTTP 500 a PHP-version mismatch or missing `vendor/` would otherwise
  produce. Confirmed against this sandbox's own PHP build, which is
  genuinely missing `bcmath` — real request, real 503, real diagnostic
  listing exactly that.
- **The whole app must run before a database exists.** `/install` (Stage 13)
  has to render — including its own requirements/database-config steps —
  before any DB connection is configured, and before `.env` even exists on
  a fresh deploy. `bootstrap/ensure-env.php` (required from `public/index.php`
  before the framework boots) bootstraps a minimal file-backed `.env`
  (generated `APP_KEY`, file session/cache, sync queue) on the very first
  request if none exists, which is what lets sessions/CSRF/Livewire work at
  all pre-install. `App\Http\Middleware\RedirectIfNotInstalled` (global,
  prepended to the `web` group) gates every other route on
  `storage/app/installed.lock` existing, and is a no-op in the `testing`
  environment specifically so it can't redirect every feature test to
  `/install`.

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
- [x] **Stage 9 — Dashboard and Reports**: real dashboard (today's sales/
      transactions/expenses/net income, 7-day trend, low stock alerts, recent
      transactions), Reports page (Sales/Products/Inventory/Supplies/Expenses
      tabs, date-range filters, payment/category breakdowns). Both owner-only.
      Made login/register redirect role-aware (`User::homeRouteName()`):
      owner → dashboard, cashier → POS, kitchen staff → kitchen board, since
      the dashboard itself is now owner-only rather than open to any tenant
      member.
- [x] **Stage 10 — Users and Settings**: Settings page now tabbed (Business
      Info / Payment Methods / Order Types / Kitchen); Users page (add/edit/
      deactivate/reset password) with plan `user_limit` enforcement — an
      owner can't lock themselves out via deactivate, and can't exceed the
      subscription's seat count.
- [x] **Stage 11 — SaaS and Super Admin**: billing_payments, promotions,
      platform_notifications tables. Admin dashboard (platform counts, recent
      registrations, plan distribution), Business Management (view/suspend/
      reactivate/soft-delete — suspend now actually blocks login, a real gap
      that existed before this stage), Business Detail (owner/members,
      `SubscriptionService` actions: activate/extend/renew/expire/suspend/
      cancel, change plan, record manual payment which renews the period),
      Plans CRUD, Promotions CRUD, Notifications (compose + audience
      targeting, shown as a banner on the matching tenants' dashboards).
      Subscriptions/Billing were folded into the Business Detail page rather
      than separate top-level admin screens (a tenant only ever has one
      active subscription). Platform Settings (spec module 9) was not built
      — no concrete platform-wide config need has come up yet; revisit if
      Stage 13's installer surfaces one.
- [x] **Stage 12 — QA and Security**: systematic review pass rather than new
      features. Found and fixed: (1) a real timezone bug — see
      `Tenant::localDayBoundsUtc()` note above — affecting dashboard/reports/
      sales-history date filtering; (2) an N+1 in the sales list
      (`$sale->tenant->timezone` per row); (3) tightened image upload
      validation to explicit `mimes:jpg,jpeg,png,webp` instead of Livewire's
      generic `image` rule (which also accepts SVG — a known XSS vector via
      inline `<script>`); (4) added rate limiting to registration (login
      already had it); (5) wired `kitchen_enabled` to actually hide the
      Kitchen nav item for owners (kitchen staff always keep it, it's their
      only screen). Verified: no unescaped `{!!` Blade output anywhere, all
      `selectRaw()` calls use fixed literal SQL (no interpolated user input),
      tenant-scoping coverage across every tenant-owned model, and that wide
      tables scroll within their own container rather than the page (checked
      `document.body.scrollWidth` against viewport on a real mobile size).
- [x] **Stage 13 — Z.com Deployment**: WordPress-style `/install` wizard
      (`InstallerService`, `resources/views/pages/install/wizard.blade.php`)
      — requirements check (PHP version/extensions/writable paths), database
      form (raw PDO test against submitted credentials before anything is
      saved, then writes `.env` and hot-swaps Laravel's runtime DB config in
      the same request via `DB::purge()` so migrate+seed can run
      immediately, no reload needed), Super Admin account creation, then
      locks via `storage/app/installed.lock`. No manual `.env` edit, SQL
      import, or Composer/Artisan command required from the installer/user
      at any step. `RedirectIfNotInstalled` middleware sends every request
      to `/install` until that lock exists, and away from `/install` once it
      does (reinstall prevention). Root-level `index.php`/`.htaccess` shim
      added so the app also works when a host's DocumentRoot can't be
      pointed at `public/` (see `DEPLOYMENT.md`). Verified against a real
      fresh state (removed `.env` and the lock file, hit a live
      `php artisan serve`): auto-bootstrapped `.env` correctly, redirected
      to `/install`, requirements step correctly flagged a genuinely missing
      `bcmath` extension in the test environment and blocked continuing,
      and `/install` correctly redirected away once locked.

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
