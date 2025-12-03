-- Basic schema

-- ✅ ISS Fetch Log
CREATE TABLE IF NOT EXISTS iss_fetch_log (
    id BIGSERIAL PRIMARY KEY,
    latitude NUMERIC(10,6) NOT NULL,
    longitude NUMERIC(10,6) NOT NULL,
    altitude NUMERIC(10,2) NOT NULL,
    velocity NUMERIC(10,2) NOT NULL,
    timestamp TIMESTAMPTZ NOT NULL UNIQUE,
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_iss_timestamp ON iss_fetch_log(timestamp);
CREATE INDEX IF NOT EXISTS idx_iss_fetched_at ON iss_fetch_log(fetched_at);

-- ✅ OSDR Items
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

-- ✅ Telemetry Legacy
CREATE TABLE IF NOT EXISTS telemetry_legacy (
    id BIGSERIAL PRIMARY KEY,
    recorded_at TIMESTAMPTZ NOT NULL,
    voltage NUMERIC(6,2) NOT NULL,
    temp NUMERIC(6,2) NOT NULL,
    source_file TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_telemetry_recorded_at ON telemetry_legacy(recorded_at);

-- ✅ CMS Pages
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
