<?php

namespace Database\Factories;

use App\Enums\MsmeCategory;
use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'address_line' => fake()->streetAddress(),
            'city' => 'Bengaluru',
            'state_code' => '29',
            'pincode' => '560011',
            'email' => fake()->companyEmail(),
            'phone' => '+919845012345',
            'gstin' => '29AAGCB7383J1Z4',
            'pan' => 'AAGCB7383J',
            'udyam_number' => 'UDYAM-KA-03-0012345',
            'msme_category' => MsmeCategory::Micro,
            'bank_account_number' => '123456789012',
            'bank_ifsc' => 'HDFC0001234',
            'upi_id' => 'company@hdfcbank',
        ];
    }
}
