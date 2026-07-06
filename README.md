# Short Link API

Сервис генерации и хранения коротких URL-ссылок (сокращатель ссылок).

## Требования к окружению

- Docker
- Docker Compose

## Архитектура

Проект написан на Symfony, данные хранятся в MySQL, генерация коротких ссылок выполняется асинхронно через Symfony Messenger worker.

## Развертывание

1. Откройте корень проекта, где лежит `docker-compose.yml`.
2. Соберите и запустите контейнеры:

```bash
docker compose up -d --build
```

После запуска будут доступны сервисы `app`, `worker`, `nginx`, `mysql`.
Сервис `init` выполнится один раз: установит зависимости, применит миграции и подготовит очередь.

API доступно по адресу:

- `GET http://localhost:8000/api/shortlink?url={original_url}`

## Postman

Коллекция для проверки API находится в `postman/short-link-api.postman_collection.json`.

## Тестирование

В проекте есть unit-тесты для ключевой бизнес-логики:

- `shortlink-api/tests/Service/ShortLinkServiceTest.php`
- `shortlink-api/tests/MessageHandler/GenerateShortCodeMessageHandlerTest.php`

Запуск тестов:

```bash
docker compose exec app php bin/phpunit
```
