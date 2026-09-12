# Интеграция с Яндекс Картами

Монорепозиторий тестового задания разделён на два самостоятельных приложения:

```text
backend/   Laravel API, Sanctum, PostgreSQL, Redis
frontend/  Vue 3 SPA, Vite, Tailwind CSS
```

Текущий результат включает рабочую аутентификацию, подключение компании, индикацию фоновой синхронизации, карточку с рейтингом и счётчиками, отзывы с пагинацией, Laravel Sail, защищённый API, гибридный парсер и Redis queue. Подробный план находится в [`task.md`](task.md).

## Быстрый запуск через Docker

Понадобятся Docker Desktop, PHP 8.3+, Composer, Node.js 20.19+ и pnpm 11+.

### 1. Бэкенд, PostgreSQL и Redis

```bash
cd backend
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Во втором терминале запустите worker очереди и оставьте его работать:

```bash
cd backend
./vendor/bin/sail artisan queue:work redis --tries=4 --timeout=1800
```

Бэкенд доступен на `http://localhost:8000`. Вариант без Docker и настройка локальных PostgreSQL и Redis описаны в [`backend/README.md`](backend/README.md).

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

## Как работает приложение

1. SPA получает CSRF cookie и входит через Sanctum.
2. После проверки ссылки API сохраняет организацию и ставит `SyncOrganizationJob` в Redis queue.
3. Worker читает публичное JSON-состояние страниц Яндекс Карт, собирает до 600 отзывов и обновляет прогресс.
4. PostgreSQL хранит карточку, отзывы, статус запуска и снимки агрегатов; SPA показывает сохранённые данные по 50 отзывов на страницу.

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

## Проверки

```bash
cd backend
php artisan test --compact
composer audit

cd ../frontend
pnpm test
pnpm run build
pnpm audit --prod
```

## Проверенный сценарий

12 сентября 2026 года выполнен smoke-test на реальной карточке «Краткость» в Яндекс Картах. API создал организацию, Redis worker завершил Job за 3 секунды, а PostgreSQL сохранил 47 отзывов и снимок агрегатов. Конечный статус — `completed`, прогресс — 100%. Числа в живой карточке могут меняться после даты проверки.

Отдельно проверены карточки с большим числом отзывов: «Tatar by Tubatay» и «Гриль Босс». Обе синхронизации завершились статусом `completed`; для каждой сохранено по 600 уникальных отзывов из публичной SSR-выдачи.

## Ограничения прототипа

- Текущий адаптер зависит от публичного JSON в HTML Яндекс Карт; изменение схемы будет явно отмечено статусом `source_changed`.
- Прототип не обходит CAPTCHA и не эмулирует защитные параметры. Блокировка фиксируется статусом `blocked`.
- Перед регулярным массовым сбором нужны общий rate limit, jitter, плановая синхронизация, метрики и оповещения. Эти production-механизмы пока не реализованы.
- Публичный хостинг и URL Git-репозитория ещё не добавлены.

## Документация

- [`backend/README.md`](backend/README.md) — настройка Laravel API.
- [`frontend/README.md`](frontend/README.md) — настройка Vue SPA.
- [`task.md`](task.md) — требования, этапы и критерии готовности.
- [`AGENTS.md`](AGENTS.md) — общие правила работы с репозиторием.
