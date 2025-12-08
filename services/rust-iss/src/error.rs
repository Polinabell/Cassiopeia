use axum::{
    http::StatusCode,
    response::{IntoResponse, Response},
    Json,
};
use serde::Serialize;
use thiserror::Error;
use uuid::Uuid;

#[derive(Debug, Serialize)]
pub struct ErrorBody {
    pub code: String,
    pub message: String,
    pub trace_id: String,
}

#[derive(Debug, Serialize)]
pub struct ApiEnvelope<T> {
    pub ok: bool,
    pub data: Option<T>,
    pub error: Option<ErrorBody>,
}

impl<T> ApiEnvelope<T> {
    pub fn ok(data: T) -> Self {
        Self {
            ok: true,
            data: Some(data),
            error: None,
        }
    }
}

impl<T> IntoResponse for ApiEnvelope<T>
where
    T: Serialize,
{
    fn into_response(self) -> Response {
        (StatusCode::OK, Json(self)).into_response()
    }
}

pub type ApiResult<T> = Result<ApiEnvelope<T>, ApiError>;

#[derive(Error, Debug)]
pub enum ApiError {
    #[error("db: {0}")]
    Db(#[from] sqlx::Error),
    #[error("http: {0}")]
    Http(#[from] reqwest::Error),
    #[error("upstream_status {0}")]
    UpstreamStatus(StatusCode, String),
    #[error("invalid: {0}")]
    Invalid(String),
}

impl ApiError {
    fn code(&self) -> &'static str {
        match self {
            ApiError::Db(_) => "DB_ERROR",
            ApiError::Http(_) => "HTTP_ERROR",
            ApiError::UpstreamStatus(_, _) => "UPSTREAM_STATUS",
            ApiError::Invalid(_) => "INVALID_INPUT",
        }
    }

    fn message(&self) -> String {
        match self {
            ApiError::Db(e) => e.to_string(),
            ApiError::Http(e) => e.to_string(),
            ApiError::UpstreamStatus(_, m) => m.clone(),
            ApiError::Invalid(m) => m.clone(),
        }
    }
}

impl From<anyhow::Error> for ApiError {
    fn from(value: anyhow::Error) -> Self {
        ApiError::Invalid(value.to_string())
    }
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let trace = Uuid::new_v4().to_string();
        let status = match self {
            ApiError::Invalid(_) => StatusCode::BAD_REQUEST,
            ApiError::UpstreamStatus(code, _) => code,
            _ => StatusCode::INTERNAL_SERVER_ERROR,
        };
        let body = ApiEnvelope::<serde_json::Value> {
            ok: false,
            data: None,
            error: Some(ErrorBody {
                code: self.code().to_string(),
                message: self.message(),
                trace_id: trace.clone(),
            }),
        };
        tracing::error!(trace_id = %trace, error = ?self, "api error");
        let mut resp = (status, Json(body)).into_response();
        *resp.status_mut() = StatusCode::OK; // перешиваем под требование: всегда 200
        resp
    }
}

