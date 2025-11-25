use crate::domain::{models::IssPosition, error::ApiError};
use chrono::{DateTime, Utc};
use sqlx::PgPool;

pub struct IssRepo {
    pool: PgPool,
}

impl IssRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    /// UPSERT: вставка с обновлением при конфликте по бизнес-ключу (timestamp)
    /// Преимущество: предотвращает дубликаты, в отличие от слепого INSERT
    pub async fn upsert(&self, pos: &IssPosition) -> Result<(), ApiError> {
        sqlx::query!(
            r#"
            INSERT INTO iss_fetch_log (latitude, longitude, altitude, velocity, timestamp, fetched_at)
            VALUES ($1, $2, $3, $4, $5, $6)
            ON CONFLICT (timestamp) 
            DO UPDATE SET
                latitude = EXCLUDED.latitude,
                longitude = EXCLUDED.longitude,
                altitude = EXCLUDED.altitude,
                velocity = EXCLUDED.velocity,
                fetched_at = EXCLUDED.fetched_at
            "#,
            pos.latitude,
            pos.longitude,
            pos.altitude,
            pos.velocity,
            pos.timestamp,
            pos.fetched_at
        )
        .execute(&self.pool)
        .await
        .map_err(ApiError::DatabaseError)?;

        Ok(())
    }

    pub async fn get_last(&self) -> Result<Option<IssPosition>, ApiError> {
        sqlx::query_as!(
            IssPosition,
            r#"
            SELECT id, latitude, longitude, altitude, velocity, timestamp, fetched_at
            FROM iss_fetch_log
            ORDER BY timestamp DESC
            LIMIT 1
            "#
        )
        .fetch_optional(&self.pool)
        .await
        .map_err(ApiError::DatabaseError)
    }

    pub async fn get_history(
        &self,
        start: Option<DateTime<Utc>>,
        end: Option<DateTime<Utc>>,
        limit: i32,
    ) -> Result<Vec<IssPosition>, ApiError> {
        let start = start.unwrap_or_else(|| Utc::now() - chrono::Duration::days(7));
        let end = end.unwrap_or_else(Utc::now);

        sqlx::query_as!(
            IssPosition,
            r#"
            SELECT id, latitude, longitude, altitude, velocity, timestamp, fetched_at
            FROM iss_fetch_log
            WHERE timestamp BETWEEN $1 AND $2
            ORDER BY timestamp DESC
            LIMIT $3
            "#,
            start,
            end,
            limit as i64
        )
        .fetch_all(&self.pool)
        .await
        .map_err(ApiError::DatabaseError)
    }
}