use crate::clients::UpstreamClients;
use crate::config::AppConfig;
use crate::domain::{IssTrend, OsdrUpsert, SpaceCacheItem};
use crate::error::ApiError;
use crate::repo::{CacheRepo, IssRepo, OsdrRepo};
use chrono::{DateTime, NaiveDateTime, TimeZone, Utc};
use serde_json::Value;

#[derive(Clone)]
pub struct IssService {
    repo: IssRepo,
    clients: UpstreamClients,
    cfg: AppConfig,
}

impl IssService {
    pub fn new(repo: IssRepo, clients: UpstreamClients, cfg: AppConfig) -> Self {
        Self { repo, clients, cfg }
    }

    pub async fn fetch_and_store(&self) -> Result<(), ApiError> {
        let payload = self.clients.fetch_iss().await?;
        self.repo
            .insert_log(&self.cfg.where_iss_url, &payload)
            .await?;
        Ok(())
    }

    pub async fn last(&self) -> Result<Option<(i64, DateTime<Utc>, String, Value)>, ApiError> {
        Ok(self.repo.last().await?)
    }

    pub async fn trend(&self, limit: i64) -> Result<IssTrend, ApiError> {
        let points = self.repo.trend(limit).await?;
        if points.len() < 2 {
            return Ok(IssTrend {
                movement: false,
                delta_km: 0.0,
                dt_sec: 0.0,
                points,
            });
        }
        let mut delta_km = 0.0;
        let mut movement = false;
        for win in points.windows(2) {
            if let [a, b] = win {
                if let (Some(lat1), Some(lon1), Some(lat2), Some(lon2)) =
                    (a.latitude, a.longitude, b.latitude, b.longitude)
                {
                    delta_km += haversine_km(lat1, lon1, lat2, lon2);
                    if delta_km > 0.1 {
                        movement = true;
                    }
                }
            }
        }
        let dt_sec =
            (points.last().unwrap().at - points.first().unwrap().at).num_milliseconds() as f64
                / 1000.0;
        Ok(IssTrend {
            movement,
            delta_km,
            dt_sec,
            points,
        })
    }
}

#[derive(Clone)]
pub struct OsdrService {
    repo: OsdrRepo,
    clients: UpstreamClients,
}

impl OsdrService {
    pub fn new(repo: OsdrRepo, clients: UpstreamClients) -> Self {
        Self { repo, clients }
    }

    pub async fn sync(&self) -> Result<usize, ApiError> {
        let json = self.clients.fetch_osdr().await?;
        let items = normalize_osdr_items(&json);
        let mut written = 0usize;
        for item in items {
            self.repo.upsert(item).await?;
            written += 1;
        }
        Ok(written)
    }

    pub async fn list(&self, limit: i64) -> Result<Vec<crate::domain::OsdrItem>, ApiError> {
        Ok(self.repo.list(limit).await?)
    }
}

#[derive(Clone)]
pub struct SpaceService {
    cache_repo: CacheRepo,
    clients: UpstreamClients,
}

impl SpaceService {
    pub fn new(cache_repo: CacheRepo, clients: UpstreamClients) -> Self {
        Self {
            cache_repo,
            clients,
        }
    }

    pub async fn apod(&self) -> Result<(), ApiError> {
        let json = self.clients.fetch_apod().await?;
        self.cache_repo.write("apod", json).await?;
        Ok(())
    }

    pub async fn neo(&self, start: &str, end: &str) -> Result<(), ApiError> {
        let json = self.clients.fetch_neo(start, end).await?;
        self.cache_repo.write("neo", json).await?;
        Ok(())
    }

    pub async fn donki(&self, kind: &str, start: &str, end: &str) -> Result<(), ApiError> {
        let json = self.clients.fetch_donki(kind, start, end).await?;
        let key = match kind {
            "CME" => "cme".to_string(),
            "FLR" => "flr".to_string(),
            _ => kind.to_lowercase(),
        };
        self.cache_repo.write(&key, json).await?;
        Ok(())
    }

    pub async fn spacex(&self) -> Result<(), ApiError> {
        let json = self.clients.fetch_spacex().await?;
        self.cache_repo.write("spacex", json).await?;
        Ok(())
    }

    pub async fn latest(&self, source: &str) -> Result<Option<SpaceCacheItem>, ApiError> {
        Ok(self.cache_repo.latest(source).await?)
    }
}

pub(crate) fn haversine_km(lat1: f64, lon1: f64, lat2: f64, lon2: f64) -> f64 {
    let rlat1 = lat1.to_radians();
    let rlat2 = lat2.to_radians();
    let dlat = (lat2 - lat1).to_radians();
    let dlon = (lon2 - lon1).to_radians();
    let a = (dlat / 2.0).sin().powi(2)
        + rlat1.cos() * rlat2.cos() * (dlon / 2.0).sin().powi(2);
    let c = 2.0 * a.sqrt().atan2((1.0 - a).sqrt());
    6371.0 * c
}

pub(crate) fn s_pick(v: &Value, keys: &[&str]) -> Option<String> {
    for k in keys {
        if let Some(x) = v.get(*k) {
            if let Some(s) = x.as_str() {
                if !s.is_empty() {
                    return Some(s.to_string());
                }
            } else if x.is_number() {
                return Some(x.to_string());
            }
        }
    }
    None
}

pub(crate) fn t_pick(v: &Value, keys: &[&str]) -> Option<DateTime<Utc>> {
    for k in keys {
        if let Some(x) = v.get(*k) {
            if let Some(s) = x.as_str() {
                if let Ok(dt) = s.parse::<DateTime<Utc>>() {
                    return Some(dt);
                }
                if let Ok(ndt) = NaiveDateTime::parse_from_str(s, "%Y-%m-%d %H:%M:%S") {
                    return Some(Utc.from_utc_datetime(&ndt));
                }
            } else if let Some(n) = x.as_i64() {
                if let Some(dt) = Utc.timestamp_opt(n, 0).single() {
                    return Some(dt);
                }
            }
        }
    }
    None
}

pub(crate) fn normalize_osdr_items(json: &Value) -> Vec<OsdrUpsert> {
    let arr = if let Some(a) = json.as_array() {
        a.clone()
    } else if let Some(v) = json.get("items").and_then(|x| x.as_array()) {
        v.clone()
    } else if let Some(v) = json.get("results").and_then(|x| x.as_array()) {
        v.clone()
    } else {
        vec![json.clone()]
    };

    arr.into_iter()
        .map(|item| {
            let id = s_pick(&item, &["dataset_id", "id", "uuid", "studyId", "accession", "osdr_id"]);
            let title = s_pick(&item, &["title", "name", "label"]);
            let status = s_pick(&item, &["status", "state", "lifecycle"]);
            let updated = t_pick(&item, &["updated", "updated_at", "modified", "lastUpdated", "timestamp"]);
            OsdrUpsert {
                dataset_id: id,
                title,
                status,
                updated_at: updated,
                raw: item,
            }
        })
        .collect()
}

