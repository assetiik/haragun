<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('dss_order_id')->nullable()->after('code');
            $table->unsignedBigInteger('dss_day_id')->nullable()->after('dss_order_id');
            $table->unsignedBigInteger('dss_slot_id')->nullable()->after('dss_day_id');
            $table->unique('dss_order_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('barcodes')->nullable()->after('sizes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['dss_order_id']);
            $table->dropColumn(['dss_order_id', 'dss_day_id', 'dss_slot_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('barcodes');
        });
    }
};
