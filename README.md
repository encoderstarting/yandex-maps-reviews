# Интеграция с Яндекс Картами

Монорепозиторий тестового задания разделён на два самостоятельных приложения:

```text
backend/   Laravel API, Sanctum, PostgreSQL, Redis
frontend/  Vue 3 SPA, Vite, Tailwind CSS
```

Текущий результат включает рабочую аутентификацию, подключение компании, индикацию фоновой синхронизации, карточку с рейтингом и счётчиками, отзывы с пагинацией, Laravel Sail, защищённый API, гибридный парсер и Redis queue. Подробный план находится в [`task.md`](task.md).

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

В ещё одном терминале запустите фоновый worker:

```bash
cd backend
php artisan queue:work redis --tries=4 --timeout=1800
```

Если Redis запущен на macOS, а не в Sail, укажите в `backend/.env` значение `REDIS_HOST=127.0.0.1`.

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

Подключённая организация получает статус `pending`. Запущенный worker забирает задачу из Redis, обновляет прогресс, сохраняет организацию, отзывы и историю агрегатов.

## Документация

- [`backend/README.md`](backend/README.md) — настройка Laravel API.
- [`frontend/README.md`](frontend/README.md) — настройка Vue SPA.
- [`task.md`](task.md) — требования, этапы и критерии готовности.
- [`AGENTS.md`](AGENTS.md) — общие правила работы с репозиторием.
