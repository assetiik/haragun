# Контракт: оферта

Для Flutter. Документы загружаются в админке: **Сайт → О приложении и политика** (PDF / Word, можно несколько).

## Эндпоинты

### Публичный (без токена)

```http
GET /api/offer
```

Язык заголовка: `?locale=ru|kz|en` или `Accept-Language`.

```json
{
  "title": "Оферта",
  "documents": [
    {
      "url": "https://example.com/storage/offer/offer.pdf",
      "name": "offer.pdf"
    },
    {
      "url": "https://example.com/storage/offer/terms.docx",
      "name": "terms.docx"
    }
  ]
}
```

- `title` — строка, всегда есть
- `documents` — массив, может быть пустым `[]`
- `documents[].url` — абсолютный URL файла, открывать в браузере / PDF viewer
- `documents[].name` — имя файла для подписи в списке

### В профиле (с Bearer)

```http
GET /api/profile
Authorization: Bearer …
```

```json
{
  "offer": {
    "title": "Оферта",
    "documents": [
      { "url": "https://…/offer.pdf", "name": "offer.pdf" }
    ]
  }
}
```

Та же форма, что у публичного эндпоинта.

## Что сделать во Flutter

1. Экран «Оферта»: заголовок `title`, список документов.
2. Тап по документу → открыть `url` (in-app WebView / `url_launcher` / PDF viewer).
3. Если `documents` пустой — показать пустое состояние.
4. С онбординга / логина → `GET /api/offer` без auth.
5. В профиле можно взять `offer` из `GET /api/profile`.

## Админка

Админ загружает файлы PDF/DOC/DOCX (несколько, с сортировкой). После «Сохранить» API сразу отдаёт новые URL.
