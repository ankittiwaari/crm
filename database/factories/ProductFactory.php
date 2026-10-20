<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'name' => Str::title(fake()->unique()->word()).' Software',
            'code' => strtoupper(Str::random(5)),
            'sac_code' => '998314',
            'tax_rate' => '18.00',
            'list_price' => fake()->numberBetween(100, 600) * 10000,
            'default_billing_cycle' => BillingCycle::Yearly,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
