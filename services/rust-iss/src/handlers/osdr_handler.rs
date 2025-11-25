use crate::{
    domain::{
        error::{ApiError, ApiResponse},
        models::OsdrDataset,
    },
    services::OsdrService,
};
use axum::{extract::State, Json};
use serde::Serialize;
use std::sync::Arc;
use tokio::sync::Mutex;

pub type SharedOsdrService = Arc<Mutex<OsdrService>>;

#[derive(Serialize)]
pub struct SyncResponse {
    pub synced_count: usize,
    pub message: String,
}

/// GET /osdr/sync - Синхронизация датасетов из NASA OSDR
pub async fn sync_datasets(
    State(service): State<SharedOsdrService>,
) -> Result<Json<ApiResponse<SyncResponse>>, ApiError> {
    let mut service = service.lock().await;
    let count = service.sync_datasets().await?;

    let response = SyncResponse {
        synced_count: count,
        message: format!("Successfully synced {} datasets", count),
    };

    Ok(Json(ApiResponse::success(response)))
}

/// GET /osdr/list - Получить список датасетов
pub async fn list_datasets(
    State(service): State<SharedOsdrService>,
) -> Result<Json<ApiResponse<Vec<OsdrDataset>>>, ApiError> {
    let mut service = service.lock().await;
    let datasets = service.get_all_datasets(50).await?;

    Ok(Json(ApiResponse::success(datasets)))
}