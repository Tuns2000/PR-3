use crate::{
    domain::error::{ApiError, ApiResponse},
    services::NasaService,
};
use axum::{extract::State, Json};
use serde_json::Value;
use std::sync::Arc;
use tokio::sync::Mutex;

pub type SharedNasaService = Arc<Mutex<NasaService>>;

/// GET /nasa/apod - Astronomy Picture of the Day
pub async fn get_apod(
    State(service): State<SharedNasaService>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let apod = service.get_apod().await?;
    Ok(Json(ApiResponse::success(apod)))
}

/// GET /nasa/neo - Near-Earth Objects
pub async fn get_neo(
    State(service): State<SharedNasaService>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let neo = service.get_neo().await?;
    Ok(Json(ApiResponse::success(neo)))
}

/// GET /nasa/donki/flr - DONKI Solar Flare events
pub async fn get_donki_flr(
    State(service): State<SharedNasaService>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let flr = service.get_donki_flr().await?;
    Ok(Json(ApiResponse::success(flr)))
}

/// GET /nasa/donki/cme - DONKI Coronal Mass Ejection events
pub async fn get_donki_cme(
    State(service): State<SharedNasaService>,
) -> Result<Json<ApiResponse<Value>>, ApiError> {
    let mut service = service.lock().await;
    let cme = service.get_donki_cme().await?;
    Ok(Json(ApiResponse::success(cme)))
}