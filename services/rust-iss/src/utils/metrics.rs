use lazy_static::lazy_static;
use prometheus::{
    register_histogram_vec, register_int_counter_vec, register_int_gauge_vec, HistogramVec,
    IntCounterVec, IntGaugeVec,
};

lazy_static! {
    // HTTP request metrics
    pub static ref HTTP_REQUESTS_TOTAL: IntCounterVec = register_int_counter_vec!(
        "http_requests_total",
        "Total number of HTTP requests",
        &["method", "endpoint", "status"]
    )
    .unwrap();

    pub static ref HTTP_REQUEST_DURATION_SECONDS: HistogramVec = register_histogram_vec!(
        "http_request_duration_seconds",
        "HTTP request latency in seconds",
        &["method", "endpoint"],
        vec![0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
    )
    .unwrap();

    // Database metrics
    pub static ref DB_QUERY_DURATION_SECONDS: HistogramVec = register_histogram_vec!(
        "db_query_duration_seconds",
        "Database query latency in seconds",
        &["query_type"],
        vec![0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0]
    )
    .unwrap();

    pub static ref DB_CONNECTIONS_ACTIVE: IntGaugeVec = register_int_gauge_vec!(
        "db_connections_active",
        "Number of active database connections",
        &["pool"]
    )
    .unwrap();

    pub static ref DB_CONNECTIONS_IDLE: IntGaugeVec = register_int_gauge_vec!(
        "db_connections_idle",
        "Number of idle database connections",
        &["pool"]
    )
    .unwrap();

    // ISS scheduler metrics
    pub static ref ISS_FETCH_TOTAL: IntCounterVec = register_int_counter_vec!(
        "iss_fetch_total",
        "Total number of ISS position fetches",
        &["status"]
    )
    .unwrap();

    pub static ref ISS_FETCH_DURATION_SECONDS: HistogramVec = register_histogram_vec!(
        "iss_fetch_duration_seconds",
        "ISS fetch operation latency in seconds",
        &[],
        vec![0.1, 0.25, 0.5, 1.0, 2.0, 5.0, 10.0]
    )
    .unwrap();

    pub static ref ISS_ALTITUDE_METERS: IntGaugeVec = register_int_gauge_vec!(
        "iss_altitude_meters",
        "Current ISS altitude in meters",
        &[]
    )
    .unwrap();

    pub static ref ISS_VELOCITY_MPS: IntGaugeVec = register_int_gauge_vec!(
        "iss_velocity_mps",
        "Current ISS velocity in meters per second",
        &[]
    )
    .unwrap();

    // OSDR scheduler metrics
    pub static ref OSDR_SYNC_TOTAL: IntCounterVec = register_int_counter_vec!(
        "osdr_sync_total",
        "Total number of OSDR sync operations",
        &["status"]
    )
    .unwrap();

    pub static ref OSDR_SYNC_DURATION_SECONDS: HistogramVec = register_histogram_vec!(
        "osdr_sync_duration_seconds",
        "OSDR sync operation latency in seconds",
        &[],
        vec![0.5, 1.0, 2.0, 5.0, 10.0, 30.0, 60.0]
    )
    .unwrap();

    pub static ref OSDR_DATASETS_SYNCED: IntCounterVec = register_int_counter_vec!(
        "osdr_datasets_synced",
        "Total number of OSDR datasets synced",
        &[]
    )
    .unwrap();

    // Cache metrics
    pub static ref CACHE_HITS_TOTAL: IntCounterVec = register_int_counter_vec!(
        "cache_hits_total",
        "Total number of cache hits",
        &["cache_key"]
    )
    .unwrap();

    pub static ref CACHE_MISSES_TOTAL: IntCounterVec = register_int_counter_vec!(
        "cache_misses_total",
        "Total number of cache misses",
        &["cache_key"]
    )
    .unwrap();

    // External API metrics
    pub static ref EXTERNAL_API_REQUESTS_TOTAL: IntCounterVec = register_int_counter_vec!(
        "external_api_requests_total",
        "Total number of external API requests",
        &["api", "status"]
    )
    .unwrap();

    pub static ref EXTERNAL_API_DURATION_SECONDS: HistogramVec = register_histogram_vec!(
        "external_api_duration_seconds",
        "External API request latency in seconds",
        &["api"],
        vec![0.1, 0.5, 1.0, 2.0, 5.0, 10.0, 30.0]
    )
    .unwrap();

    // Advisory locks metrics
    pub static ref ADVISORY_LOCKS_ACQUIRED: IntCounterVec = register_int_counter_vec!(
        "advisory_locks_acquired",
        "Total number of advisory locks acquired",
        &["lock_id"]
    )
    .unwrap();

    pub static ref ADVISORY_LOCKS_FAILED: IntCounterVec = register_int_counter_vec!(
        "advisory_locks_failed",
        "Total number of failed advisory lock attempts",
        &["lock_id"]
    )
    .unwrap();
}

/// Record HTTP request metrics
pub fn record_http_request(method: &str, endpoint: &str, status: u16, duration_secs: f64) {
    HTTP_REQUESTS_TOTAL
        .with_label_values(&[method, endpoint, &status.to_string()])
        .inc();
    
    HTTP_REQUEST_DURATION_SECONDS
        .with_label_values(&[method, endpoint])
        .observe(duration_secs);
}

/// Record database query metrics
pub fn record_db_query(query_type: &str, duration_secs: f64) {
    DB_QUERY_DURATION_SECONDS
        .with_label_values(&[query_type])
        .observe(duration_secs);
}

/// Update database connection pool metrics
pub fn update_db_pool_metrics(pool_name: &str, active: usize, idle: usize) {
    DB_CONNECTIONS_ACTIVE
        .with_label_values(&[pool_name])
        .set(active as i64);
    
    DB_CONNECTIONS_IDLE
        .with_label_values(&[pool_name])
        .set(idle as i64);
}

/// Record ISS fetch metrics
pub fn record_iss_fetch(success: bool, duration_secs: f64, altitude: Option<f64>, velocity: Option<f64>) {
    let status = if success { "success" } else { "error" };
    
    ISS_FETCH_TOTAL
        .with_label_values(&[status])
        .inc();
    
    ISS_FETCH_DURATION_SECONDS
        .with_label_values(&[])
        .observe(duration_secs);

    if let Some(alt) = altitude {
        ISS_ALTITUDE_METERS
            .with_label_values(&[])
            .set(alt as i64);
    }

    if let Some(vel) = velocity {
        ISS_VELOCITY_MPS
            .with_label_values(&[])
            .set(vel as i64);
    }
}

/// Record OSDR sync metrics
pub fn record_osdr_sync(success: bool, duration_secs: f64, datasets_count: usize) {
    let status = if success { "success" } else { "error" };
    
    OSDR_SYNC_TOTAL
        .with_label_values(&[status])
        .inc();
    
    OSDR_SYNC_DURATION_SECONDS
        .with_label_values(&[])
        .observe(duration_secs);

    if success {
        OSDR_DATASETS_SYNCED
            .with_label_values(&[])
            .inc_by(datasets_count as u64);
    }
}

/// Record cache metrics
pub fn record_cache_hit(cache_key: &str) {
    CACHE_HITS_TOTAL
        .with_label_values(&[cache_key])
        .inc();
}

pub fn record_cache_miss(cache_key: &str) {
    CACHE_MISSES_TOTAL
        .with_label_values(&[cache_key])
        .inc();
}

/// Record external API metrics
pub fn record_external_api_request(api: &str, success: bool, duration_secs: f64) {
    let status = if success { "success" } else { "error" };
    
    EXTERNAL_API_REQUESTS_TOTAL
        .with_label_values(&[api, status])
        .inc();
    
    EXTERNAL_API_DURATION_SECONDS
        .with_label_values(&[api])
        .observe(duration_secs);
}

/// Record advisory lock metrics
pub fn record_advisory_lock_acquired(lock_id: i64) {
    ADVISORY_LOCKS_ACQUIRED
        .with_label_values(&[&lock_id.to_string()])
        .inc();
}

pub fn record_advisory_lock_failed(lock_id: i64) {
    ADVISORY_LOCKS_FAILED
        .with_label_values(&[&lock_id.to_string()])
        .inc();
}
