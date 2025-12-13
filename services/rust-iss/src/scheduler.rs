use std::time::Duration;

use tokio::time::interval;
use tracing::info;

use crate::AppState;

pub fn spawn_jobs(state: AppState) {
    spawn_job(
        "iss",
        state.cfg.every_iss,
        10_001,
        state.clone(),
        |st| async move {
            st.iss.fetch_and_store().await?;
            Ok(())
        },
    );

    spawn_job(
        "osdr",
        state.cfg.every_osdr,
        10_002,
        state.clone(),
        |st| async move {
            let _ = st.osdr.sync().await;
            Ok(())
        },
    );

    spawn_job(
        "apod",
        state.cfg.every_apod,
        10_003,
        state.clone(),
        |st| async move {
            if let Err(e) = st.space.apod().await {
                tracing::warn!(job = "apod", error = ?e, "apod fetch failed");
            }
            Ok(())
        },
    );

    spawn_job(
        "neo",
        state.cfg.every_neo,
        10_004,
        state.clone(),
        |st| async move {
            let today = chrono::Utc::now().date_naive();
            let start = (today - chrono::Days::new(2)).to_string();
            let end = today.to_string();
            if let Err(e) = st.space.neo(&start, &end).await {
                tracing::warn!(job = "neo", error = ?e, "neo fetch failed");
            }
            Ok(())
        },
    );

    spawn_job(
        "donki",
        state.cfg.every_donki,
        10_005,
        state.clone(),
        |st| async move {
            let (from, to) = last_days(5);
            if let Err(e) = st.space.donki("FLR", &from, &to).await {
                tracing::warn!(job = "donki-flr", error = ?e, "donki FLR fetch failed");
            }
            if let Err(e) = st.space.donki("CME", &from, &to).await {
                tracing::warn!(job = "donki-cme", error = ?e, "donki CME fetch failed");
            }
            Ok(())
        },
    );

    spawn_job(
        "spacex",
        state.cfg.every_spacex,
        10_006,
        state,
        |st| async move {
            let _ = st.space.spacex().await;
            Ok(())
        },
    );
}

fn spawn_job<F, Fut>(name: &'static str, seconds: u64, lock_id: i64, state: AppState, f: F)
where
    F: Fn(AppState) -> Fut + Send + 'static + Copy,
    Fut: std::future::Future<Output = Result<(), crate::error::ApiError>> + Send + 'static,
{
    tokio::spawn(async move {
        let mut ticker = interval(Duration::from_secs(seconds));
        loop {
            ticker.tick().await;
            if !try_lock(&state.pool, lock_id).await {
                continue;
            }
            let res = f(state.clone()).await;
            if let Err(e) = res {
                tracing::error!(job = name, error = ?e, "job failed");
            } else {
                info!(job = name, "job done");
            }
            let _ = unlock(&state.pool, lock_id).await;
        }
    });
}

async fn try_lock(pool: &sqlx::PgPool, key: i64) -> bool {
    sqlx::query_scalar::<_, bool>("SELECT pg_try_advisory_lock($1)")
        .bind(key)
        .fetch_one(pool)
        .await
        .unwrap_or(false)
}

async fn unlock(pool: &sqlx::PgPool, key: i64) -> bool {
    sqlx::query_scalar::<_, bool>("SELECT pg_advisory_unlock($1)")
        .bind(key)
        .fetch_one(pool)
        .await
        .unwrap_or(false)
}

fn last_days(n: i64) -> (String, String) {
    let to = chrono::Utc::now().date_naive();
    let from = to - chrono::Days::new(n as u64);
    (from.to_string(), to.to_string())
}

