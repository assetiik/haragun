<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('rink_external_order_id')->nullable()->after('rink_order_id');
            $table->unique('rink_external_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['rink_external_order_id']);
            $table->dropColumn('rink_external_order_id');
        });
    }
};
