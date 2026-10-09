<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->foreignId('modifier_category_id')
                ->nullable()
                ->after('id')
                ->constrained('modifier_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modifier_category_id');
        });

        Schema::dropIfExists('modifier_categories');
    }
};
