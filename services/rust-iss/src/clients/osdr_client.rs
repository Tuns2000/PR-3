use crate::domain::{error::ApiError, models::OsdrApiResponse};
use reqwest::Client;
use std::time::Duration;

pub struct OsdrClient {
    client: Client,
    base_url: String,
    api_key: String,
}

impl OsdrClient {
    pub fn new(base_url: String, api_key: String) -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(10))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self {
            client,
            base_url,
            api_key,
        })
    }

    pub async fn fetch_datasets(&self) -> Result<OsdrApiResponse, ApiError> {
        let mut retries = 0;
        let max_retries = 1;

        loop {
            match self.try_fetch().await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    retries += 1;
                    tracing::warn!("OSDR fetch attempt {} failed: {}", retries, e);
                    tokio::time::sleep(Duration::from_millis(2000 * retries)).await;
                }
                Err(e) => {
                    return Err(ApiError::UpstreamError(format!(
                        "OSDR API failed after {} retries: {}",
                        max_retries, e
                    )));
                }
            }
        }
    }

    async fn try_fetch(&self) -> Result<OsdrApiResponse, String> {
        let mut request = self.client.get(&self.base_url);

        if !self.api_key.is_empty() {
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
            .json::<OsdrApiResponse>()
            .await
            .map_err(|e| format!("JSON parse error: {}", e))
    }
}