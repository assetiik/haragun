<?php

namespace App\Services\Iiko;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Exceptions\IikoException;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IikoCatalog
{
    public function __construct(private IikoClient $client) {}

    public function enabled(): bool
    {
        return $this->client->isConfigured();
    }

    public function usesIiko(Establishment $establishment): bool
    {
        return $this->enabled()
            && $establishment->type === EstablishmentType::Coffee
            && filled($establishment->iiko_organization_id)
            && filled($establishment->iiko_terminal_group_id)
            && filled($establishment->iiko_table_id);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{correlationId: string, orderId: string, raw: array<string, mixed>}
     */
    public function createVenueOrder(Order $order, User $user, array $lines): array
    {
        $establishment = $order->establishment;

        if ($establishment === null || ! $this->usesIiko($establishment)) {
            throw new IikoException('Заведение не подключено к iiko.');
        }

        $items = $this->buildItems($lines);

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['Нет позиций с привязкой к номенклатуре iiko.'],
            ]);
        }

        $payload = [
            'organizationId' => $establishment->iiko_organization_id,
            'terminalGroupId' => $establishment->iiko_terminal_group_id,
            'order' => [
                'id' => (string) Str::uuid(),
                'externalNumber' => (string) $order->code,
                'tableIds' => [$establishment->iiko_table_id],
                'phone' => $this->phone($user),
                'guestCount' => 1,
                'guests' => [
                    'count' => 1,
                ],
                'customer' => [
                    'name' => filled($user->name) ? $user->name : 'Гость '.$user->guestNumber(),
                    'type' => 'regular',
                ],
                'comment' => 'Haragun · '.$order->code,
                'items' => $items,
            ],
        ];

        if (filled($establishment->iiko_payment_type_id)) {
            $payload['order']['payments'] = [[
                'paymentTypeKind' => 'Card',
                'sum' => round(((int) $order->total) / 1, 2),
                'paymentTypeId' => $establishment->iiko_payment_type_id,
                'isProcessedExternally' => true,
            ]];
        }

        $created = $this->client->createOrder($payload);
        $correlationId = (string) ($created['correlationId'] ?? '');
        $orderId = (string) data_get($created, 'orderInfo.id', data_get($created, 'orderInfo.order.id', ''));
        $status = (string) data_get($created, 'orderInfo.creationStatus', '');

        if ($correlationId !== '' && strcasecmp($status, 'InProgress') === 0) {
            $this->client->waitForCommand($establishment->iiko_organization_id, $correlationId);
        }

        if ($orderId === '' || $orderId === '00000000-0000-0000-0000-000000000000') {
            throw new IikoException('iiko не вернул id заказа.', 502, '/api/1/order/create');
        }

        return [
            'correlationId' => $correlationId,
            'orderId' => $orderId,
            'raw' => $created,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildItems(array $lines): array
    {
        $productIds = collect($lines)
            ->reject(fn (array $line): bool => (bool) ($line['is_discount'] ?? false))
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($lines as $line) {
            if ((bool) ($line['is_discount'] ?? false)) {
                continue;
            }

            if (($line['kind'] ?? null) === ProductKind::Ticket->value) {
                continue;
            }

            $product = $products->get($line['product_id']);
            $iikoProductId = $product?->iiko_product_id;

            if (! filled($iikoProductId)) {
                throw ValidationException::withMessages([
                    'items' => ["Позиция «{$line['name']}» не привязана к iiko."],
                ]);
            }

            $item = [
                'type' => 'Product',
                'productId' => $iikoProductId,
                'amount' => (float) ($line['quantity'] ?? 1),
                'positionId' => (string) Str::uuid(),
            ];

            if (isset($line['unit_price'])) {
                $item['price'] = round(((int) $line['unit_price']) / 1, 2);
            }

            $items[] = $item;
        }

        return $items;
    }

    private function phone(User $user): string
    {
        $digits = preg_replace('/\D+/', '', (string) $user->phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        return str_starts_with($digits, '+') ? $digits : '+'.$digits;
    }
}
