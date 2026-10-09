<?php

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\Establishment;
use App\Models\Product;
use App\Support\Slugger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('id')
                ->constrained('brands')
                ->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->change();
        });

        $this->ensureBrandsForBrandlessCafes();
        $this->collapseCafeMenusOntoBrands();
    }

    public function down(): void
    {
        $products = DB::table('products')
            ->whereNull('establishment_id')
            ->whereNotNull('brand_id')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $establishmentId = DB::table('establishments')
                ->where('brand_id', $product->brand_id)
                ->orderBy('id')
                ->value('id');

            if ($establishmentId === null) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'establishment_id' => $establishmentId,
                'brand_id' => null,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable(false)->change();
        });
    }

    private function ensureBrandsForBrandlessCafes(): void
    {
        $brandless = Establishment::query()
            ->where('type', EstablishmentType::Coffee)
            ->whereNull('brand_id')
            ->orderBy('id')
            ->get();

        foreach ($brandless->groupBy('name') as $name => $points) {
            $first = $points->first();
            $cityId = $first->city_id;

            $brand = Brand::query()->firstOrCreate(
                [
                    'city_id' => $cityId,
                    'name' => $name,
                    'type' => EstablishmentType::Coffee,
                ],
                [
                    'slug' => Slugger::unique(
                        $name,
                        Brand::class,
                        ['city_id' => $cityId],
                        null,
                        'brand',
                    ),
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            Establishment::query()
                ->whereIn('id', $points->pluck('id'))
                ->update(['brand_id' => $brand->id]);
        }
    }

    private function collapseCafeMenusOntoBrands(): void
    {
        $brands = Brand::query()
            ->where('type', EstablishmentType::Coffee)
            ->with(['establishments' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();

        foreach ($brands as $brand) {
            $establishmentIds = $brand->establishments->pluck('id');

            if ($establishmentIds->isEmpty()) {
                continue;
            }

            $products = Product::query()
                ->with(['sizes', 'modifiers'])
                ->whereIn('establishment_id', $establishmentIds)
                ->where('kind', ProductKind::Food)
                ->whereHas(
                    'menuCategory',
                    fn ($query) => $query->where('type', EstablishmentType::Coffee),
                )
                ->orderBy('id')
                ->get()
                ->groupBy('name');

            foreach ($products as $group) {
                /** @var Product $keeper */
                $keeper = $group->first();
                $keeper->forceFill([
                    'brand_id' => $brand->id,
                    'establishment_id' => null,
                ])->save();

                foreach ($group->skip(1) as $duplicate) {
                    $this->remapDuplicateProduct($keeper, $duplicate);
                    $duplicate->modifiers()->detach();
                    $duplicate->sizes()->delete();
                    $duplicate->delete();
                }
            }
        }
    }

    private function remapDuplicateProduct(Product $keeper, Product $duplicate): void
    {
        $sizeMap = [];

        foreach ($duplicate->sizes as $size) {
            $keeperSize = $keeper->sizes->firstWhere('code', $size->code);

            if ($keeperSize === null) {
                $keeperSize = $keeper->sizes()->create([
                    'code' => $size->code,
                    'label' => $size->label,
                    'volume_ml' => $size->volume_ml,
                    'price' => $size->price,
                    'is_default' => $size->is_default,
                    'sort_order' => $size->sort_order,
                ]);
                $keeper->unsetRelation('sizes');
                $keeper->load('sizes');
            }

            $sizeMap[$size->id] = $keeperSize->id;
        }

        foreach ($duplicate->modifiers as $modifier) {
            if (! $keeper->modifiers->contains('id', $modifier->id)) {
                $keeper->modifiers()->attach($modifier->id, [
                    'sort_order' => $modifier->pivot->sort_order ?? 0,
                ]);
            }
        }

        $keeper->unsetRelation('modifiers');

        foreach (DB::table('cart_items')->where('product_id', $duplicate->id)->get() as $item) {
            DB::table('cart_items')->where('id', $item->id)->update([
                'product_id' => $keeper->id,
                'product_size_id' => $item->product_size_id !== null
                    ? ($sizeMap[$item->product_size_id] ?? null)
                    : null,
            ]);
        }

        DB::table('order_items')
            ->where('product_id', $duplicate->id)
            ->update(['product_id' => $keeper->id]);
    }
};
