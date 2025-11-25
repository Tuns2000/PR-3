use crate::domain::error::{ApiResponse};
use axum::Json;
use chrono::Utc;
use serde::Serialize;

#[derive(Serialize)]
pub struct HealthResponse {
    pub status: String,
    pub timestamp: chrono::DateTime<chrono::Utc>,
    pub version: String,
}

pub async fn health_check() -> Json<ApiResponse<HealthResponse>> {
    let response = HealthResponse {
        status: "ok".to_string(),
        timestamp: Utc::now(),
        version: env!("CARGO_PKG_VERSION").to_string(),
    };

    Json(ApiResponse::success(response))
}