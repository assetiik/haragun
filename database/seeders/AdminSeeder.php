<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->where('email', 'admin@haragun.kz')->delete();

        Admin::query()->updateOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'Admin',
                'password' => 'admin',
            ],
        );
    }
}
