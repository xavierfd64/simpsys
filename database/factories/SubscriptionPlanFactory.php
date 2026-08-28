<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'monthly_price' => fake()->numberBetween(199, 999),
            'yearly_price' => fake()->numberBetween(1990, 9990),
            'user_limit' => fake()->numberBetween(1, 10),
            'features' => ['All core features'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
