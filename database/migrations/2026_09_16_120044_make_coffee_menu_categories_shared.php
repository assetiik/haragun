<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_categories', 'type')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->string('type')->nullable()->after('establishment_id');
            });
        }

        $establishmentTypes = DB::table('establishments')->pluck('type', 'id');

        foreach (DB::table('menu_categories')->orderBy('id')->get() as $category) {
            if (filled($category->type)) {
                continue;
            }

            DB::table('menu_categories')->where('id', $category->id)->update([
                'type' => $establishmentTypes[$category->establishment_id] ?? 'coffee',
            ]);
        }

        $canonicalBySlug = [];

        foreach (DB::table('menu_categories')->where('type', 'coffee')->orderBy('id')->get() as $category) {
            if (! isset($canonicalBySlug[$category->slug])) {
                $canonicalBySlug[$category->slug] = $category->id;

                continue;
            }

            DB::table('products')
                ->where('menu_category_id', $category->id)
                ->update(['menu_category_id' => $canonicalBySlug[$category->slug]]);

            DB::table('menu_categories')->where('id', $category->id)->delete();
        }

        $this->dropEstablishmentForeign();

        if (Schema::hasIndex('menu_categories', ['establishment_id', 'slug'], 'unique')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->dropUnique(['establishment_id', 'slug']);
            });
        }

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('establishment_id')->nullable()->change();
        });

        if (! $this->hasEstablishmentForeign()) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->foreign('establishment_id')->references('id')->on('establishments')->cascadeOnDelete();
            });
        }

        DB::table('menu_categories')->where('type', 'coffee')->update([
            'establishment_id' => null,
        ]);

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });

        if (! Schema::hasIndex('menu_categories', ['type', 'slug'], 'unique')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->unique(['type', 'slug']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('menu_categories', ['type', 'slug'], 'unique')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->dropUnique(['type', 'slug']);
            });
        }

        $firstCoffeeId = DB::table('establishments')->where('type', 'coffee')->orderBy('id')->value('id');

        if ($firstCoffeeId !== null) {
            DB::table('menu_categories')->where('type', 'coffee')->update([
                'establishment_id' => $firstCoffeeId,
            ]);
        }

        $this->dropEstablishmentForeign();

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('establishment_id')->nullable(false)->change();
            $table->dropColumn('type');
            $table->unique(['establishment_id', 'slug']);
            $table->foreign('establishment_id')->references('id')->on('establishments')->cascadeOnDelete();
        });
    }

    /**
     * @return list<array{name: string, columns: list<string>}>
     */
    private function establishmentForeignKeys(): array
    {
        return array_values(array_filter(
            Schema::getForeignKeys('menu_categories'),
            fn (array $foreignKey): bool => in_array('establishment_id', $foreignKey['columns'], true),
        ));
    }

    private function hasEstablishmentForeign(): bool
    {
        return $this->establishmentForeignKeys() !== [];
    }

    private function dropEstablishmentForeign(): void
    {
        if (! $this->hasEstablishmentForeign()) {
            return;
        }

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropForeign(['establishment_id']);
        });
    }
};
