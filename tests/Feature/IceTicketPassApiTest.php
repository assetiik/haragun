<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class IceTicketPassApiTest extends TestCase
{
    use ConfiguresRinkApi;
    use RefreshDatabase;

    public function test_refund_pass_is_staff_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        [$user, $order] = $this->seedOrderWithPasses();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/passes/ZYULLQB3QR5ZWZLN5LTT/refund", [
            'reason' => 'гость попросил',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'refund_by_staff_only')
            ->assertJsonPath('details.staff_contact', '+7 700 000 00 00, менеджер (звонки и WhatsApp)');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT/refund');
    }

    public function test_move_pass_is_staff_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        [$user, $order] = $this->seedOrderWithPasses(singlePass: true);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/passes/ZYULLQB3QR5ZWZLN5LTT/move", [
            'date' => '2026-02-13',
            'time' => '19:00',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'move_by_staff_only');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT/move');
    }

    public function test_order_passes_expose_staff_contact_and_disable_self_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 16:30:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        [$user, $order] = $this->seedOrderWithPasses(singlePass: true);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('passes.0.can_refund', false)
            ->assertJsonPath('passes.0.can_move', false)
            ->assertJsonPath('passes.0.staff_contact', '+7 700 000 00 00, менеджер (звонки и WhatsApp)')
            ->assertJsonPath('passes.0.session.status', 'open');
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function seedOrderWithPasses(bool $singlePass = false): array
    {
        $user = User::factory()->create(['phone' => '77071234567']);
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
        ]);

        $adult = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'ticket_type' => 'adult',
            'price' => 4000,
        ]);

        $codes = $singlePass
            ? ['ZYULLQB3QR5ZWZLN5LTT']
            : ['ZYULLQB3QR5ZWZLN5LTT', 'BUH4853QM27CP3455D9A'];

        $order = Order::query()->create([
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Tickets,
            'status' => OrderStatus::Confirmed,
            'number' => 4242,
            'code' => 'HRG-4242',
            'rink_order_id' => 'ord_test_hold_1',
            'rink_external_order_id' => 'APP-01testexternaloid',
            'rink_session_id' => 41,
            'session_date' => '2026-02-13',
            'session_time' => '16:00:00',
            'session_ends_at' => '17:00:00',
            'session_entry_opens_at' => '15:00:00',
            'subtotal' => 4000 * count($codes),
            'discount_total' => 0,
            'promo_discount' => 0,
            'total' => 4000 * count($codes),
            'items_count' => count($codes),
            'summary_title' => 'Билеты',
            'summary_short' => 'Билеты',
            'payment_label' => 'Kaspi',
            'points_to_earn' => 0,
            'paid_at' => now(),
            'accepted_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $adult->id,
            'kind' => ProductKind::Ticket->value,
            'name' => 'Взрослый',
            'quantity' => count($codes),
            'unit_price' => 4000,
            'amount' => 4000 * count($codes),
            'barcodes' => $codes,
            'is_discount' => false,
            'sort_order' => 1,
        ]);

        return [$user, $order->fresh(['items', 'establishment'])];
    }
}
