# Фронтенд Yandex Reviews

Отдельное Vue 3 SPA для входа, подключения организации и просмотра отзывов. Приложение не содержит Laravel-шаблонов и общается с бэкендом только по HTTP.

## Требования

- Node.js 20.19+ или 22.12+;
- pnpm 11+.

## Запуск

```bash
cp .env.example .env
pnpm install
pnpm dev
```

В `.env` указывается адрес Laravel API:

```dotenv
VITE_API_URL=http://localhost:8000
```

## Сборка и аудит

```bash
pnpm run build
pnpm audit --prod
```

Sanctum работает через HTTP-only cookie. Axios настроен с `withCredentials` и `withXSRFToken`; хранить авторизационные данные в `localStorage` не требуется.
