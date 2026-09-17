<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->boolean('parking_is_free')->default(false)->after('closes_at');
            $table->unsignedSmallInteger('session_duration_minutes')->default(60)->after('parking_is_free');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(120)->after('session_duration_minutes');
            $table->integer('ice_temperature')->nullable()->after('slot_interval_minutes');
            $table->string('ice_note')->nullable()->after('ice_temperature');
            $table->string('header_color')->default('#6B4EFF')->after('ice_note');
            $table->unsignedTinyInteger('booking_days_ahead')->default(7)->after('header_color');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->dropColumn([
                'parking_is_free',
                'session_duration_minutes',
                'slot_interval_minutes',
                'ice_temperature',
                'ice_note',
                'header_color',
                'booking_days_ahead',
            ]);
        });
    }
};
