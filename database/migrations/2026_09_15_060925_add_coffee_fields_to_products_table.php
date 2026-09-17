<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('description')->nullable()->after('meta');
            $table->string('image_url')->nullable()->after('description');
            $table->string('background_color')->nullable()->after('image_url');
            $table->boolean('is_popular')->default(false)->after('background_color');
            $table->boolean('is_featured')->default(false)->after('is_popular');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'image_url',
                'background_color',
                'is_popular',
                'is_featured',
            ]);
        });
    }
};
