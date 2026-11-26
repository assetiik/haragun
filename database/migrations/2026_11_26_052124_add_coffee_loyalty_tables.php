<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('counts_toward_coffee_card')->default(false)->after('is_featured');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('counts_toward_coffee_card')->default(false)->after('is_discount');
        });

        Schema::create('coffee_loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('paid_stamps')->default(0);
            $table->timestamps();
        });

        Schema::create('coffee_loyalty_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedSmallInteger('stamps');
            $table->unsignedTinyInteger('before_paid_stamps');
            $table->unsignedTinyInteger('after_paid_stamps');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->timestamps();

            $table->unique(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coffee_loyalty_events');
        Schema::dropIfExists('coffee_loyalty_cards');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('counts_toward_coffee_card');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('counts_toward_coffee_card');
        });
    }
};
