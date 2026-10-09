<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('banners') && Schema::hasColumn('banners', 'placement')) {
            $promos = DB::table('banners')->where('placement', 'promo')->get();

            foreach ($promos as $promo) {
                DB::table('promo_tiles')->insert([
                    'city_id' => $promo->city_id,
                    'title' => $promo->title,
                    'image_url' => $promo->image_url,
                    'is_active' => $promo->is_active,
                    'sort_order' => $promo->sort_order,
                    'created_at' => $promo->created_at,
                    'updated_at' => $promo->updated_at,
                ]);
            }

            DB::table('banners')->where('placement', 'promo')->delete();
            DB::table('banners')->where(function ($query): void {
                $query->whereNull('placement')->orWhere('placement', '!=', 'hero');
            })->update(['placement' => 'hero']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_tiles');
    }
};
