<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('name');
            $table->string('image_url')->nullable()->after('accent_color');
            $table->json('images')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'image_url', 'images']);
        });
    }
};
