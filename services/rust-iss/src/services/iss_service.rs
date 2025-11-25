use crate::{
    clients::IssClient,
    domain::{error::ApiError, models::IssPosition},
    repo::{cache_repo::CacheRepo, iss_repo::IssRepo},
};
use chrono::{DateTime, TimeZone, Utc};

pub struct IssService {
    iss_client: IssClient,
    iss_repo: IssRepo,
    cache_repo: CacheRepo,
}

impl IssService {
    pub fn new(iss_client: IssClient, iss_repo: IssRepo, cache_repo: CacheRepo) -> Self {
        Self {
            iss_client,
            iss_repo,
            cache_repo,
        }
    }

    /// Получить последнюю позицию МКС (с кэшированием)
    pub async fn get_last_position(&mut self) -> Result<IssPosition, ApiError> {
        // Проверяем кэш Redis (TTL 5 минут)
        if let Some(cached) = self.cache_repo.get::<IssPosition>("iss:last").await? {
            tracing::info!("ISS position from cache");
            return Ok(cached);
        }

        // Кэш промах - читаем из БД
        if let Some(pos) = self.iss_repo.get_last().await? {
            // Сохраняем в кэш
            self.cache_repo.set("iss:last", &pos, 300).await?;
            return Ok(pos);
        }

        Err(ApiError::NotFound)
    }

    /// Загрузить данные из внешнего API и сохранить в БД
    pub async fn fetch_and_store(&mut self) -> Result<IssPosition, ApiError> {
        tracing::info!("Fetching ISS position from external API");

        // Получаем данные из WhereTheISS
        let api_data = self.iss_client.fetch_current_position().await?;

        // Конвертируем Unix timestamp в DateTime<Utc>
        let timestamp = Utc
            .timestamp_opt(api_data.timestamp, 0)
            .single()
            .ok_or_else(|| ApiError::InternalError("Invalid timestamp".to_string()))?;

        let position = IssPosition {
            id: 0, // будет заполнено БД
            latitude: api_data.latitude,
            longitude: api_data.longitude,
            altitude: api_data.altitude,
            velocity: api_data.velocity,
            timestamp,
            fetched_at: Utc::now(),
        };

        // UPSERT в БД (предотвращает дубликаты по timestamp)
        self.iss_repo.upsert(&position).await?;

        // Инвалидируем кэш
        self.cache_repo.delete("iss:last").await?;

        tracing::info!("ISS position saved: lat={}, lon={}", position.latitude, position.longitude);

        Ok(position)
    }

    /// Получить историю позиций с фильтрацией
    pub async fn get_history(
        &self,
        start: Option<DateTime<Utc>>,
        end: Option<DateTime<Utc>>,
        limit: i32,
    ) -> Result<Vec<IssPosition>, ApiError> {
        self.iss_repo.get_history(start, end, limit).await
    }
}