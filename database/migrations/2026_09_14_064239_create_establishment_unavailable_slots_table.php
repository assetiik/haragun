<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishment_unavailable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('time');
            $table->timestamps();

            $table->unique(['establishment_id', 'date', 'time'], 'establishment_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establishment_unavailable_slots');
    }
};
