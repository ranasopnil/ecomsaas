<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PackageSeeder::class);
        // User::factory(10)->create();

        // Shop staff belong to a shop, so they are created with the shop
        // (see the ProvisionStore service), not seeded on their own.
    }
}
