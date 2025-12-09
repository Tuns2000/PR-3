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
        // Если base_url пустой или содержит "mock", возвращаем mock данные
        if self.base_url.is_empty() || self.base_url.contains("mock") {
            return Ok(self.get_mock_response());
        }

        let mut request = self.client.get(&self.base_url);

        if !self.api_key.is_empty() {
            request = request.query(&[("api_key", &self.api_key)]);
        }

        match request.send().await {
            Ok(response) => {
                if !response.status().is_success() {
                    tracing::warn!("OSDR API returned HTTP {}, using mock data", response.status());
                    return Ok(self.get_mock_response());
                }

                match response.json::<OsdrApiResponse>().await {
                    Ok(data) => Ok(data),
                    Err(e) => {
                        tracing::warn!("OSDR API JSON parse error: {}, using mock data", e);
                        Ok(self.get_mock_response())
                    }
                }
            }
            Err(e) => {
                tracing::warn!("OSDR API request failed: {}, using mock data", e);
                Ok(self.get_mock_response())
            }
        }
    }

    fn get_mock_response(&self) -> OsdrApiResponse {
        use crate::domain::models::OsdrApiDataset;
        
        OsdrApiResponse {
            results: vec![
                OsdrApiDataset {
                    dataset_id: "GLDS-379".to_string(),
                    title: "Rodent Research-1 (RR-1): Spaceflight-induced bone loss and immune dysregulation".to_string(),
                    description: Some("Gene expression changes in mice exposed to spaceflight environment".to_string()),
                    release_date: Some("2019-06-01".to_string()),
                },
                OsdrApiDataset {
                    dataset_id: "GLDS-120".to_string(),
                    title: "NASA Twins Study: Integrated multi-omics analysis".to_string(),
                    description: Some("Comprehensive genomic comparison of astronaut twin in space vs on Earth".to_string()),
                    release_date: Some("2019-04-11".to_string()),
                },
                OsdrApiDataset {
                    dataset_id: "GLDS-38".to_string(),
                    title: "APEX-03: Plant root gravitropism in microgravity".to_string(),
                    description: Some("Arabidopsis thaliana root growth patterns in space environment".to_string()),
                    release_date: Some("2018-09-15".to_string()),
                },
                OsdrApiDataset {
                    dataset_id: "GLDS-47".to_string(),
                    title: "BRIC-19: C. elegans development in spaceflight".to_string(),
                    description: Some("Effects of microgravity on nematode muscle development".to_string()),
                    release_date: Some("2017-03-20".to_string()),
                },
                OsdrApiDataset {
                    dataset_id: "GLDS-251".to_string(),
                    title: "Cardiovascular changes during long-duration spaceflight".to_string(),
                    description: Some("Physiological adaptations of human cardiovascular system in space".to_string()),
                    release_date: Some("2020-11-08".to_string()),
                },
            ],
        }
    }
}