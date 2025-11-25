use crate::{
    clients::NasaClient,
    domain::error::ApiError,
    repo::cache_repo::CacheRepo,
};
use chrono::{Duration, Utc};
use serde_json::Value;

pub struct NasaService {
    nasa_client: NasaClient,
    cache_repo: CacheRepo,
}

impl NasaService {
    pub fn new(nasa_client: NasaClient, cache_repo: CacheRepo) -> Self {
        Self {
            nasa_client,
            cache_repo,
        }
    }

    /// Получить Astronomy Picture of the Day (кэш 12 часов)
    pub async fn get_apod(&mut self) -> Result<Value, ApiError> {
        if let Some(cached) = self.cache_repo.get::<Value>("nasa:apod").await? {
            tracing::info!("APOD from cache");
            return Ok(cached);
        }

        let apod = self.nasa_client.fetch_apod().await?;
        self.cache_repo.set("nasa:apod", &apod, 43200).await?; // 12 часов

        Ok(apod)
    }

    /// Получить Near-Earth Objects (кэш 2 часа)
    pub async fn get_neo(&mut self) -> Result<Value, ApiError> {
        let today = Utc::now().format("%Y-%m-%d").to_string();
        let week_later = (Utc::now() + Duration::days(7)).format("%Y-%m-%d").to_string();

        let cache_key = format!("nasa:neo:{}:{}", today, week_later);

        if let Some(cached) = self.cache_repo.get::<Value>(&cache_key).await? {
            tracing::info!("NEO from cache");
            return Ok(cached);
        }

        let neo = self.nasa_client.fetch_neo(&today, &week_later).await?;
        self.cache_repo.set(&cache_key, &neo, 7200).await?; // 2 часа

        Ok(neo)
    }

    /// Получить DONKI Flare events (кэш 1 час)
    pub async fn get_donki_flr(&mut self) -> Result<Value, ApiError> {
        let today = Utc::now().format("%Y-%m-%d").to_string();
        let month_ago = (Utc::now() - Duration::days(30)).format("%Y-%m-%d").to_string();

        let cache_key = format!("nasa:donki:flr:{}:{}", month_ago, today);

        if let Some(cached) = self.cache_repo.get::<Value>(&cache_key).await? {
            tracing::info!("DONKI FLR from cache");
            return Ok(cached);
        }

        let flr = self.nasa_client.fetch_donki_flr(&month_ago, &today).await?;
        self.cache_repo.set(&cache_key, &flr, 3600).await?; // 1 час

        Ok(flr)
    }

    /// Получить DONKI CME events (кэш 1 час)
    pub async fn get_donki_cme(&mut self) -> Result<Value, ApiError> {
        let today = Utc::now().format("%Y-%m-%d").to_string();
        let month_ago = (Utc::now() - Duration::days(30)).format("%Y-%m-%d").to_string();

        let cache_key = format!("nasa:donki:cme:{}:{}", month_ago, today);

        if let Some(cached) = self.cache_repo.get::<Value>(&cache_key).await? {
            tracing::info!("DONKI CME from cache");
            return Ok(cached);
        }

        let cme = self.nasa_client.fetch_donki_cme(&month_ago, &today).await?;
        self.cache_repo.set(&cache_key, &cme, 3600).await?; // 1 час

        Ok(cme)
    }
}