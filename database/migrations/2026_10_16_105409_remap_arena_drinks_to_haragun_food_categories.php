<?php

use App\Support\ArenaDrinkCategoryRemapper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ArenaDrinkCategoryRemapper::remap();
    }

    public function down(): void
    {
        //
    }
};
