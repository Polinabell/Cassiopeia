use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use serde_json::Value;

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct Health {
    pub status: &'static str,
    pub now: DateTime<Utc>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct IssPoint {
    pub at: DateTime<Utc>,
    pub latitude: Option<f64>,
    pub longitude: Option<f64>,
    pub altitude: Option<f64>,
    pub velocity: Option<f64>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct IssTrend {
    pub movement: bool,
    pub delta_km: f64,
    pub dt_sec: f64,
    pub points: Vec<IssPoint>,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct OsdrItem {
    pub id: i64,
    pub dataset_id: Option<String>,
    pub title: Option<String>,
    pub status: Option<String>,
    pub updated_at: Option<DateTime<Utc>>,
    pub inserted_at: DateTime<Utc>,
    pub raw: Value,
}

#[derive(Debug, Clone)]
pub struct OsdrUpsert {
    pub dataset_id: Option<String>,
    pub title: Option<String>,
    pub status: Option<String>,
    pub updated_at: Option<DateTime<Utc>>,
    pub raw: Value,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct SpaceCacheItem {
    pub source: String,
    pub fetched_at: DateTime<Utc>,
    pub payload: Value,
}

