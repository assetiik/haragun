# Контракт Flutter: TipTop Pay (только отдельные кофейни)

Оплата картой через [TipTop Pay](https://developers.tiptoppay.kz).

**Только** для заведений `type = coffee` из админки (отдельные кофейни).  
**Не** для катка / билетов / аренды / меню внутри `ice_arena`.

`API Secret` только на сервере. В приложение — `public_id` из ответа.

Пока `TIPTOP_PAY_ENABLED=false` / нет ключей — checkout сразу `paid`, корзина очищается, `payment = null`.

---

## Поток (важно)

1. `POST .../cart/checkout` → заказ `payment_status=pending`, **корзина НЕ очищается**, `coffee_screen=null`, заказ **не** активен и **не** уходит в iiko.
2. `POST .../payments/charge` (+ при необходимости `.../payments/3ds`).
3. Только после успешной оплаты: `payment_status=paid`, `accepted_at`/`paid_at` заполнены, корзина очищается, появляется `coffee_screen`, заказ можно считать принятым заведением.

При отказе / отмене / незавершённом 3DS: корзина остаётся, заказ не готовится.

---

## 1. Checkout

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
    "description": "Латте",
    "transaction_id": null
  },
  "coffee_screen": null,
  "cart": { "is_empty": false, "items": ["…ещё на месте…"] }
}
```

Пока `payment.required == true` / `is_active == false` — **не** показывать экран «заказ принят».

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

Нужен 3DS — в ответе `three_ds`, корзина ещё полная, `coffee_screen=null`. Затем:

```http
POST /api/orders/{order_id}/payments/3ds
```

```json
{ "transaction_id": 891463508, "pa_res": "…" }
```

Ошибка оплаты: `422` / `errors.payment`, корзина на месте, можно повторить charge.

---

## 3. Статусы

| Этап | `payment_status` | `is_active` | `coffee_screen` | Корзина |
|---|---|---|---|---|
| После checkout | `pending` | false | null | сохранена |
| 3DS / отказ | `pending` / `failed` | false | null | сохранена |
| После оплаты | `paid` | true | accepted… | пустая |

---

## 4. Чеклист Flutter

- [ ] После checkout смотреть `payment.required` — идти в SDK, **не** считать заказ принятым
- [ ] Не очищать UI-корзину сами — бэкенд чистит только после оплаты
- [ ] Успех только при `payment_status=paid` (charge/3ds или refresh order)
- [ ] При fail/cancel — оставить корзину и дать повторить оплату
- [ ] Каток / билеты — без TipTop
