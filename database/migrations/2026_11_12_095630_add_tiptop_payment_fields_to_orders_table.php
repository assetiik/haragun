<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('paid')->after('payment_label');
            $table->string('payment_provider')->nullable()->after('payment_status');
            $table->unsignedBigInteger('tiptop_transaction_id')->nullable()->after('payment_provider');
            $table->unsignedInteger('payment_refunded_total')->default(0)->after('tiptop_transaction_id');

            $table->index('tiptop_transaction_id');
            $table->index(['payment_status', 'payment_provider']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tiptop_transaction_id']);
            $table->dropIndex(['payment_status', 'payment_provider']);
            $table->dropColumn([
                'payment_status',
                'payment_provider',
                'tiptop_transaction_id',
                'payment_refunded_total',
            ]);
        });
    }
};
