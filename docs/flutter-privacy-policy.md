# Контракт: политика конфиденциальности

Для Flutter. Документы загружаются в админке: **Сайт → О приложении и политика** (PDF / Word, можно несколько).

## Эндпоинты

### Публичный (без токена)

```http
GET /api/privacy-policy
```

Язык заголовка: `?locale=ru|kz|en` или `Accept-Language`.

```json
{
  "title": "Политика конфиденциальности",
  "documents": [
    {
      "url": "https://example.com/storage/privacy-policy/policy.pdf",
      "name": "policy.pdf"
    },
    {
      "url": "https://example.com/storage/privacy-policy/consent.docx",
      "name": "consent.docx"
    }
  ]
}
```

- `title` — строка, всегда есть
- `documents` — массив, может быть пустым `[]`
- `documents[].url` — абсолютный URL файла, открывать в браузере / PDF viewer
- `documents[].name` — имя файла для подписи в списке

Поля `text` больше нет — только документы.

### В профиле (с Bearer)

```http
GET /api/profile
Authorization: Bearer …
```

```json
{
  "privacy_policy": {
    "title": "Политика конфиденциальности",
    "documents": [
      { "url": "https://…/policy.pdf", "name": "policy.pdf" }
    ]
  }
}
```

Та же форма, что у публичного эндпоинта.

## Что сделать во Flutter

1. Экран «Политика конфиденциальности»: заголовок `title`, список документов.
2. Тап по документу → открыть `url` (in-app WebView / `url_launcher` / PDF viewer).
3. Если `documents` пустой — показать пустое состояние.
4. С онбординга / логина → `GET /api/privacy-policy` без auth.
5. В профиле можно взять `privacy_policy` из `GET /api/profile`.

## Админка

Админ загружает файлы PDF/DOC/DOCX (несколько, с сортировкой). После «Сохранить» API сразу отдаёт новые URL.
