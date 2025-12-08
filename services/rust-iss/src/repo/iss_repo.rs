use crate::domain::{error::ApiError, models::IssPosition};
use chrono::DateTime;
use sqlx::{PgPool, Row};

pub struct IssRepo {
    pool: PgPool,
}

impl IssRepo {
    pub fn new(pool: PgPool) -> Self {
        Self { pool }
    }

    /// Сохранить позицию ISS в базу данных
    pub async fn save(&self, pos: &IssPosition) -> Result<(), ApiError> {
        sqlx::query(
            r#"
            INSERT INTO iss_fetch_log (latitude, longitude, altitude, velocity, timestamp, fetched_at)
            VALUES ($1, $2, $3, $4, $5, $6)
            "#
        )
        .bind(pos.latitude)
        .bind(pos.longitude)
        .bind(pos.altitude)
        .bind(pos.velocity)
        .bind(pos.timestamp)
        .bind(pos.fetched_at)
        .execute(&self.pool)
        .await?;

        Ok(())
    }

    /// Получить последнюю позицию ISS
    pub async fn get_latest(&self) -> Result<Option<IssPosition>, ApiError> {
        let row = sqlx::query(
            r#"
            SELECT id, latitude, longitude, altitude, velocity, timestamp, fetched_at
            FROM iss_fetch_log
            ORDER BY timestamp DESC
            LIMIT 1
            "#
        )
        .fetch_optional(&self.pool)
        .await?;

        match row {
            Some(r) => Ok(Some(IssPosition {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО: убран лишний Some()
                latitude: r.get("latitude"),
                longitude: r.get("longitude"),
                altitude: r.get("altitude"),
                velocity: r.get("velocity"),
                timestamp: r.get("timestamp"),
                fetched_at: r.get("fetched_at"),
            })),
            None => Ok(None),
        }
    }

    /// Получить историю позиций ISS
    pub async fn get_history(
        &self,
        start: Option<DateTime<chrono::Utc>>,
        end: Option<DateTime<chrono::Utc>>,
        limit: i32,
    ) -> Result<Vec<IssPosition>, ApiError> {
        let mut query_str = String::from(
            "SELECT id, latitude, longitude, altitude, velocity, timestamp, fetched_at FROM iss_fetch_log WHERE 1=1"
        );
        
        if start.is_some() {
            query_str.push_str(" AND timestamp >= $1");
        }
        if end.is_some() {
            let param_idx = if start.is_some() { "$2" } else { "$1" };
            query_str.push_str(&format!(" AND timestamp <= {}", param_idx));
        }
        
        let limit_idx = match (start.is_some(), end.is_some()) {
            (true, true) => "$3",
            (true, false) | (false, true) => "$2",
            (false, false) => "$1",
        };
        query_str.push_str(&format!(" ORDER BY timestamp DESC LIMIT {}", limit_idx));
        
        let mut query = sqlx::query(&query_str);
        
        if let Some(s) = start {
            query = query.bind(s.naive_utc());
        }
        if let Some(e) = end {
            query = query.bind(e.naive_utc());
        }
        query = query.bind(limit as i64);
        
        let rows = query.fetch_all(&self.pool).await?;

        let positions = rows
            .into_iter()
            .map(|r| IssPosition {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО
                latitude: r.get("latitude"),
                longitude: r.get("longitude"),
                altitude: r.get("altitude"),
                velocity: r.get("velocity"),
                timestamp: r.get("timestamp"),
                fetched_at: r.get("fetched_at"),
            })
            .collect();

        Ok(positions)
    }

    /// Получить позиции ISS в диапазоне времени
    pub async fn get_by_timerange(
        &self,
        start: chrono::NaiveDateTime,
        end: chrono::NaiveDateTime,
    ) -> Result<Vec<IssPosition>, ApiError> {
        let rows = sqlx::query(
            r#"
            SELECT id, latitude, longitude, altitude, velocity, timestamp, fetched_at
            FROM iss_fetch_log
            WHERE timestamp BETWEEN $1 AND $2
            ORDER BY timestamp ASC
            "#
        )
        .bind(start)
        .bind(end)
        .fetch_all(&self.pool)
        .await?;

        let positions = rows
            .into_iter()
            .map(|r| IssPosition {
                id: Some(r.get("id")), // ✅ ИСПРАВЛЕНО
                latitude: r.get("latitude"),
                longitude: r.get("longitude"),
                altitude: r.get("altitude"),
                velocity: r.get("velocity"),
                timestamp: r.get("timestamp"),
                fetched_at: r.get("fetched_at"),
            })
            .collect();

        Ok(positions)
    }
}