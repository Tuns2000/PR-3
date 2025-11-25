use crate::{
    handlers::{
        health_check, 
        get_last_position, fetch_position, get_history, SharedIssService,
        sync_datasets, list_datasets, SharedOsdrService,
        get_apod, get_neo, get_donki_flr, get_donki_cme, SharedNasaService,
        get_images, SharedJwstService,
        get_next_launch, SharedSpaceXService,
    },
    middleware::{rate_limit_middleware, request_id_middleware, SharedRateLimiter},
};
use axum::{
    middleware,
    routing::get,
    Router,
};
use tower_http::{
    cors::CorsLayer,
    trace::TraceLayer,
};

pub struct AppState {
    pub iss_service: SharedIssService,
    pub osdr_service: SharedOsdrService,
    pub nasa_service: SharedNasaService,
    pub jwst_service: SharedJwstService,
    pub spacex_service: SharedSpaceXService,
    pub rate_limiter: SharedRateLimiter,
}

pub fn create_router(state: AppState) -> Router {
    // ISS routes
    let iss_routes = Router::new()
        .route("/last", get(get_last_position))
        .route("/fetch", get(fetch_position))
        .route("/history", get(get_history))
        .with_state(state.iss_service.clone());

    // OSDR routes
    let osdr_routes = Router::new()
        .route("/sync", get(sync_datasets))
        .route("/list", get(list_datasets))
        .with_state(state.osdr_service.clone());

    // NASA routes
    let nasa_routes = Router::new()
        .route("/apod", get(get_apod))
        .route("/neo", get(get_neo))
        .route("/donki/flr", get(get_donki_flr))
        .route("/donki/cme", get(get_donki_cme))
        .with_state(state.nasa_service.clone());

    // JWST routes
    let jwst_routes = Router::new()
        .route("/images/:program_id", get(get_images))
        .with_state(state.jwst_service.clone());

    // SpaceX routes
    let spacex_routes = Router::new()
        .route("/next", get(get_next_launch))
        .with_state(state.spacex_service.clone());

    // Main router
    Router::new()
        .route("/health", get(health_check))
        .nest("/iss", iss_routes)
        .nest("/osdr", osdr_routes)
        .nest("/nasa", nasa_routes)
        .nest("/jwst", jwst_routes)
        .nest("/spacex", spacex_routes)
        // Middleware
        .layer(middleware::from_fn(request_id_middleware))
        .layer(middleware::from_fn_with_state(
            state.rate_limiter.clone(),
            rate_limit_middleware,
        ))
        .layer(TraceLayer::new_for_http())
        .layer(CorsLayer::permissive())
}