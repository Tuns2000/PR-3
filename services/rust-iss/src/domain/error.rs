use axum::{
    http::StatusCode,
    response::{IntoResponse, Response},
    Json,
};
use serde::{Deserialize, Serialize};
use std::fmt;

#[derive(Debug, Serialize, Deserialize)]
pub struct ErrorDetail {
    pub field: String,
    pub message: String,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ErrorInfo {
    pub code: String,
    pub message: String,
    pub trace_id: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ApiResponse<T> {
    pub ok: bool,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub data: Option<T>,
    #[serde(skip_serializing_if = "Option::is_none")]
    pub error: Option<ErrorInfo>,
}

impl<T> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self {
            ok: true,
            data: Some(data),
            error: None,
        }
    }

    pub fn error(code: String, message: String, trace_id: Option<String>) -> Self {
        Self {
            ok: false,
            data: None,
            error: Some(ErrorInfo {
                code,
                message,
                trace_id,
            }),
        }
    }
}

#[derive(Debug)]
pub enum ApiError {
    DatabaseError(sqlx::Error),
    RedisError(redis::RedisError),
    ReqwestError(reqwest::Error),
    NotFound(String),
    ValidationError(Vec<ErrorDetail>),
    InternalError(String),
    UpstreamError(String), // ✅ ДОБАВЛЕНО
}

impl fmt::Display for ApiError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            ApiError::DatabaseError(e) => write!(f, "Database error: {}", e),
            ApiError::RedisError(e) => write!(f, "Redis error: {}", e),
            ApiError::ReqwestError(e) => write!(f, "HTTP error: {}", e),
            ApiError::NotFound(msg) => write!(f, "Not found: {}", msg),
            ApiError::ValidationError(errors) => {
                write!(f, "Validation error: {} fields", errors.len())
            }
            ApiError::InternalError(msg) => write!(f, "Internal error: {}", msg),
            ApiError::UpstreamError(msg) => write!(f, "Upstream error: {}", msg),
        }
    }
}

impl std::error::Error for ApiError {
    fn source(&self) -> Option<&(dyn std::error::Error + 'static)> {
        match self {
            ApiError::DatabaseError(e) => Some(e),
            ApiError::RedisError(e) => Some(e),
            ApiError::ReqwestError(e) => Some(e),
            _ => None,
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

impl From<reqwest::Error> for ApiError {
    fn from(err: reqwest::Error) -> Self {
        ApiError::ReqwestError(err)
    }
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        use uuid::Uuid;
        
        let trace_id = Uuid::new_v4().to_string();
        
        let (code, message) = match &self {
            ApiError::DatabaseError(e) => ("DATABASE_ERROR".to_string(), e.to_string()),
            ApiError::RedisError(e) => ("CACHE_ERROR".to_string(), e.to_string()),
            ApiError::ReqwestError(e) => {
                let code = if let Some(status) = e.status() {
                    format!("UPSTREAM_{}", status.as_u16())
                } else {
                    "UPSTREAM_ERROR".to_string()
                };
                (code, e.to_string())
            }
            ApiError::NotFound(msg) => ("NOT_FOUND".to_string(), msg.clone()),
            ApiError::ValidationError(_) => ("VALIDATION_ERROR".to_string(), "Validation failed".to_string()),
            ApiError::InternalError(msg) => ("INTERNAL_ERROR".to_string(), msg.clone()),
            ApiError::UpstreamError(msg) => ("UPSTREAM_ERROR".to_string(), msg.clone()),
        };

        // ✅ Всегда HTTP 200 для предсказуемости
        let body = Json(ApiResponse::<()>::error(
            code,
            message,
            Some(trace_id),
        ));

        (StatusCode::OK, body).into_response()
    }
}