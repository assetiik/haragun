<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->uuid('iiko_organization_id')->nullable()->after('sort_order');
            $table->uuid('iiko_terminal_group_id')->nullable()->after('iiko_organization_id');
            $table->uuid('iiko_table_id')->nullable()->after('iiko_terminal_group_id');
            $table->uuid('iiko_payment_type_id')->nullable()->after('iiko_table_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->uuid('iiko_product_id')->nullable()->after('sort_order');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('iiko_order_id')->nullable()->after('dss_slot_id');
            $table->uuid('iiko_correlation_id')->nullable()->after('iiko_order_id');
            $table->unique('iiko_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['iiko_order_id']);
            $table->dropColumn(['iiko_order_id', 'iiko_correlation_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('iiko_product_id');
        });

        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn([
                'iiko_organization_id',
                'iiko_terminal_group_id',
                'iiko_table_id',
                'iiko_payment_type_id',
            ]);
        });
    }
};
