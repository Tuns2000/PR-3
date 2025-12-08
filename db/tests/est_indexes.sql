-- ============================================
-- Тест 1: Проверка использования индексов
-- ============================================

-- Тест 1.1: Составной индекс (fetched_at, timestamp)
EXPLAIN ANALYZE
SELECT * FROM iss_fetch_log
WHERE fetched_at >= NOW() - INTERVAL '1 day'
  AND timestamp >= NOW() - INTERVAL '1 day'
ORDER BY timestamp DESC
LIMIT 100;

-- Ожидаемый результат: Index Scan using idx_iss_fetched_at_timestamp
-- Плохой результат: Seq Scan on iss_fetch_log

-- Тест 1.2: Геопоиск по координатам
EXPLAIN ANALYZE
SELECT * FROM iss_fetch_log
WHERE latitude BETWEEN 40 AND 50
  AND longitude BETWEEN 30 AND 40
LIMIT 10;

-- Ожидаемый результат: Index Scan using idx_iss_lat_lon

-- Тест 1.3: Полнотекстовый поиск по OSDR
EXPLAIN ANALYZE
SELECT * FROM osdr_items
WHERE to_tsvector('english', title) @@ to_tsquery('english', 'space | nasa');

-- Ожидаемый результат: Bitmap Index Scan on idx_osdr_title_gin

-- Тест 1.4: Частичный индекс на release_date
EXPLAIN ANALYZE
SELECT * FROM osdr_items
WHERE release_date IS NOT NULL
ORDER BY release_date DESC
LIMIT 20;

-- Ожидаемый результат: Index Scan using idx_osdr_release_date