use crate::domain::error::ApiError;
use redis::{aio::MultiplexedConnection, AsyncCommands};
use serde::{de::DeserializeOwned, Serialize};

pub struct CacheRepo {
    conn: MultiplexedConnection,
}

impl CacheRepo {
    pub fn new(conn: MultiplexedConnection) -> Self {
        Self { conn }
    }

    pub async fn get<T: DeserializeOwned>(&mut self, key: &str) -> Result<Option<T>, ApiError> {
        let data: Option<String> = self.conn.get(key).await?;
        
        match data {
            Some(json) => {
                let parsed = serde_json::from_str(&json)
                    .map_err(|e| ApiError::InternalError(format!("JSON parse error: {}", e)))?;
                Ok(Some(parsed))
            }
            None => Ok(None),
        }
    }

    pub async fn set<T: Serialize>(
        &mut self,
        key: &str,
        value: &T,
        ttl_seconds: usize,
    ) -> Result<(), ApiError> {
        let json = serde_json::to_string(value)
            .map_err(|e| ApiError::InternalError(format!("JSON serialize error: {}", e)))?;
        
        self.conn.set_ex(key, json, ttl_seconds).await?;
        Ok(())
    }

    pub async fn delete(&mut self, key: &str) -> Result<(), ApiError> {
        self.conn.del(key).await?;
        Ok(())
    }
}