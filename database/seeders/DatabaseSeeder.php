<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
        ]);

        // Demo catalog seeders are manual-only — never auto-run on production deploys.
        if (! app()->isLocal()) {
            return;
        }

        $this->call([
            HomePageSeeder::class,
            EstablishmentCatalogSeeder::class,
            CoffeeMenuSeeder::class,
        ]);

        User::factory()->create([
            'phone' => '77770000000',
            'name' => 'Test User',
        ]);

        $this->call(OrderSeeder::class);
    }
}
