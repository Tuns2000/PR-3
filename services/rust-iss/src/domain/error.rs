use axum::{
    http::StatusCode,
    response::{IntoResponse, Response},
    Json,
};
use serde::{Deserialize, Serialize};
use uuid::Uuid;

#[derive(Debug, Serialize, Deserialize)]
pub struct ApiResponse<T> {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<T>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<ErrorDetail>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ErrorDetail {
    pub code: String,
    pub message: String,
    pub trace_id: String,
}

#[derive(Debug)]
pub enum ApiError {
    UpstreamError(String),
    DatabaseError(sqlx::Error),
    RedisError(redis::RedisError),
    ValidationError(String),
    NotFound,
    RateLimitExceeded,
    InternalError(String),
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let (code, message) = match self {
            ApiError::UpstreamError(msg) => ("UPSTREAM_ERROR", msg),
            ApiError::DatabaseError(e) => ("DB_ERROR", e.to_string()),
            ApiError::RedisError(e) => ("REDIS_ERROR", e.to_string()),
            ApiError::ValidationError(msg) => ("VALIDATION_ERROR", msg),
            ApiError::NotFound => ("NOT_FOUND", "Resource not found".to_string()),
            ApiError::RateLimitExceeded => (
                "RATE_LIMIT_EXCEEDED",
                "Too many requests, please try again later".to_string(),
            ),
            ApiError::InternalError(msg) => ("INTERNAL_ERROR", msg),
        };

        let response = ApiResponse::<()> {
            ok: false,
            data: None,
            error: Some(ErrorDetail {
                code: code.to_string(),
                message,
                trace_id: Uuid::new_v4().to_string(),
            }),
        };

        (StatusCode::OK, Json(response)).into_response()
    }
}

impl<T: Serialize> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self {
            ok: true,
            data: Some(data),
            error: None,
        }
    }

    pub fn error(code: &str, message: String) -> ApiResponse<()> {
        ApiResponse {
            ok: false,
            data: None,
            error: Some(ErrorDetail {
                code: code.to_string(),
                message,
                trace_id: Uuid::new_v4().to_string(),
            }),
        }
    }
}

impl From<sqlx::Error> for ApiError {
    fn from(err: sqlx::Error) -> Self {
        ApiError::DatabaseError(err)
    }
}

impl From<redis::RedisError> for ApiError {
    fn from(err: redis::RedisError) -> Self {
        ApiError::RedisError(err)
    }
}