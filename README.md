# Интеграция с Яндекс Картами

Монорепозиторий тестового задания разделён на два самостоятельных приложения:

```text
backend/   Laravel API, Sanctum, PostgreSQL, Redis
frontend/  Vue 3 SPA, Vite, Tailwind CSS
```

Текущий результат включает рабочую аутентификацию, интерфейс, окружение Laravel Sail, доменные таблицы и защищённый API организаций. API сохраняет ссылку, предоставляет статус синхронизации и выдаёт отзывы по 50 записей, но парсер и фоновый worker намеренно пока не реализованы. Подробный план находится в [`task.md`](task.md).

## Быстрый запуск

### 1. Бэкенд

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Для PostgreSQL и Redis через Docker вместо локального запуска:

```bash
cd backend
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

### 2. Фронтенд

В отдельном терминале:

```bash
cd frontend
cp .env.example .env
pnpm install
pnpm dev
```

Фронтенд откроется на `http://localhost:5173`, API — на `http://localhost:8000`.

Демо-доступ:

```text
demo@example.com
password
```

Для публичного окружения эти значения обязательно заменить.

## Текущий API

После входа доступны операции со своими организациями:

```text
GET  /api/v1/organizations
POST /api/v1/organizations
GET  /api/v1/organizations/{id}
GET  /api/v1/organizations/{id}/sync-status
GET  /api/v1/organizations/{id}/reviews?page=1
```

Подключённая организация получает статус `pending`. Он не изменится автоматически, пока отдельным этапом не будут добавлены parser и queue worker.

## Документация

- [`backend/README.md`](backend/README.md) — настройка Laravel API.
- [`frontend/README.md`](frontend/README.md) — настройка Vue SPA.
- [`task.md`](task.md) — требования, этапы и критерии готовности.
- [`AGENTS.md`](AGENTS.md) — общие правила работы с репозиторием.
