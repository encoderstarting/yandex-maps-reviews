# Развёртывание на российском VPS

Конфигурация поднимает на одном сервере шесть изолированных контейнеров: Caddy, Vue SPA, Laravel API, worker очереди, PostgreSQL и Redis. Наружу открыты только порты `80` и `443`; база данных и Redis доступны лишь во внутренней Docker-сети. Worker дополнительно подключён к отдельной сети `egress`: она разрешает исходящие запросы парсера к Яндекс Картам, но не публикует порты worker в интернет.

## Сервер

Рекомендуемая конфигурация для тестового проекта:

- Ubuntu 24.04;
- 2 vCPU;
- 4 ГБ RAM;
- 40–50 ГБ NVMe;
- публичный IPv4;
- образ с предустановленными Docker и Docker Compose.

Перед запуском домен или поддомен должен указывать A-записью на IPv4 сервера. Caddy автоматически получит и будет обновлять HTTPS-сертификат.

## Первый запуск

Подключитесь к серверу по SSH и клонируйте репозиторий:

```bash
git clone https://github.com/encoderstarting/yandex-maps-reviews.git
cd yandex-maps-reviews
cp deploy/.env.example deploy/.env
```

Откройте `deploy/.env` и замените домен и оба значения `ЗАМЕНИТЬ_*`.

При production-сборке PHP-расширение Redis скачивается из закреплённого официального релиза `phpredis` на GitHub. Это не даёт сборке зависеть от доступности каталога PECL.

Сгенерировать `APP_KEY` без локальной установки PHP можно так:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Проверьте итоговую конфигурацию, соберите и запустите контейнеры:

```bash
docker compose --env-file deploy/.env -f compose.production.yaml config --quiet
docker compose --env-file deploy/.env -f compose.production.yaml up -d --build
docker compose --env-file deploy/.env -f compose.production.yaml run --rm backend php artisan migrate --force
docker compose --env-file deploy/.env -f compose.production.yaml run --rm backend php artisan db:seed --force
```

Состояние контейнеров и последние логи:

```bash
docker compose --env-file deploy/.env -f compose.production.yaml ps
docker compose --env-file deploy/.env -f compose.production.yaml logs --tail=100 backend worker proxy
```

После этого приложение должно открываться по адресу из `APP_URL`.

## Обновление

```bash
git pull --ff-only
docker compose --env-file deploy/.env -f compose.production.yaml up -d --build
docker compose --env-file deploy/.env -f compose.production.yaml run --rm backend php artisan migrate --force
```

Worker ограничен параметром `--max-time=3600`, поэтому раз в час корректно завершается и автоматически создаётся заново политикой `restart: unless-stopped`. Это позволяет ему подхватывать новый код после обновления образа. Для корректной остановки очереди Compose явно отправляет `SIGTERM`, а выполняющаяся Job может завершиться в течение `stop_grace_period`.

## Остановка и резервная копия

Остановить приложение без удаления данных:

```bash
docker compose --env-file deploy/.env -f compose.production.yaml down
```

Не добавляйте флаг `-v`: он удалит volumes PostgreSQL, Redis и Caddy. Для реальной эксплуатации нужно отдельно настроить регулярную выгрузку PostgreSQL и резервное копирование за пределы этого VPS.
