use crate::domain::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;

pub struct NasaClient {
    client: Client,
    api_key: String,
}

impl NasaClient {
    pub fn new(api_key: String) -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(30))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self { client, api_key })
    }

    pub async fn fetch_apod(&self) -> Result<Value, ApiError> {
        self.fetch_with_retry("https://api.nasa.gov/planetary/apod").await
    }

    pub async fn fetch_neo(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = format!(
            "https://api.nasa.gov/neo/rest/v1/feed?start_date={}&end_date={}",
            start_date, end_date
        );
        self.fetch_with_retry(&url).await
    }

    pub async fn fetch_donki_flr(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = format!(
            "https://api.nasa.gov/DONKI/FLR?startDate={}&endDate={}",
            start_date, end_date
        );
        self.fetch_with_retry(&url).await
    }

    pub async fn fetch_donki_cme(&self, start_date: &str, end_date: &str) -> Result<Value, ApiError> {
        let url = format!(
            "https://api.nasa.gov/DONKI/CME?startDate={}&endDate={}",
            start_date, end_date
        );
        self.fetch_with_retry(&url).await
    }

    async fn fetch_with_retry(&self, url: &str) -> Result<Value, ApiError> {
        let mut retries = 0;
        let max_retries = 3;

        loop {
            match self.try_fetch(url).await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    retries += 1;
                    tracing::warn!("NASA API fetch attempt {} failed: {}", retries, e);
                    tokio::time::sleep(Duration::from_millis(2000 * retries)).await;
                }
                Err(e) => {
                    return Err(ApiError::UpstreamError(format!(
                        "NASA API failed after {} retries: {}",
                        max_retries, e
                    )));
                }
            }
        }
    }

    async fn try_fetch(&self, url: &str) -> Result<Value, String> {
        let mut request = self.client.get(url);

        if !self.api_key.is_empty() && self.api_key != "DEMO_KEY" {
            request = request.query(&[("api_key", &self.api_key)]);
        }

        let response = request
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