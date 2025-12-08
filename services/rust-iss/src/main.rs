mod clients;
mod config;
mod domain;
mod handlers;
mod middleware;
mod repo;
mod routes;
mod scheduler;
mod services;

use crate::{
    clients::{IssClient, NasaClient, OsdrClient, JwstClient, SpaceXClient},
    config::Config,
    middleware::create_rate_limiter,
    repo::{cache_repo::CacheRepo, iss_repo::IssRepo, osdr_repo::OsdrRepo},
    routes::{create_router, AppState},
    scheduler::Scheduler,
    services::{IssService, NasaService, OsdrService, JwstService, SpaceXService},
};
use sqlx::postgres::PgPoolOptions;
use std::sync::Arc;
use tokio::sync::Mutex;
use tracing::info;
use tracing_subscriber::{layer::SubscriberExt, util::SubscriberInitExt, EnvFilter};

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    // Инициализация логирования
    tracing_subscriber::registry()
        .with(EnvFilter::try_from_default_env().unwrap_or_else(|_| "info".into()))
        .with(tracing_subscriber::fmt::layer())
        .init();

    info!("Starting rust-iss service...");

    // Загрузка конфигурации
    let config = Config::from_env()?;
    config.validate()?;
    info!("Configuration loaded successfully");

    // Подключение к PostgreSQL
    let pg_pool = PgPoolOptions::new()
        .max_connections(10)
        .connect(&config.database_url)
        .await?;
    info!("Connected to PostgreSQL");

    // Инициализация таблиц
    init_database(&pg_pool).await?;

    // Подключение к Redis
    let redis_client = redis::Client::open(config.redis_url.clone())?;
    let redis_conn = redis_client.get_multiplexed_tokio_connection().await?;
    info!("Connected to Redis");

    // Создание клиентов
    let iss_client = IssClient::new(config.where_iss_url.clone())?;
    let osdr_client = OsdrClient::new(config.nasa_api_url.clone(), config.nasa_api_key.clone())?;
    let nasa_client = NasaClient::new(config.nasa_api_key.clone())?;
    let jwst_client = JwstClient::new("https://api.jwstapi.com".to_string(), "".to_string())?;
    let spacex_client = SpaceXClient::new()?;

    // Создание репозиториев
    let iss_repo = IssRepo::new(pg_pool.clone());
    let osdr_repo = OsdrRepo::new(pg_pool.clone());
    let cache_repo = CacheRepo::new(&config.redis_url)?;

    // Создание сервисов
    let iss_service = Arc::new(Mutex::new(IssService::new(
        iss_client,
        iss_repo,
        cache_repo.clone(),
    )));

    let osdr_service = Arc::new(Mutex::new(OsdrService::new(
        osdr_client,
        osdr_repo,
        cache_repo.clone(),
    )));

    let nasa_service = Arc::new(Mutex::new(NasaService::new(
        nasa_client,
        cache_repo.clone(),
    )));

    let jwst_service = Arc::new(Mutex::new(JwstService::new(
        jwst_client,
        cache_repo.clone(),
    )));

    let spacex_service = Arc::new(Mutex::new(SpaceXService::new(
        spacex_client,
        cache_repo.clone(),
    )));

    // Создание rate limiter
    let rate_limiter = create_rate_limiter(config.rate_limit_per_minute);

    // Запуск планировщика с advisory locks
    let scheduler = Arc::new(Scheduler::new(
        config.clone(),
        pg_pool.clone(), // Pass pool for advisory locks
        iss_service.clone(),
        osdr_service.clone(),
        nasa_service.clone(),
        spacex_service.clone(),
    ));
    scheduler.start();

    // Создание роутера
    let app_state = AppState {
        iss_service,
        osdr_service,
        nasa_service,
        jwst_service,
        spacex_service,
        rate_limiter,
    };

    let app = create_router(app_state);

    // Запуск сервера
    let addr = format!("{}:{}", config.host, config.port);
    let listener = tokio::net::TcpListener::bind(&addr).await?;
    info!("Server listening on {}", addr);

    axum::serve(listener, app).await?;

    Ok(())
}

async fn init_database(pool: &sqlx::PgPool) -> Result<(), Box<dyn std::error::Error>> {
    // ISS table
    sqlx::query(
        r#"
        CREATE TABLE IF NOT EXISTS iss_fetch_log (
            id BIGSERIAL PRIMARY KEY,
            latitude DOUBLE PRECISION NOT NULL,
            longitude DOUBLE PRECISION NOT NULL,
            altitude DOUBLE PRECISION NOT NULL,
            velocity DOUBLE PRECISION NOT NULL,
            timestamp TIMESTAMPTZ NOT NULL UNIQUE,
            fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
        "#,
    )
    .execute(pool)
    .await?;

    // OSDR table
    sqlx::query(
        r#"
        CREATE TABLE IF NOT EXISTS osdr_items (
            id BIGSERIAL PRIMARY KEY,
            dataset_id TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            description TEXT,
            release_date TIMESTAMPTZ,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
        "#,
    )
    .execute(pool)
    .await?;

    // Индексы
    sqlx::query("CREATE INDEX IF NOT EXISTS idx_iss_timestamp ON iss_fetch_log(timestamp DESC)")
        .execute(pool)
        .await?;

    sqlx::query("CREATE INDEX IF NOT EXISTS idx_iss_fetched_at ON iss_fetch_log(fetched_at DESC)")
        .execute(pool)
        .await?;

    sqlx::query("CREATE INDEX IF NOT EXISTS idx_osdr_updated_at ON osdr_items(updated_at DESC)")
        .execute(pool)
        .await?;

    info!("Database initialized successfully");
    Ok(())
}
