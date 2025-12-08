-- Basic schema

--  OSDR Items
CREATE TABLE IF NOT EXISTS osdr_items (
    id BIGSERIAL PRIMARY KEY,
    dataset_id VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    release_date DATE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_osdr_dataset_id ON osdr_items(dataset_id);
CREATE INDEX IF NOT EXISTS idx_osdr_updated_at ON osdr_items(updated_at);

--  Telemetry Legacy
CREATE TABLE IF NOT EXISTS telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL,
    temp NUMERIC(6,2) NOT NULL,
    source_file TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_telemetry_recorded_at ON telemetry_legacy(recorded_at);

--  CMS Pages
CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL
);

-- Seed CMS pages
INSERT INTO cms_pages(slug, title, body)
VALUES
('welcome', 'Добро пожаловать', '<h3>Демо контент</h3><p>Этот текст хранится в БД</p>'),
('unsafe', 'Небезопасный пример', '<script>console.log("XSS training")</script><p>Если вы видите всплывашку значит защита не работает</p>')
ON CONFLICT (slug) DO NOTHING;

-- ============================================
-- PHASE 4: PostgreSQL Optimization
-- ============================================

-- Включение расширений
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS btree_gin;

-- ============================================
-- ISS Fetch Log (с партиционированием)
-- ============================================
CREATE TABLE IF NOT EXISTS iss_fetch_log (
    id BIGSERIAL,
    latitude NUMERIC(10,6) NOT NULL,
    longitude NUMERIC(10,6) NOT NULL,
    altitude NUMERIC(10,2) NOT NULL,
    velocity NUMERIC(10,2) NOT NULL,
    timestamp TIMESTAMPTZ NOT NULL,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id, fetched_at)
) PARTITION BY RANGE (fetched_at);

-- Партиции по месяцам
CREATE TABLE IF NOT EXISTS iss_fetch_log_2024_12 PARTITION OF iss_fetch_log
    FOR VALUES FROM ('2024-12-01') TO ('2025-01-01');

CREATE TABLE IF NOT EXISTS iss_fetch_log_2025_01 PARTITION OF iss_fetch_log
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

CREATE TABLE IF NOT EXISTS iss_fetch_log_2025_02 PARTITION OF iss_fetch_log
    FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');

CREATE TABLE IF NOT EXISTS iss_fetch_log_2025_03 PARTITION OF iss_fetch_log
    FOR VALUES FROM ('2025-03-01') TO ('2025-04-01');

-- Индексы на партиционированной таблице
CREATE INDEX IF NOT EXISTS idx_iss_timestamp ON iss_fetch_log(timestamp);
CREATE INDEX IF NOT EXISTS idx_iss_fetched_at ON iss_fetch_log(fetched_at);
CREATE INDEX IF NOT EXISTS idx_iss_fetched_at_timestamp ON iss_fetch_log(fetched_at, timestamp);
CREATE INDEX IF NOT EXISTS idx_iss_lat_lon ON iss_fetch_log(latitude, longitude);
-- UNIQUE индекс должен включать колонку партиционирования
CREATE UNIQUE INDEX IF NOT EXISTS idx_iss_timestamp_unique ON iss_fetch_log(timestamp, fetched_at);

-- ============================================
-- OSDR Items (с полнотекстовым поиском)
-- ============================================
CREATE TABLE IF NOT EXISTS osdr_items (
    id BIGSERIAL PRIMARY KEY,
    dataset_id VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    release_date DATE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_osdr_dataset_id ON osdr_items(dataset_id);
CREATE INDEX IF NOT EXISTS idx_osdr_updated_at ON osdr_items(updated_at);
CREATE INDEX IF NOT EXISTS idx_osdr_release_date ON osdr_items(release_date) WHERE release_date IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_osdr_title_gin ON osdr_items USING gin(to_tsvector('english', title));
CREATE INDEX IF NOT EXISTS idx_osdr_description_gin ON osdr_items USING gin(to_tsvector('english', coalesce(description, '')));

-- ============================================
-- Telemetry Legacy
-- ============================================
CREATE TABLE IF NOT EXISTS telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL,
    temp NUMERIC(6,2) NOT NULL,
    source_file TEXT NOT NULL,
    CONSTRAINT voltage_range CHECK (voltage BETWEEN 0 AND 20),
    CONSTRAINT temp_range CHECK (temp BETWEEN -50 AND 100)
);

