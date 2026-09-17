<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('header_title')->nullable()->after('section_subtitle');
            $table->string('header_subtitle')->nullable()->after('header_title');
            $table->string('header_color')->nullable()->after('header_subtitle');
            $table->unsignedSmallInteger('prep_minutes')->nullable()->after('header_color');
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn([
                'header_title',
                'header_subtitle',
                'header_color',
                'prep_minutes',
            ]);
        });
    }
};
