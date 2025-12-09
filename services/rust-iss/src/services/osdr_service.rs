use crate::{
    clients::OsdrClient,
    domain::{error::ApiError, models::OsdrDataset},
    repo::{cache_repo::CacheRepo, osdr_repo::OsdrRepo},
};
use chrono::Utc;

pub struct OsdrService {
    osdr_client: OsdrClient,
    osdr_repo: OsdrRepo,
    cache_repo: CacheRepo,
}

impl OsdrService {
    pub fn new(osdr_client: OsdrClient, osdr_repo: OsdrRepo, cache_repo: CacheRepo) -> Self {
        Self {
            osdr_client,
            osdr_repo,
            cache_repo,
        }
    }

    /// Синхронизация датасетов из NASA OSDR
    pub async fn sync_datasets(&mut self) -> Result<usize, ApiError> {
        tracing::info!("Syncing OSDR datasets from NASA API");

        let api_response = self.osdr_client.fetch_datasets().await?;
        let mut saved_count = 0;

        for api_dataset in api_response.results {
            let release_date = api_dataset
                .release_date
                .and_then(|s| chrono::NaiveDate::parse_from_str(&s, "%Y-%m-%d").ok());

            let dataset = OsdrDataset {
                id: None,
                dataset_id: api_dataset.dataset_id,
                title: api_dataset.title,
                description: api_dataset.description,
                release_date,
                updated_at: Utc::now(),
            };

            self.osdr_repo.save(&dataset).await?;
            saved_count += 1;
        }

        // Инвалидируем кэш списка
        self.cache_repo.delete("osdr:all").await?;

        tracing::info!("OSDR sync complete: {} datasets", saved_count);
        Ok(saved_count)
    }

    /// Получить все датасеты (с кэшированием)
    pub async fn get_all_datasets(&mut self, limit: i32) -> Result<Vec<OsdrDataset>, ApiError> {
        let cache_key = format!("osdr:all:{}", limit);

        // Проверяем кэш (TTL 30 минут)
        if let Some(cached) = self.cache_repo.get::<Vec<OsdrDataset>>(&cache_key).await? {
            tracing::info!("OSDR datasets from cache");
            return Ok(cached);
        }

        // Читаем из БД
        let datasets = self.osdr_repo.get_all(limit).await?;

        // Сохраняем в кэш
        self.cache_repo.set(&cache_key, &datasets, 1800).await?;

        Ok(datasets)
    }

    /// Получить все датасеты (с кэшированием)
    pub async fn fetch_and_cache(&mut self) -> Result<Vec<OsdrDataset>, ApiError> {
        let response = self.osdr_client.fetch_datasets().await?;

        for api_dataset in &response.results {
            let release_date = api_dataset
                .release_date
                .as_ref()
                .and_then(|s| chrono::NaiveDate::parse_from_str(s, "%Y-%m-%d").ok());

            let dataset = OsdrDataset {
                id: None,
                dataset_id: api_dataset.dataset_id.clone(),
                title: api_dataset.title.clone(),
                description: api_dataset.description.clone(),
                release_date,
                updated_at: Utc::now(),
            };

            self.osdr_repo.save(&dataset).await?;
        }

        let datasets = self.osdr_repo.get_all(100).await?;
        self.cache_repo.set("osdr:datasets", &datasets, 3600).await?;

        Ok(datasets)
    }
}