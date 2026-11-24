<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrier' => fake()->randomElement(['UPS', 'FedEx', 'USPS']),
            'timeframe' => fake()->randomElement(['1-2 business days', '3-5 business days', '5-7 business days']),
            'price_cents' => fake()->numberBetween(500, 5000),
            'is_active' => true,
            'position' => 0,
        ];
    }
}
