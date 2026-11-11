<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('ticket_type')->nullable()->after('kind');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('rink_order_id')->nullable()->after('dss_slot_id');
            $table->unsignedBigInteger('rink_session_id')->nullable()->after('rink_order_id');
            $table->time('session_entry_opens_at')->nullable()->after('session_ends_at');
            $table->unique('rink_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['rink_order_id']);
            $table->dropColumn(['rink_order_id', 'rink_session_id', 'session_entry_opens_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ticket_type');
        });
    }
};
