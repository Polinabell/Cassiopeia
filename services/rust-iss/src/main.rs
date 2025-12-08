mod clients;
mod config;
mod domain;
mod error;
mod repo;
mod routes;
mod scheduler;
mod services;
#[cfg(test)]
mod tests;

use axum::Router;
use config::AppConfig;
use repo::{CacheRepo, IssRepo, OsdrRepo};
use services::{IssService, OsdrService, SpaceService};
use sqlx::postgres::PgPoolOptions;
use std::sync::Arc;
use tracing_subscriber::{EnvFilter, FmtSubscriber};

#[derive(Clone)]
pub struct AppState {
    pub cfg: AppConfig,
    pub pool: sqlx::PgPool,
    pub iss: Arc<IssService>,
    pub osdr: Arc<OsdrService>,
    pub space: Arc<SpaceService>,
}

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    let subscriber = FmtSubscriber::builder()
        .with_env_filter(EnvFilter::from_default_env())
        .finish();
    let _ = tracing::subscriber::set_global_default(subscriber);

    let cfg = AppConfig::from_env()?;

    let pool = PgPoolOptions::new()
        .max_connections(cfg.db_max_connections)
        .connect(&cfg.database_url)
        .await?;

    // init schema
    let iss_repo = IssRepo::new(pool.clone());
    let osdr_repo = OsdrRepo::new(pool.clone());
    let cache_repo = CacheRepo::new(pool.clone());
    iss_repo.ensure_schema().await?;
    osdr_repo.ensure_schema().await?;
    cache_repo.ensure_schema().await?;

    let clients = clients::UpstreamClients::new(cfg.clone())?;

    let iss_service = Arc::new(IssService::new(iss_repo, clients.clone(), cfg.clone()));
    let osdr_service = Arc::new(OsdrService::new(osdr_repo, clients.clone()));
    let space_service = Arc::new(SpaceService::new(cache_repo, clients.clone()));

    let state = AppState {
        cfg: cfg.clone(),
        pool: pool.clone(),
        iss: iss_service.clone(),
        osdr: osdr_service.clone(),
        space: space_service.clone(),
    };

    scheduler::spawn_jobs(state.clone());

    let app: Router = routes::build_router(state.clone());

    let listener = tokio::net::TcpListener::bind(("0.0.0.0", 3000)).await?;
    tracing::info!("rust_iss listening on 0.0.0.0:3000");
    axum::serve(listener, app.into_make_service()).await?;
    Ok(())
}
