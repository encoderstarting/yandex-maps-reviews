# Бэкенд Yandex Reviews

Laravel API отвечает за cookie-аутентификацию через Sanctum, хранение организаций и отзывов, фоновую синхронизацию через Redis queue и выдачу данных фронтенду.

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

В отдельном терминале запустите worker:

```bash
php artisan queue:work redis --tries=4 --timeout=1800
```

При локальном Redis вне Sail укажите в `.env` значение `REDIS_HOST=127.0.0.1`. Внутри Sail оставьте `REDIS_HOST=redis`.

Для локальной SQLite-базы можно изменить `DB_CONNECTION`, `DB_DATABASE`, `SESSION_DRIVER`, `CACHE_STORE` и `QUEUE_CONNECTION` в `.env`.

## Запуск через Sail

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Запуск worker через Sail:

```bash
./vendor/bin/sail artisan queue:work redis --tries=4 --timeout=1800
```

## Переменные интеграции с фронтендом

```dotenv
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=2100
```

## Настройки парсера

```dotenv
YANDEX_MAPS_CONNECT_TIMEOUT=5
YANDEX_MAPS_TIMEOUT=20
YANDEX_MAPS_RETRIES=2
YANDEX_MAPS_RETRY_DELAY_MS=500
YANDEX_MAPS_MAX_PAGES=100
YANDEX_MAPS_USER_AGENT="Mozilla/5.0 ..."
```

`YANDEX_MAPS_MAX_PAGES` ограничивает один запуск и защищает worker от бесконечной пагинации. При размере страницы 50 значение `100` допускает до 5000 отзывов. User-Agent задаётся только через конфигурацию и не зашит в бизнес-логику.

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

При первом добавлении создаётся запуск со статусом `pending`, после чего API ставит `SyncOrganizationJob` в Redis queue. Worker меняет статус на `running`, обновляет прогресс после каждой страницы и завершает запуск статусом `completed`, `blocked`, `source_changed` или `failed`.

Одновременно может выполняться только одна задача для организации. Временные ошибки повторяются через 60, 300 и 900 секунд. Отзывы сохраняются через `upsert` по внешнему ID, поэтому повторный импорт не создаёт дубли. Новый снимок рейтинга и счётчиков сохраняется только при их изменении.

## Подход к парсингу

Используется лёгкий гибридный подход без headless-браузера:

1. HTTP-клиент загружает публичную HTML-страницу карточки.
2. Из `<script class="state-view" type="application/json">` извлекается серверное JSON-состояние.
3. Из JSON читаются внешний ID, название, рейтинг, количество оценок и количество отзывов.
4. Парсер открывает публичные страницы отзывов `?page=1`, `?page=2` и далее. Каждая страница содержит до 50 отзывов и параметры пагинации.
5. Отзывы объединяются по `reviewId`; повтор страницы или неожиданное окончание считаются изменением источника.

Внутренний XHR `business/fetchReviews` исследован, но не используется: он требует динамический браузерный параметр `s`. Воспроизведение этого защитного параметра сделало бы решение хрупким и приблизило бы его к обходу защиты. Публичные SSR-страницы дают те же структурированные данные без эмуляции Chrome.

Headless-браузер не выбран основным способом из-за высокого расхода памяти, медленной прокрутки, зависимости от CSS-селекторов и сложного масштабирования на десятки организаций. Он может быть добавлен позже только как диагностический адаптер за общим контрактом `OrganizationParser`.

Парсер различает:

- `YandexMapsBlockedException` — HTTP 403, 429 или CAPTCHA;
- `YandexMapsSourceChangedException` — отсутствует JSON, показатели, отзывы или изменилась пагинация;
- `YandexMapsUnavailableException` — тайм-аут, HTTP 5xx либо превышен безопасный лимит страниц.

Для временных сетевых и серверных ошибок выполняются ограниченные повторы. CAPTCHA не обходится. В логи нельзя помещать полный HTML, cookies и служебные параметры ответа.

Парсинг проверяется небольшими HTML-fixtures; автоматические тесты не обращаются к Яндекс Картам. Парсер вызывается только из фоновой Job, поэтому длительная операция не удерживает HTTP-запрос.

Дополнительно доступны `POST /login`, `DELETE /logout`, `GET /api/v1/user`, `GET /up` и информационный `GET /`.
