# Бэкенд Yandex Reviews

Laravel API отвечает за cookie-аутентификацию через Sanctum, хранение организаций и отзывов, постановку синхронизаций в очередь и выдачу данных фронтенду.

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

Сейчас доступны `POST /login`, `DELETE /logout`, `GET /api/v1/user`, `GET /up` и информационный `GET /`.
