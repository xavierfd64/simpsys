<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'monthly_price' => 299,
                'yearly_price' => 2990,
                'user_limit' => 2,
                'sort_order' => 1,
                'features' => [
                    'Up to 2 users',
                    'All core features',
                    'Basic reports',
                    'Email support',
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'monthly_price' => 599,
                'yearly_price' => 5990,
                'user_limit' => 5,
                'sort_order' => 2,
                'features' => [
                    'Up to 5 users',
                    'All core features',
                    'Advanced reports',
                    'Priority support',
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'monthly_price' => 999,
                'yearly_price' => 9990,
                'user_limit' => 10,
                'sort_order' => 3,
                'features' => [
                    'Up to 10 users',
                    'All core features',
                    'Advanced reports',
                    'Priority support',
                    'Multi-branch (soon)',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
