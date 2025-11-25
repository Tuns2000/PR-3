use crate::{
    clients::JwstClient,
    domain::error::ApiError,
    repo::cache_repo::CacheRepo,
};
use serde_json::Value;

pub struct JwstService {
    jwst_client: JwstClient,
    cache_repo: CacheRepo,
}

impl JwstService {
    pub fn new(jwst_client: JwstClient, cache_repo: CacheRepo) -> Self {
        Self {
            jwst_client,
            cache_repo,
        }
    }

    /// Получить изображения JWST по программе (кэш 30 минут)
    pub async fn get_images(&mut self, program_id: &str) -> Result<Value, ApiError> {
        let cache_key = format!("jwst:images:{}", program_id);

        if let Some(cached) = self.cache_repo.get::<Value>(&cache_key).await? {
            tracing::info!("JWST images from cache for program {}", program_id);
            return Ok(cached);
        }

        let images = self.jwst_client.fetch_images(program_id).await?;
        self.cache_repo.set(&cache_key, &images, 1800).await?; // 30 минут

        Ok(images)
    }
}