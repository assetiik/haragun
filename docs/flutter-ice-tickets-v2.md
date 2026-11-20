# Контракт Flutter: билеты катка v2 (промо / возврат через каток)

Для команды приложения. Бэкенд уже ходит в новую билетную систему катка.  
Ключ API **только на сервере** — в приложение его не класть.

Базовый поток покупки + QR из `docs/flutter-ice-tickets.md` **остаётся**. Ниже — что добавить.

**Обновление 20 ноября 2026:** возврат и перенос билетов оформляет только администрация катка. В приложении одна кнопка «Возврат или перенос билета» — показывает `staff_contact`, **без** вызова API refund/move. Деньги каток возвращает сам в TipTop Pay.

Игнорировать неизвестные поля в JSON.

---

## 1. Сеансы (уже работает)

`GET /api/establishments/{id}?category=ice_rental&date=YYYY-MM-DD`

В `slots[]` могут быть:

| Поле | Зачем |
|---|---|
| `time`, `ends_at` | показать «16:00–18:00» |
| `is_available` | можно ли купить |
| `sale_state` | причина, если нельзя (`sales_closed`, `sold_out`, …) |
| `entry_opens_at` / `entry_closes_at` | окно входа |
| `rink_session_id` | можно игнорировать |
| `prices` | можно игнорировать; для меню лучше запрос с `time` |

Цены типов билетов:

`GET /api/establishments/{id}?category=ice_rental&date=...&time=HH:MM`

→ `products[].price` уже с API. Показывать только то, что пришло.

В QR класть **ровно** `passes[].barcode`, без URL и префиксов.

---

## 2. Промокод

### 2.1 Проверить код (до оплаты)

```http
GET /api/establishments/{establishment_id}/promo-codes/{code}?date=2026-02-13&time=16:00
```

Auth не нужен.

Ответ `200`:

```json
{
  "promo": {
    "code": "TEST10",
    "name": "Test 10%",
    "kind": "percent",
    "value": 10,
    "remaining": 100,
    "session_id": 41,
    "prices": [
      {
        "ticket_type": "adult",
        "name": {"ru": "Взрослый", "en": "Adult"},
        "full_price": 4000,
        "price": 3600,
        "discounted": true
      }
    ]
  }
}
```

UI: обновить цены в корзине по `price` / зачеркнуть `full_price` где `discounted: true`.

Ошибки `422` / `502`, поле `message` (+ иногда `code`):

| `code` (если есть) | Что показать |
|---|---|
| `promo_not_found` | Код не найден, дать убрать |
| `promo_inactive` / `promo_not_started` / `promo_expired` | Не действует |
| `promo_not_valid_here` | Только для кассы |
| `promo_used_up` | Лимит исчерпан |
| иначе | `message` как есть |

Регистр/пробелы не важны (`test10` = `TEST10`) — нормализует бэкенд.

Тестовые коды: `TEST10`, `KIDS500`, `ONETIME1`, `DESKONLY`.

### 2.2 Quote с промо (опционально)

```http
POST /api/orders/quote
```

```json
{
  "establishment_id": 1,
  "date": "2026-02-13",
  "time": "16:00",
  "promo_code": "TEST10",
  "items": [{ "product_id": 1, "quantity": 2 }]
}
```

В ответе: `promo`, `promo_code`, `promo_discount`, `total` уже со скидкой.

### 2.3 Покупка с промо

```http
POST /api/orders
Authorization: Bearer …
```

Как сейчас + поле:

```json
{
  "establishment_id": 1,
  "date": "2026-02-13",
  "time": "16:00",
  "promo_code": "TEST10",
  "items": [{ "product_id": 1, "quantity": 2 }]
}
```

В `order`:

- `promo_code`
- `promo_discount` — экономия в тенге
- `total` — к оплате
- `passes[]` — QR как раньше

Без промо поле можно не слать или `null`.

---

## 3. Passes в деталях заказа (расширение)

`GET /api/orders/{id}` — Auth.

Каждый элемент `passes[]`:

