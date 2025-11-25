use crate::domain::error::ApiError;
use reqwest::Client;
use serde_json::Value;
use std::time::Duration;
use base64::{Engine as _, engine::general_purpose}; 

pub struct AstronomyClient {
    client: Client,
    app_id: String,
    app_secret: String,
}

impl AstronomyClient {
    pub fn new(app_id: String, app_secret: String) -> Result<Self, ApiError> {
        let client = Client::builder()
            .timeout(Duration::from_secs(20))
            .user_agent("CassiopeiaBot/1.0 (Space Data Collector)")
            .build()
            .map_err(|e| ApiError::InternalError(format!("Failed to create HTTP client: {}", e)))?;

        Ok(Self {
            client,
            app_id,
            app_secret,
        })
    }

    pub async fn fetch_events(&self) -> Result<Value, ApiError> {
        let url = "https://api.astronomyapi.com/api/v2/bodies/events";

        let mut retries = 0;
        let max_retries = 3;

        loop {
            match self.try_fetch(url).await {
                Ok(data) => return Ok(data),
                Err(e) if retries < max_retries => {
                    retries += 1;
                    tracing::warn!("Astronomy API fetch attempt {} failed: {}", retries, e);
                    tokio::time::sleep(Duration::from_millis(1500 * retries)).await;
                }
                Err(e) => {
                    return Err(ApiError::UpstreamError(format!(
                        "Astronomy API failed after {} retries: {}",
                        max_retries, e
                    )));
                }
            }
        }
    }

    async fn try_fetch(&self, url: &str) -> Result<Value, String> {
        let auth = format!("{}:{}", self.app_id, self.app_secret);
       
        let basic_auth = format!("Basic {}", general_purpose::STANDARD.encode(&auth));

        let response = self
            .client
            .get(url)
            .header("Authorization", basic_auth)
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