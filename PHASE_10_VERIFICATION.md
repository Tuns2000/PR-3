
## 📋 Выполненные задачи

### 1. ✅ Prometheus Metrics

**Реализовано:**
- 15+ метрик в `services/rust-iss/src/utils/metrics.rs`
- Endpoint: `http://localhost:8082/metrics`
- Формат: Prometheus text exposition format

**Категории метрик:**

#### HTTP Метрики
```promql
http_requests_total{method,endpoint,status}
http_request_duration_seconds (histogram)
```

#### ISS Метрики
```promql
iss_fetch_total{status="success|error"}
iss_fetch_duration_seconds (histogram)
iss_altitude_meters (gauge) - текущая высота МКС
iss_velocity_mps (gauge) - текущая скорость МКС
```

#### OSDR Метрики
```promql
osdr_sync_total{status="success|error"}
osdr_sync_duration_seconds (histogram)
osdr_datasets_synced (gauge)
```

#### Database Метрики
```promql
db_connections_active
db_connections_idle
db_query_duration_seconds (histogram)
```

#### Cache Метрики
```promql
cache_hits_total
cache_misses_total
```

#### Advisory Locks
```promql
advisory_locks_acquired{lock_id="1001|1002"}
advisory_locks_failed{lock_id}
```

#### External API
```promql
external_api_requests_total{service,status}
external_api_duration_seconds{service} (histogram)
```

---

### 2. ✅ Prometheus Server

**Конфигурация:** `monitoring/prometheus/prometheus.yml`

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 30s
  external_labels:
    cluster: 'iss-tracker'
    environment: 'production'

scrape_configs:
  - job_name: 'rust-iss'
    scrape_interval: 10s
    static_configs:
      - targets: ['rust_iss:3000']
        labels:
          service: 'rust-iss'
