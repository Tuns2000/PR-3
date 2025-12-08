use axum::{
    body::Body,
    extract::Request,
    http::StatusCode,
    middleware::Next,
    response::{IntoResponse, Response},
    Json,
};
use governor::{
    clock::DefaultClock,
    state::{InMemoryState, NotKeyed},
    Quota, RateLimiter,
};
use std::{num::NonZeroU32, sync::Arc};
use crate::domain::error::ApiResponse;

pub type SharedRateLimiter = Arc<RateLimiter<NotKeyed, InMemoryState, DefaultClock>>;

pub fn create_rate_limiter(requests_per_minute: u32) -> SharedRateLimiter {
    let quota = Quota::per_minute(NonZeroU32::new(requests_per_minute).unwrap());
    Arc::new(RateLimiter::direct(quota))
}

pub async fn rate_limit_middleware(
    axum::extract::State(limiter): axum::extract::State<SharedRateLimiter>,
    request: Request,
    next: Next,
) -> Response {
    if limiter.check().is_err() {
        let error_response = ApiResponse::<()>::error(
            "RATE_LIMIT_EXCEEDED: Too many requests, please try again later".to_string(),
        );
        return (StatusCode::TOO_MANY_REQUESTS, Json(error_response)).into_response();
    }

    next.run(request).await
}