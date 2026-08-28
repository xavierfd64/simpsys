<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\ProductInventoryMovementType;
use App\Enums\ProductType;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);

        User::query()->firstOrCreate(
            ['email' => 'admin@bizmanager.test'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'juans-fishball-station'],
            [
                'name' => "Juan's Fishball Station",
                'timezone' => 'Asia/Manila',
                'status' => TenantStatus::Active,
            ]
        );

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@bizmanager.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $cashier = User::query()->firstOrCreate(
            ['email' => 'cashier@bizmanager.test'],
            [
                'name' => 'Maria Santos',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $kitchenStaff = User::query()->firstOrCreate(
            ['email' => 'kitchen@bizmanager.test'],
            [
                'name' => 'Pedro Reyes',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $tenant->memberships()->firstOrCreate(
            ['user_id' => $owner->id],
            ['role' => TenantMembershipRole::Owner]
        );

        $tenant->memberships()->firstOrCreate(
            ['user_id' => $cashier->id],
            ['role' => TenantMembershipRole::Cashier]
        );

        $tenant->memberships()->firstOrCreate(
            ['user_id' => $kitchenStaff->id],
            ['role' => TenantMembershipRole::KitchenStaff]
        );

        $tenant->settings()->firstOrCreate([]);

        $tenant->paymentMethods()->firstOrCreate(
            ['name' => 'Cash'],
            ['is_enabled' => true, 'sort_order' => 0]
        );
        $tenant->paymentMethods()->firstOrCreate(
            ['name' => 'GCash'],
            ['is_enabled' => true, 'sort_order' => 1]
        );

        $businessPlan = SubscriptionPlan::query()->where('slug', 'business')->first();

        $tenant->subscriptions()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'subscription_plan_id' => $businessPlan->id,
                'billing_period' => BillingPeriod::Monthly,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ]
        );

        $foodCategory = $tenant->productCategories()->firstOrCreate(['name' => 'Food']);
        $drinksCategory = $tenant->productCategories()->firstOrCreate(['name' => 'Drinks']);

        $products = [
            ['name' => 'Fishball', 'category_id' => $foodCategory->id, 'type' => ProductType::ReadyToSell, 'selling_price' => 2000, 'stock' => 120],
            ['name' => 'Siomai', 'category_id' => $foodCategory->id, 'type' => ProductType::ReadyToSell, 'selling_price' => 3000, 'stock' => 8],
            ['name' => 'Kwek-kwek', 'category_id' => $foodCategory->id, 'type' => ProductType::ReadyToSell, 'selling_price' => 1500, 'stock' => 45],
            ['name' => 'Milk Tea', 'category_id' => $drinksCategory->id, 'type' => ProductType::MadeToOrder, 'selling_price' => 6000, 'stock' => 0],
        ];

        $inventoryService = app(ProductInventoryService::class);

        foreach ($products as $data) {
            $product = $tenant->products()->firstOrCreate(
                ['name' => $data['name']],
                [
                    'product_category_id' => $data['category_id'],
                    'type' => $data['type'],
                    'selling_price' => $data['selling_price'],
                    'low_stock_threshold' => 10,
                    'is_active' => true,
                ]
            );

            if ($data['type'] === ProductType::ReadyToSell && $product->currentStock() === 0 && $data['stock'] > 0) {
                $inventoryService->adjust($product, $data['stock'], ProductInventoryMovementType::StockAdded, 'Initial stock');
            }
        }
    }
}
