<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => ProductType::ReadyToSell,
            'selling_price' => fake()->numberBetween(1000, 10000),
            'low_stock_threshold' => 10,
            'is_active' => true,
        ];
    }

    public function madeToOrder(): static
    {
        return $this->state(['type' => ProductType::MadeToOrder]);
    }
}
