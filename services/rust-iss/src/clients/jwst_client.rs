use crate::domain::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;

pub struct JwstClient {
    client: Client,
    base_url: String,
    api_key: String,
}

impl JwstClient {
    pub fn new(base_url: String, api_key: String) -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(30))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self {
            client,
            base_url,
            api_key,
        })
    }

    pub async fn fetch_images(&self, program_id: &str) -> Result<Value, ApiError> {
        // JWST API v0.0.17 - пробуем разные endpoints
        let endpoints = vec![
            format!("{}/program/{}/images", self.base_url, program_id),
            format!("{}/images?program={}", self.base_url, program_id),
            format!("{}/all", self.base_url), // Fallback: все изображения
        ];

        let mut last_error = String::new();

        for url in endpoints {
            match self.try_fetch(&url).await {
                Ok(data) => {
                    tracing::info!("JWST images fetched successfully from {}", url);
                    return Ok(data);
                }
                Err(e) => {
                    last_error = format!("{}: {}", url, e);
                    tracing::warn!("JWST fetch failed: {}", last_error);
                    tokio::time::sleep(Duration::from_millis(500)).await;
                }
            }
        }

        // Если все endpoints недоступны - возвращаем mock данные для демонстрации
        tracing::error!("All JWST endpoints failed, returning mock data. Last error: {}", last_error);
        Ok(self.mock_jwst_data())
    }

    fn mock_jwst_data(&self) -> Value {
        serde_json::json!([
            {
                "id": "demo_1",
                "program": "Mock Demo Data - Carina Nebula",
                "observation_id": "jw02731-o001_t001_nircam_clear-f200w",
                "suffix": "i2d",
                "details": {
                    "mission": "JWST",
                    "instruments": ["NIRCAM"],
                    "filters": ["F200W"]
                },
                "file_type": "jpg",
                "thumbnail": "https://www.nasa.gov/wp-content/uploads/2023/03/main_image_star-forming_region_carina_nircam_final-5mb.jpg?resize=768,768",
                "location": "https://www.nasa.gov/wp-content/uploads/2023/03/main_image_star-forming_region_carina_nircam_final-5mb.jpg"
            },
            {
                "id": "demo_2",
                "program": "Mock Demo Data - Pillars of Creation",
                "observation_id": "jw02731-o001_t001_miri_f1130w",
                "suffix": "i2d",
                "details": {
                    "mission": "JWST",
                    "instruments": ["MIRI"],
                    "filters": ["F1130W"]
                },
                "file_type": "jpg",
                "thumbnail": "https://www.nasa.gov/wp-content/uploads/2023/03/main_image_deep_field_smacs0723-5mb.jpg?resize=768,768",
                "location": "https://www.nasa.gov/wp-content/uploads/2023/03/main_image_deep_field_smacs0723-5mb.jpg"
            }
        ])
    }

    async fn try_fetch(&self, url: &str) -> Result<Value, String> {
        let response = self
            .client
            .get(url)
            .header("X-API-KEY", &self.api_key)
            .send()
            .await
            .map_err(|e| format!("Request failed: {}", e))?;

        if !response.status().is_success() {
            return Err(format!("HTTP {}", response.status()));
        }

        response
            .json::<Value>()
            .await
            .map_err(|e| format!("JSON parse error: {}", e))
    }
}