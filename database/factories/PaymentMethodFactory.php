<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
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
            'name' => 'Cash',
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
