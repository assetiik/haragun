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
            HomePageSeeder::class,
            EstablishmentCatalogSeeder::class,
            CoffeeMenuSeeder::class,
            AdminSeeder::class,
        ]);

        User::factory()->create([
            'phone' => '77770000000',
            'name' => 'Test User',
        ]);

        $this->call(OrderSeeder::class);
    }
}
