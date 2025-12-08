-- ============================================
-- PostgreSQL Monitoring Queries
-- ============================================

-- 1. Размеры таблиц
SELECT * FROM table_sizes;

-- 2. Использование индексов
SELECT * FROM index_usage WHERE index_scans > 0 ORDER BY index_scans DESC;

-- 3. Неиспользуемые индексы (кандидаты на удаление)
SELECT
    schemaname,
    tablename,
    indexname,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC;

-- 4. Топ медленных запросов (из pg_stat_statements)
SELECT
    query,
    calls,
    total_exec_time / 1000 AS total_seconds,
    mean_exec_time / 1000 AS avg_seconds,
    stddev_exec_time / 1000 AS stddev_seconds,
    rows
FROM pg_stat_statements
WHERE query NOT LIKE '%pg_stat_statements%'
ORDER BY total_exec_time DESC
LIMIT 20;

-- 5. Анализ блокировок
SELECT
    pid,
    usename,
    pg_blocking_pids(pid) AS blocked_by,
    query AS blocked_query
FROM pg_stat_activity
WHERE cardinality(pg_blocking_pids(pid)) > 0;

-- 6. Статистика по партициям
SELECT
    parent.relname AS table_name,
    child.relname AS partition_name,
    pg_size_pretty(pg_total_relation_size(child.oid)) AS partition_size
FROM pg_inherits
JOIN pg_class parent ON pg_inherits.inhparent = parent.oid
JOIN pg_class child ON pg_inherits.inhrelid = child.oid
WHERE parent.relname = 'iss_fetch_log'
ORDER BY child.relname;

-- 7. Выполнить VACUUM ANALYZE
SELECT maintenance_vacuum_analyze();

-- 8. Сброс статистики pg_stat_statements
SELECT pg_stat_statements_reset();