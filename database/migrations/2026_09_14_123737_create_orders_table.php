<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status');
            $table->unsignedInteger('number')->unique();
            $table->string('code')->unique();
            $table->date('session_date')->nullable();
            $table->time('session_time')->nullable();
            $table->time('session_ends_at')->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->string('summary_title')->nullable();
            $table->string('summary_short')->nullable();
            $table->string('payment_label')->nullable();
            $table->unsignedInteger('points_to_earn')->default(0);
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index(['user_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
