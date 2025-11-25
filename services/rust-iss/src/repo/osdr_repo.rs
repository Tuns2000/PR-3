use crate::domain::{models::OsdrDataset, error::ApiError};
use sqlx::PgPool;

pub struct OsdrRepo {
    pool: PgPool,
}

impl OsdrRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    pub async fn upsert(&self, dataset: &OsdrDataset) -> Result<(), ApiError> {
        sqlx::query!(
            r#"
            INSERT INTO osdr_items (dataset_id, title, description, release_date, updated_at)
            VALUES ($1, $2, $3, $4, $5)
            ON CONFLICT (dataset_id)
            DO UPDATE SET
                title = EXCLUDED.title,
                description = EXCLUDED.description,
                release_date = EXCLUDED.release_date,
                updated_at = EXCLUDED.updated_at
            "#,
            dataset.dataset_id,
            dataset.title,
            dataset.description,
            dataset.release_date,
            dataset.updated_at
        )
        .execute(&self.pool)
        .await?;

        Ok(())
    }

    pub async fn get_all(&self, limit: i32) -> Result<Vec<OsdrDataset>, ApiError> {
        sqlx::query_as!(
            OsdrDataset,
            r#"
            SELECT id, dataset_id, title, description, release_date, updated_at
            FROM osdr_items
            ORDER BY updated_at DESC
            LIMIT $1
            "#,
            limit as i64
        )
        .fetch_all(&self.pool)
        .await
        .map_err(ApiError::DatabaseError)
    }
}