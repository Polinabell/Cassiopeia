# 🚀 Cassiopeia — Space Dashboard

Многосервисный стенд на Docker Compose для мониторинга и визуализации космических данных.

## 📋 Содержание
- [Архитектура](#архитектура)
- [Функциональные модули](#функциональные-модули)
- [База данных](#база-данных)
- [Redis кэширование](#redis-кэширование)
- [Переменные окружения](#переменные-окружения)
- [Быстрый старт](#быстрый-старт)
- [Тесты](#тесты)
- [API Reference](#api-reference)

## 🏗 Архитектура

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Browser   │───▶│    Nginx    │───▶│   PHP Web   │
└─────────────┘    └─────────────┘    └──────┬──────┘
                                             │
                   ┌─────────────────────────┼─────────────────────────┐
                   │                         │                         │
                   ▼                         ▼                         ▼
           ┌─────────────┐           ┌─────────────┐           ┌─────────────┐
           │  Rust ISS   │           │    Redis    │           │ PostgreSQL  │
           │   Service   │           │    Cache    │           │      DB     │
           └──────┬──────┘           └─────────────┘           └─────────────┘
                  │                                                    ▲
                  ├────────────────────────────────────────────────────┤
                  │                                                    │
     ┌────────────┴────────────┐                             ┌────────┴────────┐
     │    External APIs        │                             │  Telemetry CLI  │
     │ (NASA, WhereTheISS,     │                             │  (Python cron)  │
     │  SpaceX, AstronomyAPI)  │                             └─────────────────┘
     └─────────────────────────┘
```

### Потоки данных
1. **Веб-запросы**: Пользователь → Nginx → PHP Web → Rust ISS → PostgreSQL
2. **Фоновые задачи**: Rust scheduler → External APIs → PostgreSQL (space_cache)
3. **Телеметрия**: Python cron → CSV → PostgreSQL (telemetry_legacy)
4. **Кэширование**: PHP Web → Redis (sessions, cache)

### Слои Rust-сервиса
- `config` — загрузка конфигурации из env
- `clients` — HTTP-клиенты с retry/timeout
- `services` — бизнес-логика (IssService, OsdrService, SpaceService)
- `repo` — репозитории для работы с БД
- `routes` — HTTP-роутинг (Axum)
- `scheduler` — фоновые задачи с pg advisory lock
- `error` — единый JSON envelope (`ok/data/error`)

## 📱 Функциональные модули

| Страница | URL | Описание |
|----------|-----|----------|
| **Dashboard** | `/dashboard` | Обзорная панель со статистикой |
| **ISS Tracker** | `/iss` | Положение МКС, карта, графики |
| **Telemetry** | `/telemetry` | Данные датчиков с сортировкой, экспорт CSV/XLSX |
| **OSDR** | `/osdr` | NASA Open Science Data Repository |
| **Space Data** | `/space` | APOD, NEO, DONKI, SpaceX |
| **JWST Gallery** | `/jwst` | Изображения James Webb с фильтрами |
| **Astro Events** | `/astro` | Астрономические события (AstronomyAPI) |

### Возможности
- ✅ **Сортировка** — по любому столбцу (asc/desc)
- ✅ **Фильтрация** — поиск по ключевым словам
- ✅ **Экспорт** — CSV и XLSX с правильным форматированием
- ✅ **Анимации** — плавные переходы и эффекты
- ✅ **Адаптивность** — работает на мобильных устройствах

## 💾 База данных

```sql
-- ISS лог загрузок
CREATE TABLE iss_fetch_log (
    id BIGSERIAL PRIMARY KEY,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    source_url TEXT NOT NULL,
    payload JSONB NOT NULL
);

-- NASA OSDR
CREATE TABLE osdr_items (
    id BIGSERIAL PRIMARY KEY,
    dataset_id TEXT,
    title TEXT,
    status TEXT,
    updated_at TIMESTAMPTZ,
    inserted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    raw JSONB NOT NULL
);

-- Space cache (APOD, NEO, DONKI, SpaceX)
CREATE TABLE space_cache (
    id BIGSERIAL PRIMARY KEY,
    source TEXT NOT NULL,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    payload JSONB NOT NULL
);

-- Телеметрия
CREATE TABLE telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL,
    temp NUMERIC(6,2) NOT NULL,
    source_file TEXT NOT NULL
);

-- CMS
CREATE TABLE cms_pages (...);
CREATE TABLE cms_blocks (...);
```

## 🔴 Redis кэширование

Redis используется для:
- **Session storage** — хранение сессий Laravel
- **Cache driver** — кэширование API-ответов
- **Rate limiting** — ограничение частоты запросов

Конфигурация в `docker-compose.yml`:
```yaml
redis:
  image: redis:7-alpine
  command: redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru
```

## ⚙️ Переменные окружения

### Обязательные
| Переменная | Описание | Пример |
|------------|----------|--------|
| `POSTGRES_USER` | Пользователь БД | `monouser` |
| `POSTGRES_PASSWORD` | Пароль БД | `monopass` |
| `POSTGRES_DB` | Имя БД | `monolith` |

### Опциональные (внешние API)
| Переменная | Описание |
|------------|----------|
| `NASA_API_KEY` | Ключ NASA API |
| `ASTRO_APP_ID` | ID приложения AstronomyAPI |
| `ASTRO_APP_SECRET` | Секрет AstronomyAPI |
| `JWST_API_KEY` | Ключ JWST API |

## 🚀 Быстрый старт

```bash
# 1. Клонировать репозиторий
git clone <repo-url>
cd he-path-of-the-samurai

# 2. Создать .env (опционально, для внешних API)
cp tmp.env.add .env
# Отредактировать .env

# 3. Запустить
docker-compose up --build

# 4. Открыть в браузере
open http://localhost:8080
```

### Порты
- **8080** — Web UI (Nginx)
- **8081** — Rust API (прямой доступ)
- **5432** — PostgreSQL
- **6379** — Redis

## 🧪 Тесты

### Rust (unit tests)
```bash
docker run --rm -v "$PWD/services/rust-iss:/app" -w /app rust:1-slim \
  bash -lc 'apt-get update && apt-get install -y --no-install-recommends pkg-config libssl-dev ca-certificates >/dev/null && cargo test --quiet'
```

### Frontend (Node.js)
```bash
node services/php-web/tests/frontend.test.js
```

## 📡 API Reference

### Rust ISS Service (порт 3000)

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `/health` | GET | Проверка здоровья |
| `/last` | GET | Последняя позиция МКС |
| `/iss/trend` | GET | Тренд движения МКС |
| `/osdr/list` | GET | Список OSDR датасетов |
| `/space/apod` | GET | NASA APOD |
| `/space/neo` | GET | Near-Earth Objects |
| `/space/donki` | GET | Space Weather |
| `/space/spacex` | GET | SpaceX следующий запуск |

### PHP Web (порт 80)

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `/api/iss/last` | GET | Прокси к Rust ISS |
| `/api/iss/trend` | GET | Прокси к Rust ISS |
| `/api/jwst/feed` | GET | JWST галерея с фильтрами |
| `/api/astro/events` | GET | AstronomyAPI события |
| `/telemetry/download/csv` | GET | Скачать телеметрию CSV |
| `/telemetry/download/xlsx` | GET | Скачать телеметрию XLSX |

## 📊 Telemetry CLI

Python-сервис для генерации телеметрии:
- Запуск по расписанию (supercronic)
- Генерация CSV с правильным форматированием:
  - `timestamp` — ISO 8601
  - `boolean` — ИСТИНА/ЛОЖЬ
  - `numbers` — числовой формат
  - `strings` — текст
- Загрузка в PostgreSQL через `COPY`

## 🎨 UI/UX

- **Тема**: Космическая (Space Grotesk font, градиенты)
- **Анимации**: fadeIn, slideIn, pulse
- **Звёзды**: CSS-анимация мерцания
- **Карточки**: Glassmorphism эффект
- **Графики**: Chart.js
- **Карты**: Leaflet.js

---

**Версия**: 2.0  
**Автор**: Cassiopeia Team  
**Лицензия**: MIT
