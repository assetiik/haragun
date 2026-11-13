# Контракт Flutter: TipTop Pay (только отдельные кофейни)

Оплата картой через [TipTop Pay](https://developers.tiptoppay.kz).

**Только** для заведений `type = coffee` из админки (отдельные кофейни).  
**Не** для катка / билетов / аренды / меню внутри `ice_arena`.

`API Secret` только на сервере. В приложение — `public_id` из ответа.

Пока `TIPTOP_PAY_ENABLED=false` / нет ключей — checkout кофейни сразу `paid`, `payment = null`.

---

## 1. Checkout корзины кофейни

```http
POST /api/establishments/{id}/cart/checkout
Authorization: Bearer …
```

Ответ:

```json
{
  "order": {
    "type": "venue",
    "payment_status": "pending",
    "payment_provider": "tiptop",
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
  "coffee_screen": { "…" },
  "cart": { "…" }
}
```

Если TipTop выключен или заведение не `coffee`: `"payment": null`, заказ уже оплачен.

Пока `payment.required == true` — заказ ещё не в iiko / не считать готовым к выдаче.

`POST /api/orders` (билеты катка) TipTop **не** использует.

---

## 2. Оплата

1. TipTop Flutter SDK + `payment.public_id` → криптограмма  
2. На бэкенд:

```http
POST /api/orders/{order_id}/payments/charge
Authorization: Bearer …
```

```json
{
  "cryptogram": "…",
  "name": "CARDHOLDER NAME"
}
```

Успех: `payment_status=paid`, `payment.required=false`.

3-D Secure:

```json
{
  "three_ds": {
    "transaction_id": 891463508,
    "acs_url": "https://…",
    "pa_req": "…",
    "iframe_is_allowed": true
  }
}
```

Затем:

```http
POST /api/orders/{order_id}/payments/3ds
```

```json
{ "transaction_id": 891463508, "pa_res": "…" }
```

Ошибка: `422`, `errors.payment`.

---

## 3. Статусы

| `payment_status` | UI |
|---|---|
| `pending` | ждём оплату |
| `paid` | кофе-экран / заказ принят |
| `failed` | повторить charge |
| `partially_refunded` / `refunded` | возврат |

---

## 4. Чеклист Flutter

- [ ] TipTop только на checkout **отдельной кофейни** (`type=coffee`)
- [ ] Каток / билеты — без TipTop
- [ ] Если `payment.required` — SDK + charge (+ 3DS)
- [ ] Успех только при `payment_status=paid`
- [ ] `public_id` из ответа, secret не хранить
