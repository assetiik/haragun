<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('city_id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('phone');
        });
    }
};
