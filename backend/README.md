# Бэкенд Yandex Reviews

Laravel API отвечает за cookie-аутентификацию через Sanctum, хранение организаций и отзывов, состояние синхронизаций и выдачу данных фронтенду. Парсер Яндекс Карт и выполнение синхронизации через очередь пока не реализованы.

## Требования

- PHP 8.3 или новее;
- Composer;
- PostgreSQL и Redis либо Docker Desktop с Laravel Sail.

## Локальный запуск

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Для локальной SQLite-базы можно изменить `DB_CONNECTION`, `DB_DATABASE`, `SESSION_DRIVER`, `CACHE_STORE` и `QUEUE_CONNECTION` в `.env`.

## Запуск через Sail

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

## Переменные интеграции с фронтендом

```dotenv
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
```

## Проверки

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
composer audit
```

## Модель данных

- `organizations` — ссылка, внешний идентификатор, название, рейтинг и счётчики компании;
- `reviews` — отзывы с уникальным внешним идентификатором внутри организации;
- `sync_runs` — состояние, прогресс и диагностическая ошибка каждого запуска;
- `organization_snapshots` — история рейтинга и счётчиков компании.

Удаление пользователя удаляет его организации, а удаление организации — связанные отзывы, запуски и снимки. Уникальные индексы не позволяют сохранить одну нормализованную ссылку дважды для одного пользователя.

## API организаций

Все маршруты ниже требуют активную сессию Sanctum. Чужая организация возвращает `404`, не раскрывая факт своего существования.

| Метод и путь | Назначение |
|---|---|
| `GET /api/v1/organizations` | Список организаций текущего пользователя |
| `POST /api/v1/organizations` | Проверка и сохранение ссылки Яндекс Карт |
| `GET /api/v1/organizations/{id}` | Данные одной организации |
| `GET /api/v1/organizations/{id}/sync-status` | Последний статус и прогресс синхронизации |
| `GET /api/v1/organizations/{id}/reviews?page=1` | Отзывы, по 50 записей на страницу |

Пример подключения:

```json
{
  "url": "https://yandex.ru/maps/org/example/123456/"
}
```

Поддерживаются HTTPS-ссылки раздела `/maps/` на доменах `yandex.ru` и `yandex.com`. Параметры запроса и завершающий слеш не влияют на проверку дублей.

При первом добавлении создаётся запуск со статусом `pending` и нулевым прогрессом. API пока не ставит задачу в очередь: это будет сделано вместе с парсером отдельным этапом.

Дополнительно доступны `POST /login`, `DELETE /logout`, `GET /api/v1/user`, `GET /up` и информационный `GET /`.
