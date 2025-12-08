use std::time::Duration;

#[derive(Clone, Debug)]
pub struct AppConfig {
    pub database_url: String,
    pub nasa_url: String,
    pub nasa_key: String,
    pub where_iss_url: String,
    pub every_osdr: u64,
    pub every_iss: u64,
    pub every_apod: u64,
    pub every_neo: u64,
    pub every_donki: u64,
    pub every_spacex: u64,
    pub http_timeout: Duration,
    pub http_user_agent: String,
    pub db_max_connections: u32,
    pub osdr_list_limit: i64,
    pub trend_limit_default: i64,
}

impl AppConfig {
    pub fn from_env() -> anyhow::Result<Self> {
        dotenvy::dotenv().ok();
        let database_url = std::env::var("DATABASE_URL")
            .map_err(|_| anyhow::anyhow!("DATABASE_URL is required"))?;

        let nasa_url = env_str(
            "NASA_API_URL",
            "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json",
        );
        let nasa_key = env_str("NASA_API_KEY", "");
        let where_iss_url =
            env_str("WHERE_ISS_URL", "https://api.wheretheiss.at/v1/satellites/25544");

        let http_timeout = Duration::from_secs(env_u64("HTTP_TIMEOUT_SECONDS", 20));
        let http_user_agent = env_str("HTTP_USER_AGENT", "rust_iss/1.0 (+github.com/cursor)");
        let db_max_connections = env_u64("DB_MAX_CONNECTIONS", 8) as u32;

        Ok(Self {
            database_url,
            nasa_url,
            nasa_key,
            where_iss_url,
            db_max_connections,
            every_osdr: env_u64("FETCH_EVERY_SECONDS", 600),
            every_iss: env_u64("ISS_EVERY_SECONDS", 120),
            every_apod: env_u64("APOD_EVERY_SECONDS", 43_200),
            every_neo: env_u64("NEO_EVERY_SECONDS", 7_200),
            every_donki: env_u64("DONKI_EVERY_SECONDS", 3_600),
            every_spacex: env_u64("SPACEX_EVERY_SECONDS", 3_600),
            osdr_list_limit: env_u64("OSDR_LIST_LIMIT", 20) as i64,
            trend_limit_default: env_u64("TREND_LIMIT", 240) as i64,
            http_timeout,
            http_user_agent,
        })
    }
}

fn env_str(key: &str, default: &str) -> String {
    std::env::var(key).unwrap_or_else(|_| default.to_string())
}

fn env_u64(key: &str, default: u64) -> u64 {
    std::env::var(key)
        .ok()
        .and_then(|s| s.parse::<u64>().ok())
        .unwrap_or(default)
}

