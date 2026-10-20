<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => ClientType::School,
            'status' => ClientStatus::Active,
            'phone' => '+9198450'.fake()->numerify('#####'),
            'billing_address' => fake()->streetAddress(),
            'city' => 'Bengaluru',
            'state_code' => '29',
            'pincode' => '560011',
        ];
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ClientStatus::Lead]);
    }

    public function pharmacy(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ClientType::Pharmacy]);
    }
}
