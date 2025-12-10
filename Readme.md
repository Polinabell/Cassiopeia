# Проект Cassiopeia (дашборд)
Многосервисный стенд на Docker Compose: Rust-сервис `rust_iss` агрегирует ISS/NASA/SpaceX/OSDR, Laravel `php_web` отдаёт дашборды и дергает внешние API (AstronomyAPI, JWST), Python `telemetry_cli` генерирует телеметрию. Nginx и PostgreSQL входят в состав.

## Архитектура и потоки
- Запрос дашборда: Пользователь → Nginx → PHP Web. PHP берёт ISS/OSDR/space кеш через `RustApiService` (HTTP в `rust_iss`) и события/позиции тел через `AstronomyService` (прямо в AstronomyAPI). Ответ — HTML.
- Rust-ISS: слои config/clients/services/repo/routes/scheduler, DI через AppState, единый JSON envelope (`ok/data/error`), HTTP retry/timeout/user-agent, upsert по бизнес-ключам, фоновые задачи с pg advisory lock.
- Фоновые задачи: scheduler внутри `rust_iss` периодически тянет WhereTheISS/OSDR/NASA (APOD/NEO/DONKI)/SpaceX и пишет кеш в Postgres. Отдельно `telemetry_cli` (Python + supercronic) по расписанию генерирует CSV и `COPY` в `telemetry_legacy`.
- PHP Web: контроллеры тонкие, бизнес в сервисах/репозиториях/DTO, единый `ApiResponse`, Blade без прямых SQL/HTTP, устойчивый парсинг AstronomyAPI/JWST.
- Docker: compose собирает все образы с нуля, без хардкода секретов (env/secrets).

## База данных (факт, см. `db/init.sql`)
- `iss_fetch_log` — лог загрузок ISS (payload jsonb).
- `osdr_items` — OSDR с уникальным `dataset_id` (partial unique index).
- `space_cache` — кеш SpaceX/NASA (индекс `source, fetched_at desc`).
- `telemetry_legacy` — таблица, куда пишет `telemetry_cli` через COPY.
- `cms_pages`, `cms_blocks` — контент CMS (сид заполнен безопасно/небезопасно для демонстрации XSS-защиты).

## Переменные окружения (минимум)
- БД: `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `DATABASE_URL` для Rust.
- Rust-ISS: `RUST_ISS__PORT`, `FETCH_*_INTERVAL_SEC`, `HTTP_TIMEOUT_SEC`, `USER_AGENT`.
- AstronomyAPI: `ASTRO_APP_ID`, `ASTRO_APP_SECRET`, `ASTRO_ORIGIN`, `ASTRO_BODIES`, `ASTRO_BODY`, `ASTRO_ELEVATION`.
- JWST: `JWST_API_KEY`, `JWST_EMAIL`.
- Прочее: `NASA_API_KEY`, `ASTRO_APP_SECRET` без скрытых символов/переносов; пример см. `tmp.env.add`.

## Быстрый старт
1) Заполните `.env` по образцу `tmp.env.add` (важно: ключи AstronomyAPI без пробелов/переносов).
2) Соберите и поднимите:
```bash
docker-compose up --build
```
3) Веб: http://localhost:8080 (Nginx → php_web). Rust API доступен из сети compose на порту 3000.

## Тесты
- Rust (в контейнере rust:1-slim):
```bash
docker run --rm -v "$PWD/services/rust-iss:/app" -w /app rust:1-slim \
  bash -lc 'apt-get update && apt-get install -y --no-install-recommends pkg-config libssl-dev ca-certificates >/dev/null && export PATH=$PATH:/usr/local/cargo/bin && cargo test --quiet'
```
- Фронтовые:
```bash
node services/php-web/tests/frontend.test.js
```

## Полезно знать
- AstronomyAPI: используется Basic Auth `base64(appId:secret)`, обязательны `latitude/longitude/elevation/from_date/to_date/time/output`
- Rust-ISS маршруты: `/health`, `/last`, `/fetch`, `/iss/trend`, `/osdr/sync`, `/osdr/list`, `/space/:src/latest|summary|refresh`.
- telemetry-cli: крутится под supercronic, пишет CSV в `telemetry_legacy` через COPY, логи — stdout/stderr.