# Контракт Flutter: 1 эспрессо в подарок (welcome)

Для новых гостей при **первой регистрации**. Только кофейни Haragun Express, не арена / фудкорт.

## Правила

- Выдаётся один раз на номер телефона при первом `verify-code`
- После удаления аккаунта подарок сгорает и повторно **не** выдаётся
- Бессрочно, пока не погашен
- Гасится **1 единица** товара с `is_welcome_espresso: true`
- Можно вместе с другими платными напитками: 2 латте + эспрессо в подарок = 3 позиции в заказе, платит за 2 латте
- Подарочный эспрессо **не** даёт штамп кофейной карты
- Бариста видит заказ как обычно: экран статуса / номер заказа / штрихкод; подарочная позиция с `is_welcome_gift: true` и `amount: 0`

## Где смотреть статус

| Место | Маршрут | Поле |
| --- | --- | --- |
| После логина | `POST /api/auth/verify-code` | `welcome_gift` |
| Главная | `GET /api/home` | `welcome_gift` |
| Меню кофейни | `GET /api/establishments/{id}/menu` | `welcome_gift` (на арене `null`) |
| Карточка товара | menu products | `is_welcome_espresso` |
| Корзина | `GET/PATCH .../cart` | `welcome_gift`, `totals` |
| Заказ | `GET /api/orders/{id}` | `welcome_gift_applied`, `items[].is_welcome_gift` |

Без авторизации: `welcome_gift: null`.

### Объект статуса

```json
{
  "available": true,
  "title": "1 эспрессо в подарок",
  "subtitle": "Только в кофейнях Haragun Express"
}
```

После погашения: `available: false` (объект остаётся).

## UX в корзине

1. Добавить эспрессо (`is_welcome_espresso: true`) и любые другие напитки
2. Включить подарок:

```http
PATCH /api/establishments/{id}/cart
Content-Type: application/json

{ "apply_welcome_gift": true }
```

Можно слать только `apply_welcome_gift` или только `payment_method`.

### Ответ корзины (фрагмент)

```json
{
  "welcome_gift": {
    "available": true,
    "can_apply": true,
    "applied": true,
    "title": "1 эспрессо в подарок",
    "subtitle": "Только в кофейнях Haragun Express",
    "discount": 990
  },
  "totals": {
    "items_count": 3,
    "subtotal": 4370,
    "welcome_gift_discount": 990,
    "total": 3380
  }
}
```

- `can_apply` — подарок есть **и** в корзине есть эспрессо
- `applied` — тумблер включён и скидка посчитана
- `totals.total` — уже с учётом подарка

Выключить: `{ "apply_welcome_gift": false }`.

Ошибки `422` на поле `apply_welcome_gift`, если:

- не кофейня
- подарок уже использован / нет
- в корзине нет эспрессо

## Checkout

Обычный `POST .../cart/checkout`. Если `apply_welcome_gift` был включён:

```json
{
  "order": {
    "welcome_gift_applied": true,
    "items_count": 3,
    "total": 3380,
    "items": [
      {
        "name": "Латте",
        "quantity": 2,
        "amount": 3380,
        "is_welcome_gift": false
      },
      {
        "name": "Эспрессо",
        "quantity": 1,
        "unit_price": 0,
        "amount": 0,
        "subtitle": "30 мл · в подарок",
        "is_welcome_gift": true
      }
    ]
  }
}
```

Гость показывает бармене экран заказа / штрихкод как сейчас. Отдельный QR для подарка не нужен.

## Товар

В меню и карточке:

```json
{
  "is_welcome_espresso": true
}
```

Метку «можно в подарок» показывайте только если `welcome_gift.available === true` и `is_welcome_espresso === true`.

## Что сделать на клиенте

1. После регистрации / на home+menu кофейни читать `welcome_gift.available`
2. В корзине тумблер «Эспрессо в подарок», когда `welcome_gift.can_apply`
3. `PATCH .../cart` с `apply_welcome_gift`
4. Итог брать из `totals.total` (не пересчитывать сами)
5. На экране заказа подсветить `items` с `is_welcome_gift: true`
6. Не путать с кофейной картой (`coffee_loyalty`) — это разные механики
