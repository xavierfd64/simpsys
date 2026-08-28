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

## Stage progress

Tracking the master instruction's Development Order (section 35):

- [x] **Stage 1 — Foundation**: Laravel setup, SQLite dev env, base migrations
      (`users`, `tenants`, `tenant_memberships`, `audit_logs`), authentication
      (login/logout/forgot/reset password as Livewire SFCs), tenant context +
      role/platform-admin middleware, shared Tailwind design tokens, guest/app/admin
      layouts (desktop sidebar + mobile drawer/bottom nav), Lucide icons, seeded
      demo accounts (`database/seeders/DatabaseSeeder.php`).
- [ ] Stage 2 — Tenant Setup (registration/onboarding, business settings shell,
      subscription plan tables)
- [ ] Stage 3 — Products
- [ ] Stage 4 — Supplies
- [ ] Stage 5 — POS
- [ ] Stage 6 — Sales
- [ ] Stage 7 — Kitchen
- [ ] Stage 8 — Expenses
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
npm run build   # or: npm run dev
php artisan serve
```
