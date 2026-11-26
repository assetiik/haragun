<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_welcome_espresso')->default(false)->after('counts_toward_coffee_card');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->boolean('apply_welcome_gift')->default(false)->after('payment_method');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_welcome_gift')->default(false)->after('counts_toward_coffee_card');
        });

        Schema::create('welcome_coffee_gifts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('available');
            $table->foreignId('reserved_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('redeemed_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->unique('phone');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welcome_coffee_gifts');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_welcome_gift');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('apply_welcome_gift');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_welcome_espresso');
        });
    }
};
