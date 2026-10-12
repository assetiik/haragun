<?php

use App\Models\MenuCategory;
use App\Support\IceRentalCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MenuCategory::query()
            ->where('slug', 'ice_rental')
            ->each(fn (MenuCategory $category) => IceRentalCatalog::sync($category));
    }

    public function down(): void
    {
        //
    }
};
