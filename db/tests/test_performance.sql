-- ============================================
-- Тест 3: Сравнение производительности
-- ============================================

-- Тест 3.1: Вставка 1000 записей (benchmark)
DO $$
DECLARE
    i INTEGER;
    start_time TIMESTAMP;
    end_time TIMESTAMP;
BEGIN
    start_time := clock_timestamp();
    
    FOR i IN 1..1000 LOOP
        INSERT INTO iss_fetch_log (latitude, longitude, altitude, velocity, timestamp, fetched_at)
        VALUES (
            random() * 180 - 90,
            random() * 360 - 180,
            400 + random() * 20,
            27000 + random() * 1000,
            NOW() + (i || ' seconds')::INTERVAL,
            NOW() + (i || ' seconds')::INTERVAL
        );
    END LOOP;
    
    end_time := clock_timestamp();
    RAISE NOTICE 'Inserted 1000 records in % ms', EXTRACT(MILLISECONDS FROM (end_time - start_time));
END $$;

-- Тест 3.2: Выборка с составным индексом
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM iss_fetch_log
WHERE fetched_at >= NOW() - INTERVAL '7 days'
  AND timestamp >= NOW() - INTERVAL '7 days'
ORDER BY timestamp DESC
LIMIT 1000;

-- Тест 3.3: Полнотекстовый поиск
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM osdr_items
WHERE to_tsvector('english', title) @@ plainto_tsquery('english', 'space station research');

-- Тест 3.4: Агрегация по партициям
EXPLAIN (ANALYZE, BUFFERS)
SELECT
    DATE_TRUNC('day', fetched_at) AS day,
    AVG(altitude) AS avg_altitude,
    AVG(velocity) AS avg_velocity,
    COUNT(*) AS records
FROM iss_fetch_log
WHERE fetched_at >= NOW() - INTERVAL '30 days'
GROUP BY day
ORDER BY day DESC;