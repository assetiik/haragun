<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_price');
            $table->integer('amount');
            $table->json('sizes')->nullable();
            $table->string('icon_key')->nullable();
            $table->boolean('is_discount')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
