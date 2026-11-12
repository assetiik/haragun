# Контракт Flutter: TipTop Pay

Оплата картой через [TipTop Pay](https://developers.tiptoppay.kz).  
`API Secret` только на сервере. В приложение кладём только `public_id` из ответа.

Пока `TIPTOP_PAY_ENABLED=false` / нет ключей — поведение как раньше: заказ сразу `payment_status=paid`, поле `payment` = `null`.

---

## 1. Создание заказа / checkout корзины

`POST /api/orders` и `POST /api/establishments/{id}/cart/checkout` дополнительно отдают:

```json
{
  "order": { "...": "как раньше + payment_status / payment_provider / payment" },
  "payment": {
    "provider": "tiptop",
    "required": true,
    "status": "pending",
    "public_id": "pk_…",
    "amount": 10000,
    "currency": "KZT",
    "invoice_id": "50",
    "account_id": "12",
    "description": "Взрослый и коньки",
    "transaction_id": null
  }
}
```

Если TipTop выключен: `"payment": null`, заказ уже оплачен.

Пока `payment.required == true` / `payment_status == pending`:

- QR / passes могут быть без barcode
- билеты ещё не confirmed на кассе
- iiko для кофе ещё не создан

---

## 2. Оплата (SDK → криптограмма → наш API)

1. TipTop Flutter SDK + `payment.public_id` → криптограмма карты  
2. Отправить на бэкенд:

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

Ответ успех:

```json
{
  "order": { "payment_status": "paid", "payment_label": "Visa · 4242", "…": "…" },
  "payment": { "required": false, "status": "paid", "…" }
}
```

Нужен 3-D Secure:

```json
{
  "order": { "payment_status": "pending", "…" },
  "payment": { "required": true, "status": "pending", "…" },
  "three_ds": {
    "transaction_id": 891463508,
    "acs_url": "https://…",
    "pa_req": "…",
    "iframe_is_allowed": true
  }
}
```

После ACS открыть банк и завершить:

```http
POST /api/orders/{order_id}/payments/3ds
```

```json
{
  "transaction_id": 891463508,
  "pa_res": "…"
}
```

Ошибка оплаты: `422`, `message` / `errors.payment`.

---

## 3. Статусы

| `payment_status` | UI |
|---|---|
| `pending` | ждём оплату |
| `paid` | ок, показывать QR / кофе-экран |
| `failed` | предложить повторить charge |
| `partially_refunded` / `refunded` | часть/весь возврат |

`GET /api/orders/{id}` — как раньше + поля `payment_status`, `payment_provider`, `payment`.

---

## 4. Возврат билета

`POST /api/orders/{id}/passes/{barcode}/refund` без изменений для приложения.  
Бэкенд сам делает TipTop `refund` на сумму билета.

---

## 5. Чеклист Flutter

- [ ] После create/checkout смотреть `payment`
- [ ] Если `payment.required` — SDK + `charge` (+ 3DS)
- [ ] Успех только при `payment_status=paid` (или `payment.required=false`)
- [ ] Не показывать «готовый» QR до оплаты
- [ ] `public_id` из ответа, secret не хранить