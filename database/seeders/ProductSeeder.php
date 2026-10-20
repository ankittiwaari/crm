<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed the products you sell. Prices are negotiated per client, so no list price is set.
     */
    public function run(): void
    {
        $products = [
            ['code' => 'SERP', 'name' => 'School ERP', 'unit_label' => 'per student'],
            ['code' => 'PHM', 'name' => 'Pharmacy Management', 'unit_label' => 'per branch'],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['code' => $product['code']], [
                ...$product,
                'default_billing_cycle' => BillingCycle::Yearly,
            ]);
        }
    }
}
