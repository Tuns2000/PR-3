use crate::{
    config::Config,
    services::{IssService, OsdrService, NasaService, SpaceXService},
    utils::metrics,
};
use std::{sync::Arc, time::{Duration, Instant}};
use tokio::sync::Mutex;
use tracing::{error, info, warn};
use sqlx::PgPool;

pub struct Scheduler {
    config: Config,
    pool: PgPool,
    iss_service: Arc<Mutex<IssService>>,
    osdr_service: Arc<Mutex<OsdrService>>,
    nasa_service: Arc<Mutex<NasaService>>,
    spacex_service: Arc<Mutex<SpaceXService>>,
}

impl Scheduler {
    pub fn new(
        config: Config,
        pool: PgPool,
        iss_service: Arc<Mutex<IssService>>,
        osdr_service: Arc<Mutex<OsdrService>>,
        nasa_service: Arc<Mutex<NasaService>>,
        spacex_service: Arc<Mutex<SpaceXService>>,
    ) -> Self {
        Self {
            config,
            pool,
            iss_service,
            osdr_service,
            nasa_service,
            spacex_service,
        }
    }

    /// Acquire PostgreSQL Advisory Lock to prevent concurrent execution
    /// Advisory locks are session-level locks that don't require a table
    /// Returns true if lock was acquired, false if another process holds it
    async fn try_acquire_lock(&self, lock_id: i64) -> Result<bool, sqlx::Error> {
        let result: (bool,) = sqlx::query_as(
            "SELECT pg_try_advisory_lock($1)"
        )
        .bind(lock_id)
        .fetch_one(&self.pool)
        .await?;
        
        Ok(result.0)
    }

    /// Release PostgreSQL Advisory Lock
    async fn release_lock(&self, lock_id: i64) -> Result<(), sqlx::Error> {
        sqlx::query("SELECT pg_advisory_unlock($1)")
            .bind(lock_id)
            .execute(&self.pool)
            .await?;
        
        Ok(())
    }

    pub fn start(self: Arc<Self>) {
        // ISS fetcher with Advisory Lock (ID: 1001)
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting ISS scheduler (every {}s)", scheduler.config.iss_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.iss_every_seconds));
                const LOCK_ID: i64 = 1001; // Unique lock ID for ISS scheduler
                
                loop {
                    interval.tick().await;
                    
                    // Try to acquire advisory lock
                    match scheduler.try_acquire_lock(LOCK_ID).await {
                        Ok(true) => {
                            metrics::record_advisory_lock_acquired(LOCK_ID);
                            
                            // Lock acquired, proceed with fetch
                            let start = Instant::now();
                            let mut service = scheduler.iss_service.lock().await;
                            
                            match service.fetch_and_store().await {
                                Ok(position) => {
                                    let duration = start.elapsed().as_secs_f64();
                                    metrics::record_iss_fetch(
                                        true, 
                                        duration, 
                                        Some(position.altitude), 
                                        Some(position.velocity)
                                    );
                                    info!("ISS position updated: lat={}, lon={}, alt={}, vel={}", 
                                          position.latitude, position.longitude, position.altitude, position.velocity);
                                }
                                Err(e) => {
                                    let duration = start.elapsed().as_secs_f64();
                                    metrics::record_iss_fetch(false, duration, None, None);
                                    error!("Failed to fetch ISS position: {:?}", e); 
                                }
                            }
                            
                            // Release lock
                            if let Err(e) = scheduler.release_lock(LOCK_ID).await {
                                error!("Failed to release ISS advisory lock: {:?}", e);
                            }
                        }
                        Ok(false) => {
                            metrics::record_advisory_lock_failed(LOCK_ID);
                            warn!("ISS scheduler: another instance is already running, skipping this tick");
                        }
                        Err(e) => {
                            error!("Failed to acquire ISS advisory lock: {:?}", e);
                        }
                    }
                }
            });
        }

        // OSDR syncer with Advisory Lock (ID: 1002) - every 2 hours
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting OSDR scheduler (every 7200s)");
                let mut interval = tokio::time::interval(Duration::from_secs(7200));
                const LOCK_ID: i64 = 1002; // Unique lock ID for OSDR scheduler
                
                loop {
                    interval.tick().await;
                    
                    // Try to acquire advisory lock (OSDR sync is expensive)
                    match scheduler.try_acquire_lock(LOCK_ID).await {
                        Ok(true) => {
                            metrics::record_advisory_lock_acquired(LOCK_ID);
                            info!("OSDR scheduler: lock acquired, starting sync");
                            
                            let start = Instant::now();
                            let mut service = scheduler.osdr_service.lock().await;
                            
                            match service.sync_datasets().await {
                                Ok(count) => {
                                    let duration = start.elapsed().as_secs_f64();
                                    metrics::record_osdr_sync(true, duration, count);
                                    info!("OSDR synced {} datasets in {:.2}s", count, duration);
                                }
                                Err(e) => {
                                    let duration = start.elapsed().as_secs_f64();
                                    metrics::record_osdr_sync(false, duration, 0);
                                    error!("Failed to sync OSDR: {:?}", e); 
                                }
                            }
                            
                            // Release lock
                            if let Err(e) = scheduler.release_lock(LOCK_ID).await {
                                error!("Failed to release OSDR advisory lock: {:?}", e);
                            }
                        }
                        Ok(false) => {
                            metrics::record_advisory_lock_failed(LOCK_ID);
                            warn!("OSDR scheduler: another instance is syncing, skipping this tick");
                        }
                        Err(e) => {
                            error!("Failed to acquire OSDR advisory lock: {:?}", e);
                        }
                    }
                }
            });
        }

        // APOD fetcher
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting APOD scheduler (every {}s)", scheduler.config.apod_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.apod_every_seconds));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.nasa_service.lock().await;
                    if let Err(e) = service.get_apod().await {
                        error!("Failed to fetch APOD: {:?}", e); 
                    } else {
                        info!("APOD fetched successfully");
                    }
                }
            });
        }

        // NEO fetcher
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting NEO scheduler (every {}s)", scheduler.config.neo_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.neo_every_seconds));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.nasa_service.lock().await;
                    if let Err(e) = service.get_neo().await {
                        error!("Failed to fetch NEO: {:?}", e); 
                    } else {
                        info!("NEO fetched successfully");
                    }
                }
            });
        }

        // DONKI fetcher
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting DONKI scheduler (every {}s)", scheduler.config.donki_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.donki_every_seconds));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.nasa_service.lock().await;
                    let _ = service.get_donki_flr().await;
                    let _ = service.get_donki_cme().await;
                    info!("DONKI events fetched");
                }
            });
        }

        // SpaceX fetcher
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting SpaceX scheduler (every {}s)", scheduler.config.spacex_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.spacex_every_seconds));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.spacex_service.lock().await;
                    if let Err(e) = service.get_next_launch().await {
                        error!("Failed to fetch SpaceX launch: {:?}", e); 
                    } else {
                        info!("SpaceX next launch fetched");
                    }
                }
            });
        }

        info!("All background schedulers started");
    }
}