```

**Статус:**
```
✅ http://localhost:9090 - UP
✅ /-/healthy - HTTP 200 OK
✅ Scraping rust_iss every 10 seconds
```

---

### 3. ✅ Alert Rules

**Файл:** `monitoring/prometheus/alerts.yml`  
**Количество:** 11 alert rules

#### Critical Alerts
1. **ServiceDown** - Сервис недоступен (>1m)
2. **OSDRSyncFailure** - Ошибки синхронизации OSDR (>10%)
3. **HighHTTPErrorRate** - HTTP 5xx ошибки (>5%)

#### Warning Alerts
4. **HighISSFetchErrorRate** - Ошибки получения ISS позиции (>10%)
5. **SlowISSFetch** - Медленные запросы ISS (p95 > 5s)
6. **LowDatabaseConnectionPool** - Мало свободных соединений (<10%)

#### Info Alerts
7. **HighAdvisoryLockContention** - Конкуренция за lock (>50%)
8. **LowCacheHitRate** - Низкий cache hit rate (<50%)

**Исправление:**
- ❌ Ошибка: `binary expression must contain only scalar and instant vector types`
- ✅ Исправлено: Использованы `sum()` для агрегации векторов

---

### 4. ✅ Grafana Dashboard

**Доступ:**
- URL: http://localhost:3001
- Login: `admin`
- Password: `admin`

**Dashboard:** ISS Tracker - System Overview

#### Панели (6 total):

1. **HTTP Request Rate**
   - Тип: Time series
   - Query: `rate(http_requests_total[5m])`
   - Разбивка: method, endpoint, status

2. **HTTP Request Latency (p95)**
   - Тип: Gauge
   - Query: `histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))`
   - Thresholds: 100ms (warning), 500ms (critical)

3. **ISS Altitude**
   - Тип: Time series
   - Query: `iss_altitude_meters`
   - Unit: km

4. **Database Connection Pool**
   - Тип: Time series
   - Queries:
     - `db_connections_active`
     - `db_connections_idle`
   - Legend: active + idle

5. **ISS Fetch Rate**
   - Тип: Time series
   - Query: `rate(iss_fetch_total[5m])`
   - Split: by status (success/error)

6. **Cache Hit/Miss Rate**
   - Тип: Time series
   - Queries:
     - `rate(cache_hits_total[5m])`
     - `rate(cache_misses_total[5m])`

**Провизионинг:**
```
✅ Datasource: Prometheus (http://prometheus:9090)
✅ Dashboard: Auto-provisioned from JSON
✅ Refresh: Every 10 seconds
```

---

### 5. ✅ JSON Structured Logging

**Реализовано:** `services/rust-iss/src/main.rs`

**Функционал:**
- Условное форматирование на основе `LOG_FORMAT` env var
- `LOG_FORMAT=json` → JSON structured logs
- Default → Human-readable text logs

**Пример JSON log:**
```json
{
  "timestamp": "2025-12-09T02:19:30.123Z",
  "level": "INFO",
  "target": "rust_iss::scheduler",
  "fields": {
    "message": "ISS position fetched successfully",
    "lock_id": 1001,
    "altitude_km": 431,
    "velocity_mps": 27552,
    "duration_ms": 994
  },
  "span": {
    "name": "fetch_iss_position",
    "request_id": "abc123"
  }
}
```

**Dependencies:**
```toml
tracing-subscriber = { version = "0.3", features = ["json"] }
```

---

## 🔧 Исправленные проблемы

### 1. Type Mismatch в osdr_repo.rs
**Проблема:**
```rust
let release_dates: Vec<Option<DateTime<Utc>>> = 
    datasets.iter().map(|d| d.release_date).collect();
// Error: expected DateTime<Utc>, found NaiveDateTime
```

**Решение:**
```rust
let release_dates: Vec<Option<chrono::NaiveDateTime>> = 
    datasets.iter().map(|d| d.release_date).collect();
let updated_ats: Vec<chrono::NaiveDateTime> = 
    datasets.iter().map(|d| d.updated_at).collect();
```

### 2. Prometheus Alert Rule Syntax Error
**Проблема:**
```yaml
expr: |
  (
    rate(advisory_locks_failed[5m])
    /
    rate(advisory_locks_acquired[5m] + advisory_locks_failed[5m])
  ) > 0.5
# Error: binary expression must contain only scalar and instant vector types
```

**Решение:**
```yaml
expr: |
  sum(rate(advisory_locks_failed[5m]))
  /
  (sum(rate(advisory_locks_acquired[5m])) + sum(rate(advisory_locks_failed[5m]))) > 0.5
```

---

## 📊 Живые данные (Verification)

### Metrics Endpoint (http://localhost:8082/metrics)

```promql
# ISS Tracking
iss_altitude_meters 431
iss_velocity_mps 27552
iss_fetch_total{status="success"} 2
iss_fetch_duration_seconds_sum 1.997  # ~2s total
iss_fetch_duration_seconds_count 2

# Advisory Locks
advisory_locks_acquired{lock_id="1001"} 2  # ISS scheduler
advisory_locks_acquired{lock_id="1002"} 1  # OSDR scheduler

# OSDR Sync
osdr_sync_total{status="error"} 1
osdr_sync_duration_seconds_sum 3.010  # ~3s
osdr_sync_duration_seconds_count 1
```

### Prometheus Status
```bash
curl http://localhost:9090/-/healthy
# HTTP 200 OK
```

### Grafana Status
```bash
curl http://localhost:3001/api/health
# {"commit": "...", "database": "ok", "version": "..."}
```

---

## 📁 Новые файлы

### Metrics Implementation
- `services/rust-iss/src/utils/metrics.rs` - Все метрики
- `services/rust-iss/src/utils/mod.rs` - Module export

### Prometheus Configuration
- `monitoring/prometheus/prometheus.yml` - Scrape config
- `monitoring/prometheus/alerts.yml` - 11 alert rules

### Grafana Configuration
- `monitoring/grafana/provisioning/datasources/prometheus.yml`
- `monitoring/grafana/provisioning/dashboards/dashboards.yml`
- `monitoring/grafana/dashboards/iss-tracker-overview.json`

### Modified Files
- `services/rust-iss/Cargo.toml` - Added prometheus dependencies
- `services/rust-iss/src/main.rs` - JSON logging
- `services/rust-iss/src/routes/mod.rs` - /metrics endpoint
- `services/rust-iss/src/scheduler/mod.rs` - Metrics integration
- `docker-compose.yml` - prometheus + grafana services

---

## 🚀 Deployment Guide

### 1. Rebuild Rust Service
```powershell
docker-compose build rust_iss
docker-compose up -d rust_iss
```

### 2. Start Monitoring Stack
```powershell

```

### 3. Verify Endpoints
```powershell
# Metrics
curl http://localhost:8082/metrics | Select-String "iss_"

# Prometheus
curl http://localhost:9090/-/healthy

# Grafana
Start-Process http://localhost:3001
```

### 4. Access Dashboards
- **Prometheus:** http://localhost:9090
  - Targets: Status → Targets
  - Alerts: Alerts → Rules
  
- **Grafana:** http://localhost:3001
  - Username: `admin`
  - Password: `admin`
  - Navigate: Dashboards → ISS Tracker - System Overview

---
# Metrics
curl http://localhost:8082/metrics | Select-String "iss_"

# Prometheus
Start-Process http://localhost:9090

# Grafana (admin/admin)
Start-Process http://localhost:3001
#