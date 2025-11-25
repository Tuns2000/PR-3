use crate::{
    domain::error::{ApiError, ApiResponse},
    services::JwstService,
};
use axum::{extract::{Path, State}, Json};
use serde_json::Value;
use std::sync::Arc;
use tokio::sync::Mutex;

pub type SharedJwstService = Arc<Mutex<JwstService>>;

/// GET /jwst/images/:program_id - Получить изображения JWST по программе
pub async fn get_images(
    State(service): State<SharedJwstService>,
    Path(program_id): Path<String>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let images = service.get_images(&program_id).await?;
    Ok(Json(ApiResponse::success(images)))
}