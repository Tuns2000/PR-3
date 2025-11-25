use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use validator::Validate;

// ===========================
// ISS Models
// ===========================

#[derive(Debug, Clone, Serialize, Deserialize, sqlx::FromRow)]
pub struct IssPosition {
    pub id: i32,
    pub latitude: f64,
    pub longitude: f64,
    pub altitude: f64,
    pub velocity: f64,
    pub timestamp: DateTime<Utc>, // TIMESTAMPTZ в PostgreSQL
    pub fetched_at: DateTime<Utc>, // TIMESTAMPTZ
}

#[derive(Debug, Deserialize)]
pub struct IssApiResponse {
    pub latitude: f64,
    pub longitude: f64,
    pub altitude: f64,
    pub velocity: f64,
    pub timestamp: i64, // Unix timestamp
}

#[derive(Debug, Validate, Deserialize)]
pub struct IssFilterQuery {
    #[validate(range(min = 1, max = 1000))]
    pub limit: Option<i32>,
    pub start_date: Option<DateTime<Utc>>,
    pub end_date: Option<DateTime<Utc>>,
}

// ===========================
// OSDR Models
// ===========================

#[derive(Debug, Clone, Serialize, Deserialize, sqlx::FromRow)]
pub struct OsdrDataset {
    pub id: i32,
    pub dataset_id: String,
    pub title: String,
    pub description: Option<String>,
    pub release_date: Option<DateTime<Utc>>,
    pub updated_at: DateTime<Utc>,
}

#[derive(Debug, Deserialize)]
pub struct OsdrApiResponse {
    pub results: Vec<OsdrApiDataset>,
}

#[derive(Debug, Deserialize)]
pub struct OsdrApiDataset {
    #[serde(rename = "accession")]
    pub dataset_id: String,
    pub title: String,
    pub description: Option<String>,
    #[serde(rename = "publicReleaseDate")]
    pub release_date: Option<String>,
}

// ===========================
// APOD Model
// ===========================

#[derive(Debug, Clone, Serialize, Deserialize, sqlx::FromRow)]
pub struct ApodEntry {
    pub id: i32,
    pub date: chrono::NaiveDate,
    pub title: String,
    pub explanation: String,
    pub url: String,
    pub hdurl: Option<String>,
    pub media_type: String,
    pub fetched_at: DateTime<Utc>,
}

// ===========================
// Cache Model
// ===========================

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct CachedData<T> {
    pub data: T,
    pub cached_at: DateTime<Utc>,
}