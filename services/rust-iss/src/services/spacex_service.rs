use crate::{
    clients::SpaceXClient,
    domain::error::ApiError,
    repo::cache_repo::CacheRepo,
};
use serde_json::Value;

pub struct SpaceXService {
    spacex_client: SpaceXClient,
    cache_repo: CacheRepo,
}

impl SpaceXService {
    pub fn new(spacex_client: SpaceXClient, cache_repo: CacheRepo) -> Self {
        Self {
            spacex_client,
            cache_repo,
        }
    }

    /// Получить следующий запуск SpaceX (кэш 1 час)
    pub async fn get_next_launch(&mut self) -> Result<Value, ApiError> {
        if let Some(cached) = self.cache_repo.get::<Value>("spacex:next").await? {
            tracing::info!("SpaceX next launch from cache");
            return Ok(cached);
        }

        let launch = self.spacex_client.fetch_next_launch().await?;
        self.cache_repo.set("spacex:next", &launch, 3600).await?; // 1 час

        Ok(launch)
    }
}