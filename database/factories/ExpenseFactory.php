<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state: ₹1,180 of hosting, including ₹180 GST.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spent_on' => today(),
            'category' => ExpenseCategory::Hosting,
            'vendor' => 'DigitalOcean',
            'description' => 'Server',
            'amount' => 118_000,
            'gst_amount' => 18_000,
        ];
    }
}
