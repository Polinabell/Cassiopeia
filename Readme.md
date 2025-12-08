# Проект Cassiopeia (космический дашборд)
Коротко: многосервисный стенд на Docker Compose: Rust-сервис `rust_iss` агрегирует ISS/NASA/AstronomyAPI, Laravel `php_web` отдает дашборды и прокси, Python `telemetry_cli` генерирует телеметрию. Nginx в составе, PostgreSQL как БД.

## Что сделано в рефакторинге
- Rust: слои config/clients/services/repo/routes/scheduler, DI через AppState, единый JSON envelope ошибок, retry/timeout в HTTP, upsert по бизнес-ключам, фоновые задачи с pg advisory lock.
- PHP: сервисы/репозитории вместо логики в контроллерах, единый ApiResponse, Blade без прямых SQL/HTTP, устойчивый парсинг AstronomyAPI/JWST.
- Legacy: Pascal заменён на Python `telemetry_cli` с supercronic, COPY CSV в Postgres, логи в stdout/stderr.
- Docker: обновлён `docker-compose.yml`, убраны хардкоды секретов, сборка образов «с нуля».
- Тесты: Rust unit (10+), фронтовые JS unit (11) для утилит парсинга.

## Быстрый старт
1. Подготовьте `.env` (см. `tmp.env.add` как пример: DB, ASTRO_APP_ID/SECRET, JWST_API_KEY/EMAIL, RUST_BASE и др.).
2. Соберите и поднимите:
   ```bash
   docker-compose up --build
   ```
3. Приложение: http://localhost:8080 (Nginx → php_web), Rust API на 3000 внутри сети.

## Полезные команды
- Запуск бэкенд-тестов Rust:
  ```bash
  docker run --rm -v \"$PWD/services/rust-iss:/app\" -w /app rust:1-slim \
    bash -lc 'apt-get update && apt-get install -y --no-install-recommends pkg-config libssl-dev ca-certificates && export PATH=$PATH:/usr/local/cargo/bin && cargo test'
  ```
- Запуск фронтовых JS тестов:
  ```bash
  node services/php-web/tests/frontend.test.js
  ```

## Модули
- `rust_iss` — Axum + SQLx, клиенты внешних API, репозитории, фоновые задачи, единый envelope ошибок.
- `php_web` — Laravel контроллеры + сервисы/репо, Blade дашборд, JWST/Astro прокси.
- `telemetry_cli` — Python + supercronic, генерация CSV и COPY в Postgres.
- `db` — PostgreSQL init/sql.
- `nginx` — обратный прокси для php_web.