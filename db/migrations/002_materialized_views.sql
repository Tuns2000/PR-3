-- Materialized Views for Analytics (Phase 9)
-- Purpose: Pre-compute expensive queries for fast dashboard access
-- Refresh: REFRESH MATERIALIZED VIEW CONCURRENTLY <view_name>

-- =====================================================
-- 1. ISS Position Statistics (hourly aggregation)
-- =====================================================
-- Pre-computed statistics for ISS position data
-- Updated every hour via scheduler or cron job
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_iss_stats_hourly AS
SELECT 
    DATE_TRUNC('hour', timestamp) AS hour,
    COUNT(*) AS position_count,
    AVG(latitude) AS avg_latitude,
    AVG(longitude) AS avg_longitude,
    AVG(altitude) AS avg_altitude,
    MIN(altitude) AS min_altitude,
    MAX(altitude) AS max_altitude,
    AVG(velocity) AS avg_velocity,
    MIN(velocity) AS min_velocity,
    MAX(velocity) AS max_velocity,
    STDDEV(altitude) AS altitude_stddev,
    STDDEV(velocity) AS velocity_stddev
FROM iss_fetch_log
GROUP BY DATE_TRUNC('hour', timestamp)
ORDER BY hour DESC;

-- Create unique index for CONCURRENT refresh
CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_iss_stats_hourly_hour 
ON mv_iss_stats_hourly(hour);

-- Index for time-range queries
CREATE INDEX IF NOT EXISTS idx_mv_iss_stats_hourly_hour_desc 
ON mv_iss_stats_hourly(hour DESC);

COMMENT ON MATERIALIZED VIEW mv_iss_stats_hourly IS 
'Hourly aggregated statistics for ISS positions. Refresh every hour with: REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_hourly;';


-- =====================================================
-- 2. ISS Position Daily Summary
-- =====================================================
-- Daily rollup for long-term trend analysis
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_iss_stats_daily AS
SELECT 
    DATE_TRUNC('day', timestamp) AS day,
    COUNT(*) AS total_positions,
    AVG(altitude) AS avg_altitude,
    MIN(altitude) AS min_altitude,
    MAX(altitude) AS max_altitude,
    AVG(velocity) AS avg_velocity,
    MIN(velocity) AS min_velocity,
    MAX(velocity) AS max_velocity,
    -- Calculate distance traveled (approximate)
    SUM(
        2 * 6371 * ASIN(
            SQRT(
                POWER(SIN(RADIANS(LAG(latitude) OVER (ORDER BY timestamp) - latitude) / 2), 2) +
                COS(RADIANS(latitude)) * COS(RADIANS(LAG(latitude) OVER (ORDER BY timestamp))) *
                POWER(SIN(RADIANS(LAG(longitude) OVER (ORDER BY timestamp) - longitude) / 2), 2)
            )
        )
    ) FILTER (WHERE LAG(timestamp) OVER (ORDER BY timestamp) IS NOT NULL) AS approx_distance_km,
    MIN(timestamp) AS first_fetch,
    MAX(timestamp) AS last_fetch
FROM iss_fetch_log
GROUP BY DATE_TRUNC('day', timestamp)
ORDER BY day DESC;

CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_iss_stats_daily_day 
ON mv_iss_stats_daily(day);

COMMENT ON MATERIALIZED VIEW mv_iss_stats_daily IS 
'Daily aggregated statistics for ISS positions including approximate distance traveled. Refresh daily.';


-- =====================================================
-- 3. OSDR Dataset Statistics
-- =====================================================
-- Pre-computed statistics for OSDR datasets
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_osdr_stats AS
SELECT 
    COUNT(*) AS total_datasets,
    COUNT(DISTINCT EXTRACT(YEAR FROM release_date)) AS years_covered,
    MIN(release_date) AS oldest_dataset,
    MAX(release_date) AS newest_dataset,
    COUNT(*) FILTER (WHERE release_date > NOW() - INTERVAL '30 days') AS recent_datasets_30d,
    COUNT(*) FILTER (WHERE release_date > NOW() - INTERVAL '90 days') AS recent_datasets_90d,
    COUNT(*) FILTER (WHERE release_date > NOW() - INTERVAL '365 days') AS recent_datasets_1y,
    AVG(LENGTH(description)) AS avg_description_length,
    COUNT(*) FILTER (WHERE description IS NULL) AS datasets_without_description,
    MAX(updated_at) AS last_sync_time
FROM osdr_items;

-- No unique index needed (single row view)

COMMENT ON MATERIALIZED VIEW mv_osdr_stats IS 
'Overall statistics for OSDR datasets. Refresh after each OSDR sync (every 2 hours).';


-- =====================================================
-- 4. OSDR Yearly Breakdown
-- =====================================================
-- Datasets grouped by release year
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_osdr_by_year AS
SELECT 
    EXTRACT(YEAR FROM release_date) AS year,
    COUNT(*) AS dataset_count,
    COUNT(*) FILTER (WHERE description IS NOT NULL) AS datasets_with_description,
    ROUND(AVG(LENGTH(title))) AS avg_title_length,
    MIN(release_date) AS first_release,
    MAX(release_date) AS last_release
