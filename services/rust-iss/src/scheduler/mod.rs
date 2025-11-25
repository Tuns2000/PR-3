use crate::{
    config::Config,
    services::{IssService, OsdrService, NasaService, SpaceXService},
};
use std::{sync::Arc, time::Duration};
use tokio::sync::Mutex;
use tracing::{error, info};

pub struct Scheduler {
    config: Config,
    iss_service: Arc<Mutex<IssService>>,
    osdr_service: Arc<Mutex<OsdrService>>,
    nasa_service: Arc<Mutex<NasaService>>,
    spacex_service: Arc<Mutex<SpaceXService>>,
}

impl Scheduler {
    pub fn new(
        config: Config,
        iss_service: Arc<Mutex<IssService>>,
        osdr_service: Arc<Mutex<OsdrService>>,
        nasa_service: Arc<Mutex<NasaService>>,
        spacex_service: Arc<Mutex<SpaceXService>>,
    ) -> Self {
        Self {
            config,
            iss_service,
            osdr_service,
            nasa_service,
            spacex_service,
        }
    }

    pub fn start(self: Arc<Self>) {
        // ISS fetcher
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting ISS scheduler (every {}s)", scheduler.config.iss_every_seconds);
                let mut interval = tokio::time::interval(Duration::from_secs(scheduler.config.iss_every_seconds));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.iss_service.lock().await;
                    match service.fetch_and_store().await {
                        Ok(position) => {
                            info!("ISS position updated: lat={}, lon={}", position.latitude, position.longitude);
                        }
                        Err(e) => {
                            error!("Failed to fetch ISS position: {:?}", e); 
                        }
                    }
                }
            });
        }

        // OSDR syncer (каждые 2 часа)
        {
            let scheduler = self.clone();
            tokio::spawn(async move {
                info!("Starting OSDR scheduler (every 7200s)");
                let mut interval = tokio::time::interval(Duration::from_secs(7200));
                
                loop {
                    interval.tick().await;
                    
                    let mut service = scheduler.osdr_service.lock().await;
                    match service.sync_datasets().await {
                        Ok(count) => {
                            info!("OSDR synced {} datasets", count);
                        }
                        Err(e) => {
                            error!("Failed to sync OSDR: {:?}", e); 
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