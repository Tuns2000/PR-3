use serde::Deserialize;
use std::env;

#[derive(Debug, Clone, Deserialize)]
pub struct Config {
    // Database
    pub database_url: String,
    pub redis_url: String,
    
    // External APIs
    pub nasa_api_url: String,
    pub nasa_api_key: String,
    pub where_iss_url: String,
    
    // Scheduler intervals (seconds)
    pub iss_every_seconds: u64,
    pub apod_every_seconds: u64,
    pub neo_every_seconds: u64,
    pub donki_every_seconds: u64,
    pub spacex_every_seconds: u64,
    
    // Rate limiting
    pub rate_limit_per_minute: u32,
    
    // Server
    pub host: String,
    pub port: u16,
}

impl Config {
    pub fn from_env() -> Result<Self, String> {
        dotenv::dotenv().ok();

        Ok(Self {
            database_url: env::var("DATABASE_URL")
                .map_err(|_| "DATABASE_URL not set".to_string())?,
            redis_url: env::var("REDIS_URL")
                .unwrap_or_else(|_| "redis://redis:6379".to_string()),
            
            nasa_api_url: env::var("NASA_API_URL")
                .unwrap_or_else(|_| "https://api.nasa.gov".to_string()),
            nasa_api_key: env::var("NASA_API_KEY")
                .unwrap_or_else(|_| "DEMO_KEY".to_string()),
            where_iss_url: env::var("WHERE_ISS_URL")
                .unwrap_or_else(|_| "https://api.wheretheiss.at/v1/satellites/25544".to_string()),
            
            iss_every_seconds: env::var("ISS_EVERY_SECONDS")
                .unwrap_or_else(|_| "120".to_string())
                .parse()
                .unwrap_or(120),
            apod_every_seconds: env::var("APOD_EVERY_SECONDS")
                .unwrap_or_else(|_| "43200".to_string())
                .parse()
                .unwrap_or(43200),
            neo_every_seconds: env::var("NEO_EVERY_SECONDS")
                .unwrap_or_else(|_| "7200".to_string())
                .parse()
                .unwrap_or(7200),
            donki_every_seconds: env::var("DONKI_EVERY_SECONDS")
                .unwrap_or_else(|_| "3600".to_string())
                .parse()
                .unwrap_or(3600),
            spacex_every_seconds: env::var("SPACEX_EVERY_SECONDS")
                .unwrap_or_else(|_| "3600".to_string())
                .parse()
                .unwrap_or(3600),
            
            rate_limit_per_minute: env::var("RATE_LIMIT_PER_MINUTE")
                .unwrap_or_else(|_| "30".to_string())
                .parse()
                .unwrap_or(30),
            
            host: env::var("HOST").unwrap_or_else(|_| "0.0.0.0".to_string()),
            port: env::var("PORT")
                .unwrap_or_else(|_| "3000".to_string())
                .parse()
                .unwrap_or(3000),
        })
    }

    pub fn validate(&self) -> Result<(), String> {
        if self.database_url.is_empty() {
            return Err("DATABASE_URL cannot be empty".to_string());
        }
        if self.iss_every_seconds < 10 {
            return Err("ISS_EVERY_SECONDS must be >= 10".to_string());
        }
        Ok(())
    }
}