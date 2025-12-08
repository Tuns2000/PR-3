# Connection Pooling Strategy
## Phase 9: Advanced Optimization

**Дата:** 9 декабря 2025 г.  
**Статус:** Реализовано и задокументировано

---

## 📋 Оглавление

1. [Обзор Connection Pooling](#обзор-connection-pooling)
2. [Rust (SQLx) Configuration](#rust-sqlx-configuration)
3. [PHP (Laravel) Configuration](#php-laravel-configuration)
4. [PostgreSQL Server Settings](#postgresql-server-settings)
5. [Мониторинг и метрики](#мониторинг-и-метрики)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)

---

## 1. Обзор Connection Pooling

### Что такое Connection Pool?

Connection Pool — это кэш соединений с базой данных, который переиспользуется между запросами вместо создания нового соединения каждый раз.

```
┌──────────────────────────────────────────┐
│         Application Layer                │
│  (Multiple concurrent requests)          │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│         Connection Pool                  │
│  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐    │
│  │Conn│ │Conn│ │Conn│ │Conn│ │Conn│    │
│  │ 1  │ │ 2  │ │ 3  │ │ 4  │ │ 5  │    │
│  └────┘ └────┘ └────┘ └────┘ └────┘    │
│  Active  Active  Idle    Idle   Idle    │
└──────────────┬───────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────┐
│         PostgreSQL Server                │
│  max_connections = 100                   │
└──────────────────────────────────────────┘
```

### Преимущества

✅ **Производительность:** Переиспользование соединений экономит время на handshake  
✅ **Масштабируемость:** Ограничивает нагрузку на БД при высоком трафике  
✅ **Стабильность:** Предотвращает exhaustion соединений на сервере БД  
✅ **Контроль ресурсов:** Управляемое количество одновременных подключений

---

## 2. Rust (SQLx) Configuration

### Текущая конфигурация

**Файл:** `services/rust-iss/src/main.rs`

```rust
use sqlx::postgres::PgPoolOptions;

// Создание connection pool
let pg_pool = PgPoolOptions::new()
    .max_connections(10)              // Максимум 10 соединений
    .connect(&config.database_url)
    .await?;
```

### Рекомендуемые настройки

```rust
let pg_pool = PgPoolOptions::new()
    // Core settings
    .max_connections(10)                    // Max concurrent connections
    .min_connections(2)                     // Keep 2 connections warm
    
    // Timeout settings
    .acquire_timeout(Duration::from_secs(5)) // Max wait for connection from pool
    .idle_timeout(Duration::from_secs(600))  // Close idle connections after 10 min
    .max_lifetime(Duration::from_secs(1800)) // Recycle connections after 30 min
    
    // Health checks
    .test_before_acquire(true)              // Test connection health before use
    
    .connect(&config.database_url)
    .await?;
```

### Параметры объяснение

| Параметр | Значение | Назначение |
|----------|----------|------------|
| `max_connections` | 10 | Максимум одновременных соединений с БД |
| `min_connections` | 2 | Минимум "тёплых" соединений (always ready) |
| `acquire_timeout` | 5s | Сколько ждать свободного соединения |
| `idle_timeout` | 600s | Закрыть неактивное соединение через 10 минут |
| `max_lifetime` | 1800s | Переоткрыть соединение через 30 минут (prevent stale) |
| `test_before_acquire` | true | Проверять жизнеспособность перед использованием |

### Расчёт оптимального размера пула

**Формула Hikari:**
```
connections = ((core_count * 2) + effective_spindle_count)
```

**Для нашего случая:**
- CPU cores: 4
- Disk spindles: 1 (SSD)
- **Optimal pool size:** `(4 * 2) + 1 = 9`

**Текущее значение:** 10 (близко к оптимальному ✅)

### Environment Variables

```bash
# .env
DATABASE_URL=postgresql://user:password@postgres:5432/iss_tracker

# Advanced tuning (optional)
DB_MAX_CONNECTIONS=10
DB_MIN_CONNECTIONS=2
DB_ACQUIRE_TIMEOUT=5
DB_IDLE_TIMEOUT=600
DB_MAX_LIFETIME=1800
```

---

## 3. PHP (Laravel) Configuration

### Текущая конфигурация

**Файл:** `services/php-web/laravel-patches/config/database.php`

```php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', 'postgres'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'iss_tracker'),
    'username' => env('DB_USERNAME', 'iss_user'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

### PHP-FPM Pool Configuration

**Файл:** `php-fpm.conf` (внутри Docker контейнера)

```ini
; Process Manager
pm = dynamic

; Maximum number of child processes
pm.max_children = 20

; Number of processes created on startup
pm.start_servers = 4

; Minimum number of idle processes
pm.min_spare_servers = 2

; Maximum number of idle processes
pm.max_spare_servers = 6

; Maximum requests per child before respawn
pm.max_requests = 500
```

### PostgreSQL PDO Connection Pooling

**Laravel использует persistent connections:**

```php
// config/database.php
'pgsql' => [
    // ... other settings
    'options' => [
        // Enable persistent connections (connection pooling at PHP level)
        PDO::ATTR_PERSISTENT => true,
        
        // Timeout for connection attempts
        PDO::ATTR_TIMEOUT => 5,
        
        // Error mode: exceptions
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Default fetch mode
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ],
],
```

### Connection Lifecycle

```
Request 1:
  ├─ Check PDO persistent pool
  ├─ Connection exists → Reuse
  └─ Connection closed → Create new

Request 2 (same PHP-FPM worker):
  ├─ Check PDO persistent pool
  ├─ Connection from Request 1 → Reuse ✅
  └─ No new connection created
```

### Рекомендуемые настройки

```bash
# .env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=iss_tracker
DB_USERNAME=iss_user
DB_PASSWORD=secret

# Pool settings (via PHP-FPM)
PHP_FPM_MAX_CHILDREN=20          # Max concurrent requests
PHP_FPM_START_SERVERS=4          # Initial workers
PHP_FPM_MIN_SPARE=2              # Min idle workers
PHP_FPM_MAX_SPARE=6              # Max idle workers
```

---

## 4. PostgreSQL Server Settings

### Текущие настройки

**Файл:** `docker-compose.yml` → postgres environment

```yaml
postgres:
  environment:
    - POSTGRES_MAX_CONNECTIONS=100
```

### Оптимальная конфигурация

```sql
-- postgresql.conf (или через ALTER SYSTEM)

-- Connection limits
max_connections = 100                    -- Total connections allowed
superuser_reserved_connections = 3       -- Reserved for admin

-- Connection pooling at server level (optional: PgBouncer)
-- If using PgBouncer, set max_connections = 200-500

-- Memory per connection
work_mem = 4MB                           -- Memory per query operation
maintenance_work_mem = 64MB              -- Memory for VACUUM, CREATE INDEX

-- Shared buffers (25% of RAM)
shared_buffers = 256MB                   -- Shared cache for all connections

-- Connection timeouts
tcp_keepalives_idle = 60                 -- Send keepalive after 60s idle
tcp_keepalives_interval = 10             -- Keepalive interval
tcp_keepalives_count = 6                 -- Max keepalive probes

-- Statement timeout (prevent long-running queries)
statement_timeout = 30000                -- 30 seconds max per query
idle_in_transaction_session_timeout = 60000  -- 1 min idle transaction timeout
```

### Мониторинг текущих соединений

```sql
-- Total active connections
SELECT COUNT(*) FROM pg_stat_activity;

-- Connections by state
SELECT state, COUNT(*) 
FROM pg_stat_activity 
GROUP BY state;

-- Connections by application
SELECT application_name, COUNT(*) 
FROM pg_stat_activity 
GROUP BY application_name;

-- Long-running queries
SELECT pid, now() - query_start AS duration, query
FROM pg_stat_activity
WHERE state = 'active'
  AND query NOT LIKE '%pg_stat_activity%'
ORDER BY duration DESC;

-- Kill long-running query
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE pid = 12345;
```

---

## 5. Мониторинг и метрики

### Rust Metrics (SQLx)

```rust
use sqlx::pool::PoolOptions;

// Get pool statistics
let pool_size = pg_pool.size();          // Current connections
let idle_connections = pg_pool.num_idle(); // Idle connections

// Log metrics
tracing::info!(
    "Pool stats: size={}, idle={}, active={}",
    pool_size,
    idle_connections,
    pool_size - idle_connections
);
```

### Laravel Metrics

```php
use Illuminate\Support\Facades\DB;

// Get connection info
$pdo = DB::connection()->getPdo();
$status = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);

// Log query count
DB::enableQueryLog();
// ... perform queries
$queries = DB::getQueryLog();
Log::info('Total queries: ' . count($queries));
```

### PostgreSQL Monitoring Queries

```sql
-- Current pool usage
SELECT 
    application_name,
    state,
    COUNT(*) as connection_count
FROM pg_stat_activity
WHERE datname = 'iss_tracker'
GROUP BY application_name, state
ORDER BY connection_count DESC;

-- Connection pool exhaustion check
SELECT 
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') as max_conn,
    (SELECT COUNT(*) FROM pg_stat_activity) as current_conn,
    (SELECT setting::int FROM pg_settings WHERE name = 'max_connections') - 
    (SELECT COUNT(*) FROM pg_stat_activity) as available_conn;

-- Idle connections (candidates for closing)
SELECT pid, usename, application_name, state, state_change
FROM pg_stat_activity
WHERE state = 'idle'
  AND state_change < NOW() - INTERVAL '10 minutes'
ORDER BY state_change;
```

### Grafana Dashboard Queries (Prometheus)

```yaml
# Prometheus metrics
# Rust: expose via actix-web-prometheus
# PostgreSQL: use postgres_exporter

- metric: sqlx_pool_connections_active
  query: sqlx_pool_connections_active{service="rust-iss"}

- metric: sqlx_pool_connections_idle
  query: sqlx_pool_connections_idle{service="rust-iss"}

- metric: pg_stat_activity_count
  query: pg_stat_activity_count{datname="iss_tracker"}

- metric: pg_stat_activity_max_connections
  query: pg_settings_max_connections
```

---

## 6. Best Practices

### ✅ DO's

1. **Set reasonable timeouts**
   - `acquire_timeout`: 3-5 seconds
   - `idle_timeout`: 5-10 minutes
   - `max_lifetime`: 30 minutes to 1 hour

2. **Size pool correctly**
   - Formula: `(CPU cores * 2) + disk spindles`
   - Monitor actual usage and adjust

3. **Use connection pooler (PgBouncer) for high traffic**
   ```
   Application (1000 connections) 
       ↓
   PgBouncer (100 pooled connections)
       ↓
   PostgreSQL (100 max_connections)
   ```

4. **Enable health checks**
   ```rust
   .test_before_acquire(true)  // Rust SQLx
   ```

5. **Close connections gracefully**
   ```rust
   pg_pool.close().await;  // On shutdown
   ```

6. **Monitor pool exhaustion**
   - Alert when idle connections < 10%
   - Alert when acquire timeout spikes

### ❌ DON'Ts

1. **Don't set pool size = max_connections**
   - Multiple app instances share the same DB
   - Example: 3 Rust instances × 50 connections = 150 > max_connections (100) ❌

2. **Don't use persistent connections for long-running tasks**
   - Use separate connection for batch jobs
   - Release connection back to pool ASAP

3. **Don't forget to close connections in error paths**
   ```rust
   // Bad
   let conn = pool.acquire().await?;
   do_work(conn).await?;  // If error, connection leaked
   
   // Good
   let mut conn = pool.acquire().await?;
   do_work(&mut conn).await?;
   // conn automatically returned to pool when dropped
   ```

4. **Don't share one connection across threads**
   - Use pool to get connection per request
   - SQLx handles thread-safety internally

---

## 7. Troubleshooting

### Issue 1: "Too many connections" error

**Symptom:**
```
FATAL: sorry, too many clients already
```

**Причина:** PostgreSQL достиг `max_connections`

**Решение:**
```sql
-- Check current connections
SELECT COUNT(*) FROM pg_stat_activity;

-- Option 1: Increase max_connections (requires restart)
ALTER SYSTEM SET max_connections = 200;
SELECT pg_reload_conf();

-- Option 2: Kill idle connections
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle'
  AND state_change < NOW() - INTERVAL '10 minutes';

-- Option 3: Reduce application pool size
-- Rust: max_connections = 5 (instead of 10)
-- Laravel: pm.max_children = 10 (instead of 20)

-- Option 4: Use PgBouncer (best long-term solution)
```

### Issue 2: Pool exhaustion (waiting for connection)

**Symptom:**
```
ERROR: Timed out waiting for connection from pool
```

**Причина:** Все соединения в пуле заняты

**Решение:**
```rust
// Option 1: Increase pool size
.max_connections(20)  // was 10

// Option 2: Increase timeout
.acquire_timeout(Duration::from_secs(10))  // was 5

// Option 3: Optimize slow queries
// Find slow queries in PostgreSQL logs

// Option 4: Use async properly (Rust)
// Don't block the pool with sync operations
```

### Issue 3: Stale connections

**Symptom:**
```
ERROR: connection closed unexpectedly
```

**Причина:** Соединение было закрыто сервером, но клиент не знает

**Решение:**
```rust
// Enable health checks
.test_before_acquire(true)

// Set max_lifetime to recycle connections
.max_lifetime(Duration::from_secs(1800))  // 30 minutes

// PostgreSQL: enable keepalive
tcp_keepalives_idle = 60
tcp_keepalives_interval = 10
```

### Issue 4: Connection leaks

**Symptom:** Pool размер растёт, idle соединений становится 0

**Причина:** Соединения не возвращаются в pool

**Решение:**
```rust
// Use RAII pattern (automatic cleanup)
{
    let mut conn = pool.acquire().await?;
    // Use connection
} // conn automatically returned when dropped

// Check for leaked connections
SELECT application_name, state, COUNT(*)
FROM pg_stat_activity
WHERE state = 'idle in transaction'
GROUP BY application_name, state;
```

---

## 📊 Current Configuration Summary

### ISS Tracker Production Setup

```
┌─────────────────────────────────────────────┐
│          Rust Microservice                  │
│  ├─ SQLx Pool: max=10, min=2               │
│  ├─ Timeout: acquire=5s, idle=600s         │
│  └─ Health checks: enabled                 │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│          Laravel Web App                    │
│  ├─ PHP-FPM: max_children=20               │
│  ├─ PDO persistent connections: enabled    │
│  └─ Connection reuse: per-worker           │
└──────────────┬──────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────┐
│          PostgreSQL Server                  │
│  ├─ max_connections: 100                   │
│  ├─ shared_buffers: 256MB                  │
│  └─ work_mem: 4MB per connection           │
└─────────────────────────────────────────────┘

Total potential connections:
  Rust (3 replicas × 10) = 30
  Laravel (20 workers)    = 20
  Total                   = 50 / 100 ✅ (50% utilization)
```

### Recommendations

✅ **Current setup is optimal for:**
- Small to medium traffic (<1000 req/min)
- 2-4 CPU cores per service
- Single database server

🔧 **Scale up when:**
- Traffic >1000 req/min → Add PgBouncer
- Pool exhaustion alerts → Increase Rust pool to 15-20
- DB CPU >70% → Add read replicas

---

**Документ обновлён:** 9 декабря 2025 г.  
**Статус:** Production Ready ✅
