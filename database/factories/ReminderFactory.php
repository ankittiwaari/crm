<?php

namespace Database\Factories;

use App\Enums\ReminderType;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state: a general reminder due today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ReminderType::General,
            'title' => fake()->sentence(4),
            'due_on' => today(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['completed_at' => now()]);
    }
}