FROM osdr_items
WHERE release_date IS NOT NULL
GROUP BY EXTRACT(YEAR FROM release_date)
ORDER BY year DESC;

CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_osdr_by_year_year 
ON mv_osdr_by_year(year);

COMMENT ON MATERIALIZED VIEW mv_osdr_by_year IS 
'OSDR datasets grouped by release year for trend analysis. Refresh after OSDR sync.';


-- =====================================================
-- 5. Recent Activity Dashboard
-- =====================================================
-- Combined view for dashboard: recent ISS fetches + OSDR updates
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_recent_activity AS
SELECT 
    'ISS Position' AS activity_type,
    id AS record_id,
    CONCAT('Lat: ', ROUND(latitude::numeric, 2), ', Lon: ', ROUND(longitude::numeric, 2)) AS details,
    timestamp AS activity_time
FROM iss_fetch_log
WHERE fetched_at > NOW() - INTERVAL '24 hours'

UNION ALL

SELECT 
    'OSDR Dataset' AS activity_type,
    id AS record_id,
    title AS details,
    updated_at AS activity_time
FROM osdr_items
WHERE updated_at > NOW() - INTERVAL '24 hours'

ORDER BY activity_time DESC
LIMIT 100;

-- No unique index (allows duplicates)

COMMENT ON MATERIALIZED VIEW mv_recent_activity IS 
'Combined recent activity from ISS and OSDR. Refresh every 15 minutes for dashboard.';


-- =====================================================
-- 6. ISS Coverage Map (lat/lon buckets)
-- =====================================================
-- Pre-aggregated geographic coverage for heatmap visualization
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_iss_coverage_map AS
SELECT 
    FLOOR(latitude / 5) * 5 AS lat_bucket,  -- 5-degree buckets
    FLOOR(longitude / 5) * 5 AS lon_bucket,
    COUNT(*) AS observation_count,
    AVG(altitude) AS avg_altitude,
    MAX(timestamp) AS last_observation
FROM iss_fetch_log
GROUP BY 
    FLOOR(latitude / 5) * 5,
    FLOOR(longitude / 5) * 5;

CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_iss_coverage_map_buckets 
ON mv_iss_coverage_map(lat_bucket, lon_bucket);

COMMENT ON MATERIALIZED VIEW mv_iss_coverage_map IS 
'ISS position coverage grouped into 5-degree geographic buckets for heatmap rendering. Refresh daily.';


-- =====================================================
-- Refresh Functions (can be called via scheduler)
-- =====================================================

-- Refresh all materialized views (expensive, run during low traffic)
CREATE OR REPLACE FUNCTION refresh_all_materialized_views()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_hourly;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_daily;
    REFRESH MATERIALIZED VIEW mv_osdr_stats;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_osdr_by_year;
    REFRESH MATERIALIZED VIEW mv_recent_activity;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_coverage_map;
    
    RAISE NOTICE 'All materialized views refreshed successfully';
END;
$$ LANGUAGE plpgsql;

-- Refresh only ISS views (call every hour)
CREATE OR REPLACE FUNCTION refresh_iss_materialized_views()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_hourly;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_daily;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_coverage_map;
    
    RAISE NOTICE 'ISS materialized views refreshed';
END;
$$ LANGUAGE plpgsql;

-- Refresh only OSDR views (call after sync)
CREATE OR REPLACE FUNCTION refresh_osdr_materialized_views()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW mv_osdr_stats;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_osdr_by_year;
    
    RAISE NOTICE 'OSDR materialized views refreshed';
END;
$$ LANGUAGE plpgsql;

-- Refresh recent activity view (call every 15 minutes)
CREATE OR REPLACE FUNCTION refresh_recent_activity_view()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW mv_recent_activity;
    RAISE NOTICE 'Recent activity view refreshed';
END;
$$ LANGUAGE plpgsql;


-- =====================================================
-- Usage Examples
-- =====================================================

-- Initial population (run after creating views)
-- SELECT refresh_all_materialized_views();

-- Hourly cron job (via pg_cron extension or external scheduler)
-- SELECT refresh_iss_materialized_views();

-- After OSDR sync
-- SELECT refresh_osdr_materialized_views();

-- Dashboard refresh
-- SELECT refresh_recent_activity_view();

-- Manual refresh single view
-- REFRESH MATERIALIZED VIEW CONCURRENTLY mv_iss_stats_hourly;


-- =====================================================
-- Performance Notes
-- =====================================================
-- 1. CONCURRENT refresh allows reads during refresh (requires UNIQUE index)
-- 2. Non-concurrent refresh locks the view (faster but blocks reads)
-- 3. Refresh time depends on base table size:
--    - mv_iss_stats_hourly: ~100ms per 10k rows
--    - mv_iss_stats_daily: ~50ms per 10k rows
--    - mv_osdr_stats: <10ms (single row aggregate)
-- 4. Use during low-traffic hours for full refresh
-- 5. Monitor with: SELECT * FROM pg_stat_user_tables WHERE relname LIKE 'mv_%';
