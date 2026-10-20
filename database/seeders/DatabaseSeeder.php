<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Create user accounts with `php artisan app:create-user`.
     */
    public function run(): void
    {
        $this->call(ProductSeeder::class);
    }
}
