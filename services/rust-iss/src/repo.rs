use crate::domain::{IssPoint, OsdrItem, OsdrUpsert, SpaceCacheItem};
use anyhow::Context;
use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

#[derive(Clone)]
pub struct IssRepo {
    pool: PgPool,
}

impl IssRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    pub async fn ensure_schema(&self) -> anyhow::Result<()> {
        // ISS
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS iss_fetch_log(
                id BIGSERIAL PRIMARY KEY,
                fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                source_url TEXT NOT NULL,
                payload JSONB NOT NULL
            )",
        )
        .execute(&self.pool)
        .await?;
        Ok(())
    }

    pub async fn insert_log(&self, source_url: &str, payload: &Value) -> anyhow::Result<()> {
        sqlx::query("INSERT INTO iss_fetch_log (source_url, payload) VALUES ($1,$2)")
            .bind(source_url)
            .bind(payload)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn last(&self) -> anyhow::Result<Option<(i64, DateTime<Utc>, String, Value)>> {
        let row_opt = sqlx::query(
            "SELECT id, fetched_at, source_url, payload
             FROM iss_fetch_log
             ORDER BY id DESC LIMIT 1",
        )
        .fetch_optional(&self.pool)
        .await?;
        Ok(row_opt.map(|row| {
            (
                row.get("id"),
                row.get("fetched_at"),
                row.get("source_url"),
                row.get("payload"),
            )
        }))
    }

    pub async fn trend(&self, limit: i64) -> anyhow::Result<Vec<IssPoint>> {
        let rows = sqlx::query(
            "SELECT fetched_at, payload
             FROM iss_fetch_log
             ORDER BY id DESC
             LIMIT $1",
        )
        .bind(limit.max(2))
        .fetch_all(&self.pool)
        .await?;
        let mut out = Vec::new();
        for r in rows.into_iter().rev() {
            let payload: Value = r.get("payload");
            out.push(IssPoint {
                at: r.get::<DateTime<Utc>, _>("fetched_at"),
                latitude: pick_f64(&payload, &["latitude", "lat"]),
                longitude: pick_f64(&payload, &["longitude", "lon", "lng"]),
                altitude: pick_f64(&payload, &["altitude", "alt"]),
                velocity: pick_f64(&payload, &["velocity", "vel"]),
            });
        }
        Ok(out)
    }
}

#[derive(Clone)]
pub struct OsdrRepo {
    pool: PgPool,
}

impl OsdrRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    pub async fn ensure_schema(&self) -> anyhow::Result<()> {
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS osdr_items(
                id BIGSERIAL PRIMARY KEY,
                dataset_id TEXT,
                title TEXT,
                status TEXT,
                updated_at TIMESTAMPTZ,
                inserted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                raw JSONB NOT NULL
            )",
        )
        .execute(&self.pool)
        .await?;
        sqlx::query(
            "CREATE UNIQUE INDEX IF NOT EXISTS ux_osdr_dataset_id
             ON osdr_items(dataset_id) WHERE dataset_id IS NOT NULL",
        )
        .execute(&self.pool)
        .await?;
        Ok(())
    }

    pub async fn upsert(&self, item: OsdrUpsert) -> anyhow::Result<()> {
        if let Some(ds) = item.dataset_id.clone() {
            sqlx::query(
                "INSERT INTO osdr_items(dataset_id, title, status, updated_at, raw)
                 VALUES($1,$2,$3,$4,$5)
                 ON CONFLICT (dataset_id) DO UPDATE
                 SET title=EXCLUDED.title,
                     status=EXCLUDED.status,
                     updated_at=EXCLUDED.updated_at,
                     raw=EXCLUDED.raw",
            )
            .bind(ds)
            .bind(item.title)
            .bind(item.status)
            .bind(item.updated_at)
            .bind(item.raw)
            .execute(&self.pool)
            .await?;
        } else {
            sqlx::query(
                "INSERT INTO osdr_items(dataset_id, title, status, updated_at, raw)
                 VALUES($1,$2,$3,$4,$5)",
            )
            .bind::<Option<String>>(None)
            .bind(item.title)
            .bind(item.status)
            .bind(item.updated_at)
            .bind(item.raw)
            .execute(&self.pool)
            .await?;
        }
        Ok(())
    }

    pub async fn list(&self, limit: i64) -> anyhow::Result<Vec<OsdrItem>> {
        let rows = sqlx::query(
            "SELECT id, dataset_id, title, status, updated_at, inserted_at, raw
             FROM osdr_items
             ORDER BY inserted_at DESC
             LIMIT $1",
        )
        .bind(limit)
        .fetch_all(&self.pool)
        .await?;
        Ok(rows
            .into_iter()
            .map(|r| OsdrItem {
                id: r.get("id"),
                dataset_id: r.get("dataset_id"),
                title: r.get("title"),
                status: r.get("status"),
                updated_at: r.get("updated_at"),
                inserted_at: r.get("inserted_at"),
                raw: r.get("raw"),
            })
            .collect())
    }
}

#[derive(Clone)]
pub struct CacheRepo {
    pool: PgPool,
}

impl CacheRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    pub async fn ensure_schema(&self) -> anyhow::Result<()> {
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS space_cache(
                id BIGSERIAL PRIMARY KEY,
                source TEXT NOT NULL,
                fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                payload JSONB NOT NULL
            )",
        )
        .execute(&self.pool)
        .await?;
        sqlx::query(
            "CREATE INDEX IF NOT EXISTS ix_space_cache_source ON space_cache(source, fetched_at DESC)",
        )
        .execute(&self.pool)
        .await?;
        Ok(())
    }

    pub async fn write(&self, source: &str, payload: Value) -> anyhow::Result<()> {
        sqlx::query("INSERT INTO space_cache(source, payload) VALUES ($1,$2)")
            .bind(source)
            .bind(payload)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn latest(&self, source: &str) -> anyhow::Result<Option<SpaceCacheItem>> {
        let row = sqlx::query(
            "SELECT fetched_at, payload
             FROM space_cache
             WHERE source=$1
             ORDER BY id DESC
             LIMIT 1",
        )
        .bind(source)
        .fetch_optional(&self.pool)
        .await?;
        Ok(row.map(|r| SpaceCacheItem {
            source: source.to_string(),
            fetched_at: r.get("fetched_at"),
            payload: r.get("payload"),
        }))
    }
}

fn pick_f64(v: &Value, keys: &[&str]) -> Option<f64> {
    for k in keys {
        if let Some(x) = v.get(*k) {
            if let Some(f) = x.as_f64() {
                return Some(f);
            }
            if let Some(s) = x.as_str() {
                if let Ok(f) = s.parse::<f64>() {
                    return Some(f);
                }
            }
            if let Some(i) = x.as_i64() {
                return Some(i as f64);
            }
        }
    }
    None
}

