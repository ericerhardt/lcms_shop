<?php

namespace Database\Factories;

use App\Enums\PackagingType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('LBL-####-??')),
            'name' => fake()->words(3, true).' labels',
            'description' => fake()->sentence(),
            'packaging_type' => fake()->randomElement(PackagingType::cases()),
            'quantity_per_package' => fake()->randomElement([100, 250, 500, 1000]),
            'price_cents' => fake()->numberBetween(1500, 25000),
            'stock_quantity' => fake()->numberBetween(5, 200),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }
}
