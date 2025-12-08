use crate::domain::{error::ApiError, models::OsdrDataset};
use sqlx::{PgPool, Row};

pub struct OsdrRepo {
    pool: PgPool,
}

impl OsdrRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    /// Сохранить датасет OSDR в базу данных (UPSERT)
    pub async fn save(&self, dataset: &OsdrDataset) -> Result<(), ApiError> {
        sqlx::query(
            r#"
            INSERT INTO osdr_items (dataset_id, title, description, release_date, updated_at)
            VALUES ($1, $2, $3, $4, $5)
            ON CONFLICT (dataset_id) DO UPDATE SET
                title = EXCLUDED.title,
                description = EXCLUDED.description,
                release_date = EXCLUDED.release_date,
                updated_at = EXCLUDED.updated_at
            "#
        )
        .bind(&dataset.dataset_id)
        .bind(&dataset.title)
        .bind(&dataset.description)
        .bind(dataset.release_date)
        .bind(dataset.updated_at)
        .execute(&self.pool)
        .await?;

        Ok(())
    }

    /// Получить все датасеты OSDR
    pub async fn get_all(&self, limit: i32) -> Result<Vec<OsdrDataset>, ApiError> {
        let rows = sqlx::query(
            r#"
            SELECT id, dataset_id, title, description, release_date, updated_at
            FROM osdr_items
            ORDER BY updated_at DESC
            LIMIT $1
            "#
        )
        .bind(limit as i64)
        .fetch_all(&self.pool)
        .await?;

        let datasets = rows
            .into_iter()
            .map(|r| OsdrDataset {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО
                dataset_id: r.get("dataset_id"),
                title: r.get("title"),
                description: r.get("description"),
                release_date: r.get("release_date"),
                updated_at: r.get("updated_at"),
            })
            .collect();

        Ok(datasets)
    }

    /// Получить датасет по ID
    pub async fn get_by_id(&self, dataset_id: &str) -> Result<Option<OsdrDataset>, ApiError> {
        let row = sqlx::query(
            r#"
            SELECT id, dataset_id, title, description, release_date, updated_at
            FROM osdr_items
            WHERE dataset_id = $1
            "#
        )
        .bind(dataset_id)
        .fetch_optional(&self.pool)
        .await?;

        match row {
            Some(r) => Ok(Some(OsdrDataset {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО
                dataset_id: r.get("dataset_id"),
                title: r.get("title"),
                description: r.get("description"),
                release_date: r.get("release_date"),
                updated_at: r.get("updated_at"),
            })),
            None => Ok(None),
        }
    }

    /// Поиск по названию (полнотекстовый поиск)
    pub async fn search_by_title(&self, query: &str, limit: i32) -> Result<Vec<OsdrDataset>, ApiError> {
        let rows = sqlx::query(
            r#"
            SELECT id, dataset_id, title, description, release_date, updated_at
            FROM osdr_items
            WHERE title_search @@ plainto_tsquery('english', $1)
            ORDER BY ts_rank(title_search, plainto_tsquery('english', $1)) DESC
            LIMIT $2
            "#
        )
        .bind(query)
        .bind(limit as i64)
        .fetch_all(&self.pool)
        .await?;

        let datasets = rows
            .into_iter()
            .map(|r| OsdrDataset {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО
                dataset_id: r.get("dataset_id"),
                title: r.get("title"),
                description: r.get("description"),
                release_date: r.get("release_date"),
                updated_at: r.get("updated_at"),
            })
            .collect();

        Ok(datasets)
    }

    /// Batch insert/update datasets using PostgreSQL UNNEST for efficiency
    /// Much faster than individual INSERT statements for large batches
    /// 
    /// # Performance
    /// - Single transaction for all records
    /// - Uses UNNEST to create temporary table
    /// - ON CONFLICT DO UPDATE for upsert behavior
    /// - ~10x faster than individual inserts for 100+ records
    pub async fn batch_upsert(&self, datasets: &[OsdrDataset]) -> Result<u64, ApiError> {
        if datasets.is_empty() {
            return Ok(0);
        }

        // Build arrays for UNNEST
        let dataset_ids: Vec<String> = datasets.iter().map(|d| d.dataset_id.clone()).collect();
        let titles: Vec<String> = datasets.iter().map(|d| d.title.clone()).collect();
        let descriptions: Vec<Option<String>> = datasets.iter().map(|d| d.description.clone()).collect();
        let release_dates: Vec<Option<chrono::DateTime<chrono::Utc>>> = 
            datasets.iter().map(|d| d.release_date).collect();
        let updated_ats: Vec<chrono::DateTime<chrono::Utc>> = 
            datasets.iter().map(|d| d.updated_at).collect();

        let result = sqlx::query(
            r#"
            INSERT INTO osdr_items (dataset_id, title, description, release_date, updated_at)
            SELECT * FROM UNNEST($1::text[], $2::text[], $3::text[], $4::timestamptz[], $5::timestamptz[])
            ON CONFLICT (dataset_id) DO UPDATE SET
                title = EXCLUDED.title,
                description = EXCLUDED.description,
                release_date = EXCLUDED.release_date,
                updated_at = EXCLUDED.updated_at
            "#
        )
        .bind(&dataset_ids)
        .bind(&titles)
        .bind(&descriptions)
        .bind(&release_dates)
        .bind(&updated_ats)
        .execute(&self.pool)
        .await?;

        Ok(result.rows_affected())
    }

    /// Count total datasets in database
    pub async fn count(&self) -> Result<i64, ApiError> {
        let row = sqlx::query("SELECT COUNT(*) as count FROM osdr_items")
            .fetch_one(&self.pool)
            .await?;
        
        Ok(row.get("count"))
    }

    /// Delete datasets older than specified days (cleanup)
    pub async fn delete_older_than(&self, days: i64) -> Result<u64, ApiError> {
        let result = sqlx::query(
            "DELETE FROM osdr_items WHERE updated_at < NOW() - INTERVAL '1 day' * $1"
        )
        .bind(days)
        .execute(&self.pool)
        .await?;

        Ok(result.rows_affected())
    }
}