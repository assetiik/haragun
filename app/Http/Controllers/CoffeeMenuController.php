<?php

namespace App\Http\Controllers;

use App\Enums\EstablishmentType;
use App\Http\Requests\QuoteCoffeeItemRequest;
use App\Http\Resources\CoffeeProductCardResource;
use App\Http\Resources\CoffeeProductDetailResource;
use App\Models\Cart;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Product;
use App\Services\CartService;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Coffee', weight: 5)]
class CoffeeMenuController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request, Establishment $establishment): JsonResponse
    {
        $this->ensureCoffee($establishment);

        $validated = $request->validate([
            'category' => ['nullable', 'string'],
        ]);

        $categories = MenuCategory::query()
            ->coffee()
            ->active()
            ->ordered()
            ->get();

        $chips = collect([
            [
                'slug' => 'popular',
                'name' => 'Популярные',
            ],
        ])->concat($categories->map(fn (MenuCategory $category) => [
            'slug' => $category->slug,
            'name' => $category->name,
        ]))->values();

        $selectedSlug = $validated['category'] ?? 'popular';
        if (! $chips->contains(fn (array $chip) => $chip['slug'] === $selectedSlug)) {
            $selectedSlug = 'popular';
        }

        $productsQuery = Product::query()
            ->with('menuCategory')
            ->where('establishment_id', $establishment->id)
            ->coffeeMenu()
            ->active()
            ->ordered();

        if ($selectedSlug === 'popular') {
            $productsQuery->popular();
        } else {
            $category = $categories->firstWhere('slug', $selectedSlug);
            $productsQuery->where('menu_category_id', $category?->id);
        }

        $products = $productsQuery->get();

        $featured = Product::query()
            ->where('establishment_id', $establishment->id)
            ->coffeeMenu()
            ->active()
            ->featured()
            ->ordered()
            ->first()
            ?? $products->first();

        $cartSummary = [
            'items_count' => 0,
            'total' => 0,
            'total_label' => Money::format(0),
            'label' => 'Корзина · 0 шт',
        ];
        $activeOrder = null;

        if ($request->user('sanctum') !== null) {
            $user = $request->user('sanctum');
            $cart = Cart::query()
                ->with('items')
                ->where('user_id', $user->id)
                ->where('establishment_id', $establishment->id)
                ->first();

            $cartSummary = $this->carts->summary($cart);
            $activeOrder = $this->carts->activeOrderBanner($user, $establishment);
        }

        return response()->json([
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'address' => $establishment->address,
                'brand_id' => $establishment->brand_id,
            ],
            'header' => [
                'address' => $establishment->address,
            ],
            'categories' => $chips,
            'selected_category' => $selectedSlug,
            'featured' => $featured ? new CoffeeProductCardResource($featured) : null,
            'products' => CoffeeProductCardResource::collection($products),
            'cart' => $cartSummary,
            'active_order' => $activeOrder,
        ]);
    }

    public function show(Establishment $establishment, Product $product): CoffeeProductDetailResource
    {
        $this->ensureCoffee($establishment);
        abort_unless(
            $product->establishment_id === $establishment->id
                && $product->is_active
                && $product->menuCategory?->type === EstablishmentType::Coffee,
            404
        );

        $product->load([
            'sizes' => fn ($query) => $query->ordered(),
            'modifiers' => fn ($query) => $query->active(),
        ]);

        return new CoffeeProductDetailResource($product);
    }

    public function quoteItem(QuoteCoffeeItemRequest $request, Establishment $establishment): JsonResponse
    {
        $this->ensureCoffee($establishment);

        $validated = $request->validated();

        $product = Product::query()
            ->with(['sizes', 'modifiers' => fn ($query) => $query->active()])
            ->where('establishment_id', $establishment->id)
            ->coffeeMenu()
            ->active()
            ->findOrFail($validated['product_id']);

        $size = null;
        if (isset($validated['size_id'])) {
            $size = $product->sizes->firstWhere('id', (int) $validated['size_id']);
            if ($size === null) {
                throw ValidationException::withMessages([
                    'size_id' => ['Выбранный размер недоступен.'],
                ]);
            }
        } else {
            $size = $product->defaultSize();
        }

        $unitPrice = $size?->price ?? $product->price;
        $modifiers = collect();

        foreach ($validated['modifier_ids'] ?? [] as $modifierId) {
            $modifier = Modifier::query()
                ->whereKey($modifierId)
                ->active()
                ->first();

            if ($modifier === null) {
                throw ValidationException::withMessages([
                    'modifier_ids' => ["Добавка #{$modifierId} недоступна."],
                ]);
            }

            if (! $product->modifiers->contains('id', $modifier->id)) {
                throw ValidationException::withMessages([
                    'modifier_ids' => ["Добавка {$modifier->name} не подходит к этому напитку."],
                ]);
            }

            $modifiers->push($modifier);
            $unitPrice += $modifier->price;
        }

        $quantity = (int) ($validated['quantity'] ?? 1);
        $total = $unitPrice * $quantity;

        return response()->json([
            'product_id' => $product->id,
            'size' => $size ? [
                'id' => $size->id,
                'code' => $size->code,
                'label' => $size->label,
                'price' => $size->price,
            ] : null,
            'modifiers' => $modifiers->map(fn (Modifier $modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'price_label' => $modifier->priceLabel(),
            ])->values(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_price_label' => Money::format($unitPrice),
            'total' => $total,
            'total_label' => Money::format($total),
            'add_button_label' => Money::format($total),
        ]);
    }

    private function ensureCoffee(Establishment $establishment): void
    {
        abort_unless($establishment->offersCoffeeMenu(), 404);
    }
}