CREATE INDEX IF NOT EXISTS idx_telemetry_recorded_at ON telemetry_legacy(recorded_at);
CREATE INDEX IF NOT EXISTS idx_telemetry_source_file ON telemetry_legacy(source_file);

-- ============================================
-- CMS Pages
-- ============================================
CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGSERIAL PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO cms_pages(slug, title, body)
VALUES
('welcome', 'Добро пожаловать', '<h3>Демо контент</h3><p>Этот текст хранится в БД</p>'),
('unsafe', 'Небезопасный пример', '<script>console.log("XSS training")</script><p>Если вы видите всплывашку значит защита не работает</p>')
ON CONFLICT (slug) DO NOTHING;

-- ============================================
-- Автоматическое создание партиций
-- ============================================
CREATE OR REPLACE FUNCTION create_partition_if_not_exists(
    table_name TEXT,
    partition_date DATE
) RETURNS VOID AS $$
DECLARE
    partition_name TEXT;
    start_date DATE;
    end_date DATE;
BEGIN
    partition_name := table_name || '_' || TO_CHAR(partition_date, 'YYYY_MM');
    start_date := DATE_TRUNC('month', partition_date);
    end_date := start_date + INTERVAL '1 month';
    
    IF NOT EXISTS (
        SELECT 1 FROM pg_tables 
        WHERE schemaname = 'public' AND tablename = partition_name
    ) THEN
        EXECUTE FORMAT(
            'CREATE TABLE %I PARTITION OF %I FOR VALUES FROM (%L) TO (%L)',
            partition_name, table_name, start_date, end_date
        );
        RAISE NOTICE 'Created partition: %', partition_name;
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION auto_create_partition()
RETURNS TRIGGER AS $$
BEGIN
    PERFORM create_partition_if_not_exists('iss_fetch_log', NEW.fetched_at::DATE);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_auto_create_partition
BEFORE INSERT ON iss_fetch_log
FOR EACH ROW
EXECUTE FUNCTION auto_create_partition();

-- ============================================
-- Представления для мониторинга
-- ============================================
CREATE OR REPLACE VIEW table_sizes AS
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS indexes_size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

CREATE OR REPLACE VIEW index_usage AS
SELECT
    schemaname,
    relname AS tablename,
    indexrelname AS indexname,
    idx_scan AS index_scans,
    idx_tup_read AS tuples_read,
    idx_tup_fetch AS tuples_fetched,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
ORDER BY idx_scan DESC;

-- ============================================
-- Функция обслуживания
-- ============================================
CREATE OR REPLACE FUNCTION maintenance_vacuum_analyze()
RETURNS TEXT AS $$
DECLARE
    tbl RECORD;
    result TEXT := '';
BEGIN
    FOR tbl IN 
        SELECT schemaname, tablename 
        FROM pg_tables 
        WHERE schemaname = 'public'
    LOOP
        EXECUTE 'VACUUM ANALYZE ' || quote_ident(tbl.schemaname) || '.' || quote_ident(tbl.tablename);
        result := result || tbl.tablename || ' vacuumed; ';
    END LOOP;
    RETURN result;
END;
$$ LANGUAGE plpgsql;

-- Комментарии к таблицам
COMMENT ON TABLE iss_fetch_log IS 'ISS position tracking logs (partitioned by month)';
COMMENT ON TABLE osdr_items IS 'NASA OSDR datasets cache';
COMMENT ON TABLE telemetry_legacy IS 'Legacy telemetry data from Pascal service';
COMMENT ON TABLE cms_pages IS 'CMS pages for static content';

COMMENT ON COLUMN iss_fetch_log.timestamp IS 'Original ISS timestamp from API';
COMMENT ON COLUMN iss_fetch_log.fetched_at IS 'When we fetched this data (partition key)';
COMMENT ON COLUMN osdr_items.dataset_id IS 'Unique OSDR dataset identifier';
