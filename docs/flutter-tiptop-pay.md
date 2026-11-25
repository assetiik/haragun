# Контракт Flutter: TipTop Pay (каток + кофейни)

Оплата картой через [TipTop Pay](https://developers.tiptoppay.kz).

**Каток** (`type = ice_arena`): билеты, аренда, еда/меню — свой TipTop merchant.  
**Отдельные кофейни** (`type = coffee`): checkout корзины — отдельный TipTop merchant.

В приложение всегда берите `public_id` **из ответа API** (`payment.public_id`) — у катка и кофе он разный.  
`API Secret` только на сервере.

Пока TipTop выключен / нет ключей для типа заведения — заказ сразу `paid`, `payment = null`.

---

## Поток (важно)

### Еда / меню (корзина: кофейня или фудкорт катка)

1. `POST .../cart/checkout` → заказ `payment_status=pending`, **корзина НЕ очищается**, заказ **не** активен.
2. `POST .../payments/charge` (+ при необходимости `.../payments/3ds`).
3. Только после успешной оплаты: `payment_status=paid`, корзина очищается.  
   Для `coffee` появляется `coffee_screen`; для катка `coffee_screen` обычно `null`.

### Билеты / аренда (каток)

1. `POST /api/orders` → заказ `payment_status=pending`, бронь **ещё не confirm**, QR/passes без кодов, `is_active=false`.
2. `POST .../payments/charge` (+ 3DS).
3. После оплаты: confirm билетов, появляются `passes` / barcodes, `is_active=true`.

При отказе / отмене / незавершённом 3DS: заказ не активен. Новый заказ того же пользователя на то же заведение отменяет предыдущий неоплаченный TipTop-заказ.

---

## 1. Checkout корзины

```http
POST /api/establishments/{id}/cart/checkout
Authorization: Bearer …
```

Ответ при TipTop:

```json
{
  "order": {
    "type": "venue",
    "status": "accepted",
    "payment_status": "pending",
    "payment_provider": "tiptop",
    "is_active": false,
    "payment": { "provider": "tiptop", "required": true, "…" }
  },
  "payment": {
    "provider": "tiptop",
    "required": true,
    "status": "pending",
    "public_id": "pk_…",
    "amount": 2940,
    "currency": "KZT",
    "invoice_id": "50",
    "account_id": "12",
    "description": "…",
    "transaction_id": null
  },
  "coffee_screen": null,
  "cart": { "is_empty": false, "items": ["…ещё на месте…"] }
}
```

Пока `payment.required == true` / `is_active == false` — **не** считать заказ принятым.

---

## 1b. Создание билетов / аренды

```http
POST /api/orders
```

Ответ при TipTop:

```json
{
  "order": {
    "type": "tickets",
    "payment_status": "pending",
    "payment_provider": "tiptop",
    "is_active": false,
    "passes": [{ "barcode": null, "…" }]
  },
  "payment": {
    "provider": "tiptop",
    "required": true,
    "public_id": "pk_…",
    "amount": 8000,
    "invoice_id": "APP-01j…",
    "…"
  }
}
```

Для билетов катка `payment.invoice_id` = номер заказа в билетной системе (`external_order_id`, вид `APP-…`).  
Тот же номер уходит в TipTop как `InvoiceId` — каток по нему находит платёж при возврате в ЛК TipTop.  
Для кофе/корзины `invoice_id` по-прежнему локальный id заказа (число строкой).

Фискальный чек формирует онлайн-касса TipTop (метод 1): бэкенд при charge передаёт позиции заказа в `JsonData.PaymentData.CustomerReceipt` (сумма позиций = `order.total` после скидок). При API-refund уходит чек возврата с той же структурой. Приложению ничего дополнительно слать не нужно.

---

## 2. Charge / 3DS

```http
POST /api/orders/{order_id}/payments/charge
```

```json
{ "cryptogram": "…", "name": "CARDHOLDER NAME" }
```

Успех:

```json
{
  "order": { "payment_status": "paid", "is_active": true, "…" },
  "payment": { "required": false, "status": "paid", "…" },
  "coffee_screen": { "screen": "accepted", "…" },
  "cart": { "is_empty": true }
}
```

Нужен 3DS — в ответе `three_ds`, затем:

```http
POST /api/orders/{order_id}/payments/3ds
```

```json
{ "transaction_id": 891463508, "pa_res": "…" }
```

`pa_res` — непустая строка как вернул SDK (в тесте ACS бывает `AQ==`).

Ошибка: `422` / `errors.payment`, можно повторить charge.

Отправка PaRes сама по себе не подтверждает списание — смотрите `payment_status=paid`.

---

## 3. Статусы

| Этап | `payment_status` | `is_active` | Билеты / QR | Корзина |
|---|---|---|---|---|
| После checkout / create | `pending` | false | нет | сохранена |
| 3DS / отказ | `pending` / `failed` | false | нет | сохранена |
| После оплаты | `paid` | true | есть | пустая |

---

## 4. Чеклист Flutter

- [ ] `public_id` только из `payment.public_id` ответа (разный для катка и кофе)
- [ ] После checkout / create смотреть `payment.required` — идти в SDK
- [ ] Успех только при `payment_status=paid`
- [ ] Для билетов QR только после оплаты
- [ ] При fail/cancel — дать повторить оплату
- [ ] Secret в приложении не хранить
