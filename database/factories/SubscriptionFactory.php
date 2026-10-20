<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state: a flat ₹48,000 yearly deal, half-way through its period.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = today()->subMonths(6);

        return [
            'client_id' => Client::factory(),
            'product_id' => Product::factory(),
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => BillingCycle::Yearly,
            'pricing_model' => PricingModel::Flat,
            'rate' => 4_800_000,
            'quantity' => 1,
            'start_date' => $start,
            'current_period_start' => $start,
            'current_period_end' => BillingCycle::Yearly->periodEnd($start),
        ];
    }

    /**
     * The current period ends the given number of days from today.
     */
    public function renewingIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'current_period_start' => today()->addDays($days)->subYear()->addDay(),
            'current_period_end' => today()->addDays($days),
        ]);
    }

    public function perUnit(int $rate, int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_model' => PricingModel::PerUnit,
            'rate' => $rate,
            'quantity' => $quantity,
        ]);
    }
}
