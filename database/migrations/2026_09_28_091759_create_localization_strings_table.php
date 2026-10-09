<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localization_strings', function (Blueprint $table) {
            $table->id();
            $table->string('section', 40);
            $table->string('key', 80);
            $table->text('ru');
            $table->text('kz');
            $table->text('en');
            $table->timestamps();

            $table->unique(['section', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localization_strings');
    }
};