```json
{
  "id": "10-0",
  "item_id": 10,
  "label": "Взрослый",
  "barcode": "ZYULLQB3QR5ZWZLN5LTT",
  "status": "active",
  "ticket_type": "adult",
  "price": 4000,
  "full_price": 4000,
  "can_refund": false,
  "can_move": false,
  "staff_contact": "+7 700 000 00 00, менеджер (звонки и WhatsApp)",
  "session": {
    "date": "2026-02-13",
    "time": "16:00",
    "ends_at": "17:00",
    "entry_opens_at": "15:00",
    "status": "open"
  },
  "valid_from": "…",
  "valid_until": "…",
  "is_active": false
}
```

| `status` | UI |
|---|---|
| `active` | Действителен |
| `used` | Уже прошли |
| `refunded` | Возвращён (оформил каток) |
| `cancelled` | Аннулирован |
| `expired` | Истёк |
| `pending` | QR ещё нет |

| Поле | UI |
|---|---|
| `can_refund` / `can_move` | Всегда `false`. **Не** показывать кнопки «Вернуть» / «Перенести», которые вызывают API. |
| `staff_contact` | Обычный текст (не HTML). Контакт катка для возврата/переноса. Может быть `""`. |
| `session.status` | Если `cancelled` или `closed` — сеанс отменил каток. Автовозврата нет: покажи статус + контакт. |

`is_active` — можно ли сейчас показать «годен к проходу» (окно входа и сеанс не отменён).  
Для текста «Вход откроется в …» используй `session.entry_opens_at` **и** текущее время / `is_active`.

После того как каток перенёс билет, при следующем `GET /orders/{id}` у pass будет новое время сеанса, **QR тот же**. После возврата — `status: refunded`.

На уровне заказа также: `promo_code`, `promo_discount`.

---

## 4. Возврат или перенос (только через каток)

У билета **одна** кнопка: «Возврат или перенос билета».

Она **не вызывает** `POST .../refund` и `POST .../move`. Показывает гостю текст:

- если `staff_contact` не пустой:  
  `Чтобы вернуть или перенести билет, свяжитесь с катком: {staff_contact}`
- если пустой:  
  `Чтобы вернуть или перенести билет, обратитесь к администратору катка.`

Деньги за билет, купленный в приложении, каток возвращает сам в TipTop Pay. Приложению ничего делать не нужно.

### Старые эндпоинты (не использовать в UI)

Если случайно вызвать — ответ `403`:

```http
POST /api/orders/{order_id}/passes/{barcode}/refund
POST /api/orders/{order_id}/passes/{barcode}/move
```

```json
{
  "message": "Чтобы вернуть или перенести билет, свяжитесь с катком: +7 700 000 00 00, менеджер (звонки и WhatsApp)",
  "code": "refund_by_staff_only",
  "details": { "staff_contact": "+7 700 000 00 00, менеджер (звонки и WhatsApp)" }
}
```

Для move код ошибки: `move_by_staff_only`. Покажи `message` гостю. Деньги сами не возвращай.

---

## 5. Ошибки билетной системы

Ориентир: HTTP + `message` (+ `code` если есть).

Частые при покупке: `sold_out`, `price_changed`, `sales_closed`, `session_cancelled`, `not_on_sale_yet`, промо-коды выше.

Если билетная система недоступна (`502`) — **не принимать оплату**, показать «продажа временно недоступна».

---

## 6. Чеклист для Flutter

- [ ] Слоты: `ends_at`, `sale_state`, недоступные сеансы
- [ ] Цены после выбора `time`
- [ ] Поле промокода → preview → цены со скидкой / убрать код
- [ ] `POST /orders` с `promo_code`
- [ ] QR из `barcode` как есть
- [ ] Статусы pass; `can_refund` / `can_move` всегда false
- [ ] Одна кнопка «Возврат или перенос билета» → текст из `staff_contact` (не API)
- [ ] При `session.status` = `cancelled` / `closed` — статус + контакт, без auto-refund
- [ ] Убрать экраны/кнопки self-service refund и move

Кофе / фудкорт / логин не трогать.
