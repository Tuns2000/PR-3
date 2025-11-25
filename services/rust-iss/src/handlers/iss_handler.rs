use crate::{
    domain::{
        error::{ApiError, ApiResponse},
        models::{IssFilterQuery, IssPosition},
    },
    services::IssService,
};
use axum::{extract::{Query, State}, Json};
use std::sync::Arc;
use tokio::sync::Mutex;
use validator::Validate;

pub type SharedIssService = Arc<Mutex<IssService>>;

/// GET /iss/last - Получить последнюю позицию МКС
pub async fn get_last_position(
    State(service): State<SharedIssService>,
) -> Result<Json<ApiResponse<IssPosition>>, ApiError> {
    let mut service = service.lock().await;
    let position = service.get_last_position().await?;
    Ok(Json(ApiResponse::success(position)))
}

/// GET /iss/fetch - Триггер для принудительной загрузки данных
pub async fn fetch_position(
    State(service): State<SharedIssService>,
) -> Result<Json<ApiResponse<IssPosition>>, ApiError> {
    let mut service = service.lock().await;
    let position = service.fetch_and_store().await?;
    Ok(Json(ApiResponse::success(position)))
}

/// GET /iss/history - Получить историю позиций с фильтрацией
pub async fn get_history(
    State(service): State<SharedIssService>,
    Query(query): Query<IssFilterQuery>,
) -> Result<Json<ApiResponse<Vec<IssPosition>>>, ApiError> {
    // Валидация входных данных
    query.validate().map_err(|e| {
        ApiError::ValidationError(format!("Invalid query parameters: {}", e))
    })?;

    let service = service.lock().await;
    let limit = query.limit.unwrap_or(100);
    let history = service.get_history(query.start_date, query.end_date, limit).await?;

    Ok(Json(ApiResponse::success(history)))
}