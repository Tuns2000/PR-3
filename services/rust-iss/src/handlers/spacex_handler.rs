use crate::{
    domain::error::{ApiError, ApiResponse},
    services::SpaceXService,
};
use axum::{extract::State, Json};
use serde_json::Value;
use std::sync::Arc;
use tokio::sync::Mutex;

pub type SharedSpaceXService = Arc<Mutex<SpaceXService>>;

/// GET /spacex/next - Получить следующий запуск SpaceX
pub async fn get_next_launch(
    State(service): State<SharedSpaceXService>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let launch = service.get_next_launch().await?;
    Ok(Json(ApiResponse::success(launch)))
}