<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weekend_price')->nullable()->after('price');
        });

        DB::table('products')
            ->whereIn('kind', ['ticket', 'rental_skates', 'rental_helmet'])
            ->update(['weekend_price' => DB::raw('price')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weekend_price');
        });
    }
};
