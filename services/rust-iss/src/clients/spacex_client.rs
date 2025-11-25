use crate::domain::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;

pub struct SpaceXClient {
    client: Client,
}

impl SpaceXClient {
    pub fn new() -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(15))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self { client })
    }

    pub async fn fetch_next_launch(&self) -> Result<Value, ApiError> {
        let url = "https://api.spacexdata.com/v4/launches/next";

        let mut retries = 0;
        let max_retries = 3;

        loop {
            match self.try_fetch(url).await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    retries += 1;
                    tracing::warn!("SpaceX fetch attempt {} failed: {}", retries, e);
                    tokio::time::sleep(Duration::from_millis(1000 * retries)).await;
                }
                Err(e) => {
                    return Err(ApiError::UpstreamError(format!(
                        "SpaceX API failed after {} retries: {}",
                        max_retries, e
                    )));
                }
            }
        }
    }

    async fn try_fetch(&self, url: &str) -> Result<Value, String> {
        let response = self
            .client
            .get(url)
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