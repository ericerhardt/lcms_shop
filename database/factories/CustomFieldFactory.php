<?php

namespace Database\Factories;

use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'key' => Str::slug($name, '_'),
            'type' => 'text',
            'options' => null,
            'position' => 0,
        ];
    }

    /**
     * @param  array<int, string>  $options
     */
    public function select(array $options): static
    {
        return $this->state(['type' => 'select', 'options' => $options]);
    }
}
