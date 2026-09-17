<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modifier_product')) {
            Schema::create('modifier_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['modifier_id', 'product_id']);
            });
        }

        $now = now();

        if (Schema::hasTable('product_modifier_group') && Schema::hasColumn('modifiers', 'modifier_group_id')) {
            foreach (DB::table('product_modifier_group')->get() as $link) {
                $modifiers = DB::table('modifiers')
                    ->where('modifier_group_id', $link->modifier_group_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                foreach ($modifiers as $index => $modifier) {
                    $exists = DB::table('modifier_product')
                        ->where('modifier_id', $modifier->id)
                        ->where('product_id', $link->product_id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('modifier_product')->insert([
                        'modifier_id' => $modifier->id,
                        'product_id' => $link->product_id,
                        'sort_order' => $index + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $canonicalByKey = [];

        foreach (DB::table('modifiers')->orderBy('id')->get() as $modifier) {
            $key = mb_strtolower(trim((string) $modifier->name)).'|'.$modifier->price;

            if (! isset($canonicalByKey[$key])) {
                $canonicalByKey[$key] = $modifier->id;

                continue;
            }

            $canonicalId = $canonicalByKey[$key];

            foreach (DB::table('modifier_product')->where('modifier_id', $modifier->id)->get() as $pivot) {
                $exists = DB::table('modifier_product')
                    ->where('modifier_id', $canonicalId)
                    ->where('product_id', $pivot->product_id)
                    ->exists();

                if (! $exists) {
                    DB::table('modifier_product')->where('id', $pivot->id)->update([
                        'modifier_id' => $canonicalId,
                    ]);

                    continue;
                }

                DB::table('modifier_product')->where('id', $pivot->id)->delete();
            }

            DB::table('modifiers')->where('id', $modifier->id)->delete();
        }

        if (Schema::hasColumn('modifiers', 'modifier_group_id')) {
            Schema::table('modifiers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('modifier_group_id');
            });
        }

        Schema::dropIfExists('product_modifier_group');
        Schema::dropIfExists('modifier_groups');
    }

    public function down(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['establishment_id', 'slug']);
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->foreignId('modifier_group_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::create('product_modifier_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'modifier_group_id']);
        });

        Schema::dropIfExists('modifier_product');
    }
};
