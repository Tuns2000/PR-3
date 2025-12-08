-- ============================================
-- Тест 2: Проверка партиционирования
-- ============================================

-- Тест 2.1: Вставка тестовых данных в разные партиции
INSERT INTO iss_fetch_log (latitude, longitude, altitude, velocity, timestamp, fetched_at)
VALUES
    (45.0, 30.0, 408.5, 27500.0, '2024-12-01 10:00:00+00', '2024-12-01 10:00:00+00'),
    (46.0, 31.0, 409.0, 27505.0, '2025-01-15 10:00:00+00', '2025-01-15 10:00:00+00'),
    (47.0, 32.0, 409.5, 27510.0, '2025-02-20 10:00:00+00', '2025-02-20 10:00:00+00'),
    (48.0, 33.0, 410.0, 27515.0, '2025-03-25 10:00:00+00', '2025-03-25 10:00:00+00');

-- Тест 2.2: Проверка распределения по партициям
SELECT
    tableoid::regclass AS partition,
    COUNT(*) AS row_count
FROM iss_fetch_log
GROUP BY tableoid
ORDER BY partition;

-- Ожидаемый результат:
-- iss_fetch_log_2024_12 | N
-- iss_fetch_log_2025_01 | N
-- iss_fetch_log_2025_02 | N
-- iss_fetch_log_2025_03 | N

-- Тест 2.3: Проверка размера партиций
SELECT
    parent.relname AS table_name,
    child.relname AS partition_name,
    pg_size_pretty(pg_total_relation_size(child.oid)) AS partition_size,
    (SELECT COUNT(*) FROM ONLY child.oid::regclass) AS row_count
FROM pg_inherits
JOIN pg_class parent ON pg_inherits.inhparent = parent.oid
JOIN pg_class child ON pg_inherits.inhrelid = child.oid
WHERE parent.relname = 'iss_fetch_log'
ORDER BY child.relname;

-- Тест 2.4: Проверка автоматического создания партиций (триггер)
-- Вставляем запись для апреля 2025 (партиция ещё не создана)
INSERT INTO iss_fetch_log (latitude, longitude, altitude, velocity, timestamp, fetched_at)
VALUES (49.0, 34.0, 410.5, 27520.0, '2025-04-10 10:00:00+00', '2025-04-10 10:00:00+00');

-- Проверяем, что партиция создалась
SELECT tablename FROM pg_tables WHERE tablename LIKE 'iss_fetch_log_2025_04';

-- Тест 2.5: EXPLAIN для запросов с фильтрацией по партиционированному полю
EXPLAIN ANALYZE
SELECT * FROM iss_fetch_log
WHERE fetched_at >= '2025-01-01' AND fetched_at < '2025-02-01'
ORDER BY timestamp DESC
LIMIT 100;

-- Ожидаемый результат: 
-- Партиция iss_fetch_log_2025_01 (partition pruning работает)
-- Не сканируются другие партиции