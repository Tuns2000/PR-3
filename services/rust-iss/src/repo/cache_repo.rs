use redis::aio::Connection;
use redis::{Client, RedisError};
use serde::{Deserialize, Serialize};

#[derive(Clone)]
pub struct CacheRepo {
    client: Client,
}

impl CacheRepo {
    pub fn new(redis_url: &str) -> Result<Self, RedisError> {
        let client = Client::open(redis_url)?;
        Ok(Self { client })
    }

    pub async fn get_connection(&self) -> Result<Connection, RedisError> {
        self.client.get_async_connection().await
    }

    /// Получить значение из кэша
    pub async fn get<T: for<'de> Deserialize<'de>>(
        &self,
        key: &str,
    ) -> Result<Option<T>, RedisError> {
        let mut conn = self.get_connection().await?;
        let value: Option<String> = redis::cmd("GET").arg(key).query_async(&mut conn).await?;

        match value {
            Some(json) => {
                crate::utils::metrics::record_cache_hit(key);
                Ok(serde_json::from_str(&json).ok())
            },
            None => {
                crate::utils::metrics::record_cache_miss(key);
                Ok(None)
            },
        }
    }

    /// Сохранить значение в кэш с TTL
    pub async fn set<T: Serialize>(
        &self,
        key: &str,
        value: &T,
        ttl_seconds: usize,
    ) -> Result<(), RedisError> {
        let mut conn = self.get_connection().await?;
        let json = serde_json::to_string(value).unwrap();

        redis::cmd("SETEX")
            .arg(key)
            .arg(ttl_seconds as u64)
            .arg(json)
            .query_async::<_, ()>(&mut conn)
            .await?;

        Ok(())
    }

    /// Удалить значение из кэша
    pub async fn delete(&self, key: &str) -> Result<(), RedisError> {
        let mut conn = self.get_connection().await?;
        redis::cmd("DEL")
            .arg(key)
            .query_async::<_, ()>(&mut conn)
            .await?;
        Ok(())
    }

    /// Проверить существование ключа
    pub async fn exists(&self, key: &str) -> Result<bool, RedisError> {
        let mut conn = self.get_connection().await?;
        let result: i32 = redis::cmd("EXISTS").arg(key).query_async(&mut conn).await?;
        Ok(result > 0)
    }

    /// Установить TTL для существующего ключа
    pub async fn expire(&self, key: &str, ttl_seconds: usize) -> Result<bool, RedisError> {
        let mut conn = self.get_connection().await?;
        let result: i32 = redis::cmd("EXPIRE")
            .arg(key)
            .arg(ttl_seconds as u64)
            .query_async(&mut conn)
            .await?;
        Ok(result > 0)
    }
}