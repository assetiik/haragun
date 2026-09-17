<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('kind')->default('ticket')->after('menu_category_id');
            $table->boolean('requires_size')->default(false)->after('kind');
            $table->unsignedTinyInteger('size_min')->nullable()->after('requires_size');
            $table->unsignedTinyInteger('size_max')->nullable()->after('size_min');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['kind', 'requires_size', 'size_min', 'size_max']);
        });
    }
};
