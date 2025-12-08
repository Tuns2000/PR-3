use crate::{
    domain::{
        error::{ApiError, ApiResponse, ErrorDetail},
        models::{IssHistoryQuery, IssPosition},
    },
    services::IssService,
    AppState,
};
use axum::{extract::{Query, State}, Json};
use validator::Validate;

/// GET /iss/current - Получить текущую позицию МКС
pub async fn get_current_position(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<IssPosition>>, ApiError> {
    let mut service = state.iss_service.lock().await;
    let position = service.get_current().await?;
    Ok(Json(ApiResponse::success(position)))
}

/// GET /iss/fetch - Триггер для принудительной загрузки данных
pub async fn fetch_position(
    State(state): State<AppState>,
) -> Result<Json<ApiResponse<IssPosition>>, ApiError> {
    let mut service = state.iss_service.lock().await;
    let position = service.fetch_and_save().await?;
    Ok(Json(ApiResponse::success(position)))
}

/// GET /iss/history - Получить историю позиций с фильтрацией
pub async fn get_history(
    State(state): State<AppState>,
    Query(query): Query<IssHistoryQuery>,
) -> Result<Json<ApiResponse<Vec<IssPosition>>>, ApiError> {
    query.validate().map_err(|e| {
        ApiError::ValidationError(vec![ErrorDetail {
            field: "query".to_string(),
            message: format!("Invalid query parameters: {}", e),
        }])
    })?;

    let mut service = state.iss_service.lock().await;
    let limit = query.limit.unwrap_or(100);
    let history = service.get_history(query.start_date, query.end_date, limit).await?;

    Ok(Json(ApiResponse::success(history)))
}