use crate::domain::{error::ApiError, models::IssApiResponse};
use reqwest::Client;
use std::time::Duration;

pub struct IssClient {
    client: Client,
    base_url: String,
}

impl IssClient {
    pub fn new(base_url: String) -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(10))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self { client, base_url })
    }

    pub async fn fetch_current_position(&self) -> Result<IssApiResponse, ApiError> {
        let mut retries = 0;
        let max_retries = 3;

        loop {
            match self.try_fetch().await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    retries += 1;
                    tracing::warn!("ISS fetch attempt {} failed: {}", retries, e);
                    tokio::time::sleep(Duration::from_millis(1000 * retries)).await;
                }
                Err(e) => {
                    return Err(ApiError::UpstreamError(format!(
                        "ISS API failed after {} retries: {}",
                        max_retries, e
                    )));
                }
            }
        }
    }

    async fn try_fetch(&self) -> Result<IssApiResponse, String> {
        let response = self
            .client
            .get(&self.base_url)
            .send()
            .await
            .map_err(|e| format!("Request failed: {}", e))?;

        if !response.status().is_success() {
            return Err(format!("HTTP {}", response.status()));
        }

        response
            .json::<IssApiResponse>()
            .await
            .map_err(|e| format!("JSON parse error: {}", e))
    }
}