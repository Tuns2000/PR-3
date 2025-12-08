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
pub struct ApiResponse<T> {
    pub success: bool,
    pub data: Option<T>,
    pub error: Option<String>,
    pub errors: Option<Vec<ErrorDetail>>,
}

impl<T> ApiResponse<T> {
    pub fn success(data: T) -> Self {
        Self {
            success: true,
            data: Some(data),
            error: None,
            errors: None,
        }
    }

    pub fn error(message: String) -> Self {
        Self {
            success: false,
            data: None,
            error: Some(message),
            errors: None,
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
        let (status, message) = match &self {
            ApiError::DatabaseError(e) => (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()),
            ApiError::RedisError(e) => (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()),
            ApiError::ReqwestError(e) => (StatusCode::BAD_GATEWAY, e.to_string()),
            ApiError::NotFound(msg) => (StatusCode::NOT_FOUND, msg.clone()),
            ApiError::ValidationError(_) => {
                (StatusCode::BAD_REQUEST, "Validation failed".to_string())
            }
            ApiError::InternalError(msg) => (StatusCode::INTERNAL_SERVER_ERROR, msg.clone()),
            ApiError::UpstreamError(msg) => (StatusCode::BAD_GATEWAY, msg.clone()),
        };

        let body = Json(ApiResponse::<()> {
            success: false,
            data: None,
            error: Some(message),
            errors: match self {
                ApiError::ValidationError(errors) => Some(errors),
                _ => None,
            },
        });

        (status, body).into_response()
    }
}