use axum::{
    extract::{Path, Query, State},
    routing::get,
    Router,
};
use serde::Deserialize;
use sqlx::Row;

use crate::{
    domain::{Health, IssTrend, SpaceCacheItem},
    error::{ApiEnvelope, ApiResult},
    AppState,
};
use chrono::{Days, Utc};

pub fn build_router(state: AppState) -> Router {
    Router::new()
        .route("/health", get(health))
        .route("/last", get(last_iss))
        .route("/fetch", get(trigger_iss))
        .route("/iss/trend", get(iss_trend))
        .route("/osdr/sync", get(osdr_sync))
        .route("/osdr/list", get(osdr_list))
        .route("/space/:src/latest", get(space_latest))
        .route("/space/refresh", get(space_refresh))
        .route("/space/summary", get(space_summary))
        .with_state(state)
}

async fn health() -> ApiResult<Health> {
    Ok(ApiEnvelope::ok(Health {
        status: "ok",
        now: Utc::now(),
    }))
}

async fn last_iss(State(st): State<AppState>) -> ApiResult<serde_json::Value> {
    let last = st.iss.last().await?;
    let payload = last.map(|(id, at, src, json)| {
        serde_json::json!({"id": id, "fetched_at": at, "source_url": src, "payload": json })
    });
    Ok(ApiEnvelope::ok(payload.unwrap_or_else(|| serde_json::json!({"message":"no data"}))))
}

async fn trigger_iss(State(st): State<AppState>) -> ApiResult<serde_json::Value> {
    st.iss.fetch_and_store().await?;
    last_iss(State(st)).await
}

#[derive(Deserialize)]
struct TrendQuery {
    limit: Option<i64>,
}

async fn iss_trend(
    State(st): State<AppState>,
    Query(q): Query<TrendQuery>,
) -> ApiResult<IssTrend> {
    let limit = q
        .limit
        .unwrap_or_else(|| st.cfg.trend_limit_default)
        .clamp(2, 1000);
    let trend = st.iss.trend(limit).await?;
    Ok(ApiEnvelope::ok(trend))
}

async fn osdr_sync(State(st): State<AppState>) -> ApiResult<serde_json::Value> {
    let written = st.osdr.sync().await?;
    Ok(ApiEnvelope::ok(serde_json::json!({ "written": written })))
}

#[derive(Deserialize)]
struct OsdrQuery {
    limit: Option<i64>,
}

async fn osdr_list(
    State(st): State<AppState>,
    Query(q): Query<OsdrQuery>,
) -> ApiResult<serde_json::Value> {
    let limit = q
        .limit
        .unwrap_or(st.cfg.osdr_list_limit)
        .clamp(1, 500);
    let items = st.osdr.list(limit).await?;
    Ok(ApiEnvelope::ok(serde_json::json!({ "items": items })))
}

async fn space_latest(
    Path(src): Path<String>,
    State(st): State<AppState>,
) -> ApiResult<serde_json::Value> {
    let item = st.space.latest(&src).await?;
    let payload = item
        .map(|i| serde_json::json!({ "source": i.source, "fetched_at": i.fetched_at, "payload": i.payload }))
        .unwrap_or_else(|| serde_json::json!({"source": src, "message": "no data"}));
    Ok(ApiEnvelope::ok(payload))
}

#[derive(Deserialize)]
struct RefreshQuery {
    src: Option<String>,
}

async fn space_refresh(
    Query(q): Query<RefreshQuery>,
    State(st): State<AppState>,
) -> ApiResult<serde_json::Value> {
    let list = q
        .src
        .unwrap_or_else(|| "apod,neo,flr,cme,spacex".to_string());
    let mut refreshed = Vec::new();
    for s in list.split(',').map(|x| x.trim().to_lowercase()) {
        match s.as_str() {
            "apod" => {
                let _ = st.space.apod().await;
                refreshed.push("apod");
            }
            "neo" => {
                let today = Utc::now().date_naive();
                let start = (today - Days::new(2)).to_string();
                let end = today.to_string();
                let _ = st.space.neo(&start, &end).await;
                refreshed.push("neo");
            }
            "flr" => {
                let (from, to) = last_days(5);
                let _ = st.space.donki("FLR", &from, &to).await;
                refreshed.push("flr");
            }
            "cme" => {
                let (from, to) = last_days(5);
                let _ = st.space.donki("CME", &from, &to).await;
                refreshed.push("cme");
            }
            "spacex" => {
                let _ = st.space.spacex().await;
                refreshed.push("spacex");
            }
            _ => {}
        }
    }
    Ok(ApiEnvelope::ok(serde_json::json!({ "refreshed": refreshed })))
}

async fn space_summary(State(st): State<AppState>) -> ApiResult<serde_json::Value> {
    let apod = st.space.latest("apod").await?;
    let neo = st.space.latest("neo").await?;
    let flr = st.space.latest("flr").await?;
    let cme = st.space.latest("cme").await?;
    let spacex = st.space.latest("spacex").await?;

    let iss_last = st.iss.last().await?;
    let iss_last = iss_last.map(|(_, at, _, payload)| serde_json::json!({"at": at, "payload": payload}));

    let osdr_count: i64 = sqlx::query("SELECT count(*) AS c FROM osdr_items")
        .fetch_one(&st.pool)
        .await
        .map(|r| r.get::<i64, _>("c"))
        .unwrap_or(0);

    Ok(ApiEnvelope::ok(serde_json::json!({
        "apod": apod.map(item_to_json),
        "neo": neo.map(item_to_json),
        "flr": flr.map(item_to_json),
        "cme": cme.map(item_to_json),
        "spacex": spacex.map(item_to_json),
        "iss": iss_last.unwrap_or(serde_json::json!({})),
        "osdr_count": osdr_count
    })))
}

fn item_to_json(item: SpaceCacheItem) -> serde_json::Value {
    serde_json::json!({"at": item.fetched_at, "payload": item.payload})
}

fn last_days(n: i64) -> (String, String) {
    let to = Utc::now().date_naive();
    let from = to - Days::new(n as u64);
    (from.to_string(), to.to_string())
}

