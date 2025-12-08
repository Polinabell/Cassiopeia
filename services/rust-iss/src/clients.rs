use crate::config::AppConfig;
use crate::error::ApiError;
use anyhow::Context;
use axum::http::StatusCode;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;
use tokio::time::sleep;

#[derive(Clone)]
pub struct UpstreamClients {
    client: Client,
    cfg: AppConfig,
}

impl UpstreamClients {
    pub fn new(cfg: AppConfig) -> anyhow::Result<Self> {
        let client = Client::builder()
            .timeout(cfg.http_timeout)
            .user_agent(cfg.http_user_agent.clone())
            .build()
            .context("build http client")?;
        Ok(Self { client, cfg })
    }

    pub async fn fetch_iss(&self) -> Result<Value, ApiError> {
        let url = &self.cfg.where_iss_url;
        let req = self.client.get(url);
        self.request_json(req, "UPSTREAM_ISS").await
    }

    pub async fn fetch_osdr(&self) -> Result<Value, ApiError> {
        let req = self.client.get(&self.cfg.nasa_url);
        self.request_json(req, "UPSTREAM_OSDR").await
    }

    pub async fn fetch_apod(&self) -> Result<Value, ApiError> {
        let mut req = self
            .client
            .get("https://api.nasa.gov/planetary/apod")
            .query(&[("thumbs", "true")]);
        if !self.cfg.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.cfg.nasa_key)]);
        }
        self.request_json(req, "UPSTREAM_APOD").await
    }

    pub async fn fetch_neo(&self, start: &str, end: &str) -> Result<Value, ApiError> {
        let mut req = self
            .client
            .get("https://api.nasa.gov/neo/rest/v1/feed")
            .query(&[("start_date", start), ("end_date", end)]);
        if !self.cfg.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.cfg.nasa_key)]);
        }
        self.request_json(req, "UPSTREAM_NEO").await
    }

    pub async fn fetch_donki(&self, path: &str, start: &str, end: &str) -> Result<Value, ApiError> {
        let mut req = self
            .client
            .get(&format!("https://api.nasa.gov/DONKI/{path}"))
            .query(&[("startDate", start), ("endDate", end)]);
        if !self.cfg.nasa_key.is_empty() {
            req = req.query(&[("api_key", &self.cfg.nasa_key)]);
        }
        self.request_json(req, "UPSTREAM_DONKI").await
    }

    pub async fn fetch_spacex(&self) -> Result<Value, ApiError> {
        let req = self
            .client
            .get("https://api.spacexdata.com/v4/launches/next");
        self.request_json(req, "UPSTREAM_SPACEX").await
    }

    async fn request_json(&self, req: reqwest::RequestBuilder, code: &str) -> Result<Value, ApiError> {
        let mut last_err = None;
        for _ in 0..3 {
            match req.try_clone().expect("clone req").send().await {
                Ok(resp) => {
                    let status = resp.status();
                    if !status.is_success() {
                        let status_ax = StatusCode::from_u16(status.as_u16())
                            .unwrap_or(StatusCode::BAD_GATEWAY);
                        last_err = Some(ApiError::UpstreamStatus(
                            status_ax,
                            format!("{code} status {}", status.as_u16()),
                        ));
                    } else {
                        let json: Value = resp.json().await?;
                        return Ok(json);
                    }
                }
                Err(e) => last_err = Some(ApiError::Http(e)),
            }
            sleep(Duration::from_millis(250)).await;
        }
        Err(last_err.unwrap_or(ApiError::UpstreamStatus(
            StatusCode::BAD_GATEWAY,
            format!("{code} empty"),
        )))
    }
}

