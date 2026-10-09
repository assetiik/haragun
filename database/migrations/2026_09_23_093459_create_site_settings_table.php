<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('about_title')->default('О приложении');
            $table->string('about_version')->nullable();
            $table->text('about_text')->nullable();
            $table->string('contacts_title')->default('Контакты');
            $table->string('contacts_phone')->nullable();
            $table->string('contacts_phone_raw')->nullable();
            $table->string('contacts_email')->nullable();
            $table->string('contacts_address')->nullable();
            $table->string('contacts_hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
