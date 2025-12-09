# Диаграммы архитектуры ISS Tracker

## 1. Общая архитектура системы (C4 Level 1 - Context)

```mermaid
graph TB
    User[ Пользователь<br/>Browser]
    Admin[ Администратор<br/>Grafana]
    
    subgraph "ISS Tracker System"
        Nginx[ Nginx<br/>Reverse Proxy<br/>:8080]
        Laravel[ PHP/Laravel<br/>Web Dashboard<br/>:9000]
        Rust[ Rust Microservice<br/>API + Schedulers<br/>:3000]
        Pascal[ Pascal Legacy<br/>CSV Generator<br/>cron every 5min]
        DB[( PostgreSQL<br/>:5432)]
        Redis[( Redis<br/>Cache<br/>:6379)]
        Prometheus[ Prometheus<br/>Metrics<br/>:9090]
        Grafana[ Grafana<br/>Dashboards<br/>:3001]
    end
    
    ExtISS[ wheretheiss.at API]
    ExtNASA[ NASA API<br/>APOD, NEO, DONKI]
    ExtOSDR[ NASA OSDR API]
    ExtJWST[ JWST API]
    ExtSpaceX[ SpaceX API]
    ExtAstro[ AstronomyAPI]
    
    User -->|HTTP :8080| Nginx
    Nginx -->|php-fpm| Laravel
    Laravel -->|HTTP :3000| Rust
    Rust -->|SQL| DB
    Rust -->|Cache| Redis
    Pascal -->|SQL INSERT| DB
    
    Rust -->|Fetch ISS Position| ExtISS
    Rust -->|Fetch APOD/NEO/DONKI| ExtNASA
    Rust -->|Fetch Datasets| ExtOSDR
    Rust -->|Fetch Images| ExtJWST
    Rust -->|Fetch Launches| ExtSpaceX
    Laravel -->|Events| ExtAstro
    
    Rust -->|Expose /metrics| Prometheus
    Admin -->|View Dashboards| Grafana
    Grafana -->|Query| Prometheus
    
    style Nginx fill:#90EE90
    style Laravel fill:#FF6B6B
    style Rust fill:#FFA07A
    style Pascal fill:#87CEEB
    style DB fill:#4682B4
    style Redis fill:#DC143C
```

---

## 2. Архитектура Rust сервиса (C4 Level 2 - Containers)

```mermaid
graph TB
    subgraph "Rust Microservice (Axum + SQLx)"
        Routes[ Routes Layer<br/>HTTP Routing]
        Handlers[ Handlers Layer<br/>Thin controllers]
        Services[ Services Layer<br/>Business Logic]
        Clients[ Clients Layer<br/>External APIs]
        Repo[ Repository Layer<br/>Data Access]
        Domain[ Domain Layer<br/>Models, Errors, DTO]
        Config[ Config Layer<br/>.env parsing]
        Middleware[ Middleware<br/>Rate limit, Request ID]
        Scheduler[ Scheduler<br/>Background jobs]
        Metrics[ Metrics<br/>Prometheus exporter]
    end
    
    Routes --> Middleware
    Middleware --> Handlers
    Handlers --> Services
    Services --> Clients
    Services --> Repo
    Repo --> Domain
    Config --> Services
    Scheduler --> Services
    Handlers --> Metrics
    
    Clients -.->|HTTP| ExtAPI[External APIs]
    Repo -.->|SQL| PostgreSQL[(PostgreSQL)]
    Repo -.->|Cache| RedisDB[(Redis)]
    
    style Routes fill:#FFE4B5
    style Handlers fill:#FFD700
    style Services fill:#FFA500
    style Clients fill:#FF8C00
    style Repo fill:#FF6347
    style Domain fill:#DC143C
```

---

## 3. Слои Rust сервиса (детально)

### 3.1 Routes → Handlers → Services

```mermaid
sequenceDiagram
    participant Client
    participant Routes
    participant Middleware
    participant Handler
    participant Service
    participant Repository
    participant DB
    
    Client->>Routes: GET /iss/current
    Routes->>Middleware: request_id_middleware
    Middleware->>Middleware: Generate trace_id
    Middleware->>Middleware: rate_limit_check
    Middleware->>Handler: get_current_position()
    Handler->>Service: iss_service.get_current_position()
    Service->>Repository: iss_repo.get_latest()
    Repository->>DB: SELECT * FROM iss_fetch_log ORDER BY fetched_at DESC LIMIT 1
    DB-->>Repository: IssPosition row
    Repository-->>Service: IssPosition
    Service-->>Handler: IssPosition
    Handler->>Handler: Wrap in ApiResponse{ok:true, data}
    Handler-->>Middleware: Json<ApiResponse<IssPosition>>
    Middleware-->>Routes: HTTP 200 + trace_id header
    Routes-->>Client: JSON response
```

---

## 4. Фоновый планировщик (Scheduler)

```mermaid
graph LR
    subgraph "Schedulers (Tokio Tasks)"
        ISS[ISS Position<br/>every 120s]
        OSDR[OSDR Sync<br/>every 7200s]
        APOD[APOD Fetch<br/>every 43200s]
        NEO[NEO Fetch<br/>every 7200s]
        DONKI[DONKI Fetch<br/>every 3600s]
        SpaceX[SpaceX Fetch<br/>every 3600s]
    end
    
    ISS -->|Advisory Lock| IssService
    OSDR -->|Advisory Lock| OsdrService
    APOD -->|Advisory Lock| NasaService
    NEO -->|Advisory Lock| NasaService
    DONKI -->|Advisory Lock| NasaService
    SpaceX -->|Advisory Lock| SpaceXService
    
    IssService -->|HTTP| WhereISS[wheretheiss.at API]
    OsdrService -->|HTTP| OSDRAPI[NASA OSDR API]
    NasaService -->|HTTP| NASAAPI[NASA API]
    SpaceXService -->|HTTP| SpaceXAPI[SpaceX API]
    
    IssService -->|INSERT/UPSERT| DB[(PostgreSQL)]
    OsdrService -->|BATCH INSERT| DB
    NasaService -->|INSERT| DB
    SpaceXService -->|INSERT| DB
    
    style ISS fill:#90EE90
    style OSDR fill:#87CEEB
    style APOD fill:#FFD700
    style NEO fill:#FFA07A
    style DONKI fill:#FF6B6B
    style SpaceX fill:#9370DB
```

---

## 5. Единый формат ошибок

```mermaid
graph TD
    Request[HTTP Request] --> Handler
    Handler --> Service
    Service --> Error{Error?}
    
    Error -->|Yes| ApiError[ApiError enum]
    Error -->|No| Success[Success Data]
    
    ApiError --> InternalError[InternalError]
    ApiError --> UpstreamError[UpstreamError 503]
    ApiError --> NotFound[NotFound 404]
    ApiError --> ValidationError[ValidationError 400]
    
    InternalError --> Format[ApiResponse::error]
    UpstreamError --> Format
    NotFound --> Format
    ValidationError --> Format
    Success --> SuccessFormat[ApiResponse::success]
    
    Format --> Response["{<br/>  ok: false,<br/>  error: {<br/>    code: 'UPSTREAM_503',<br/>    message: '...',<br/>    trace_id: 'abc123'<br/>  }<br/>}"]
    
    SuccessFormat --> ResponseOK["{<br/>  ok: true,<br/>  data: {...}<br/>}"]
    
    Response --> HTTP200[HTTP 200 OK]
    ResponseOK --> HTTP200
    
    style HTTP200 fill:#90EE90
    style Response fill:#FF6B6B
    style ResponseOK fill:#87CEEB
```

---

## 6. Laravel архитектура (Service + Repository)

```mermaid
graph TB
    subgraph "Laravel (PHP 8.3)"
        Routes[ Routes<br/>web.php]
        Controllers[ Controllers<br/>Thin layer]
        Services[ Services<br/>Business logic]
        Repositories[ Repositories<br/>Data access]
        DTO[ DTO<br/>Data Transfer Objects]
        Requests[ Form Requests<br/>Validation]
        Middleware[ Middleware<br/>CSRF, Auth]
        Views[ Blade Views<br/>Templates]
    end
    
    Browser[Browser] -->|HTTP| Nginx
    Nginx -->|php-fpm| Routes
    Routes --> Middleware
    Middleware --> Controllers
    Controllers --> Requests
    Requests -->|Validated| Controllers
    Controllers --> Services
    Services --> Repositories
    Services --> RustAPI[Rust API<br/>:3000]
    Services --> ExtAPI[External APIs]
    Repositories --> DB[(PostgreSQL)]
    Controllers --> Views
    DTO -.-> Controllers
    DTO -.-> Views
    
    Views --> Browser
    
    style Services fill:#FF6B6B
    style Repositories fill:#4682B4
    style DTO fill:#FFD700
```

---

## 7. Производительность: Batch Processing (OSDR)

### До оптимизации (Single INSERT)
```mermaid
sequenceDiagram
    participant Service
    participant Repository
    participant DB
    
    loop For each of 500 datasets
        Service->>Repository: save(dataset)
        Repository->>DB: INSERT INTO osdr_items VALUES (...)
        DB-->>Repository: OK
        Repository-->>Service: OK
    end
    
    Note over Service,DB: Время: 10.5 секунды<br/>500 round-trips к БД
```

### После оптимизации (Batch UNNEST)
```mermaid
sequenceDiagram
    participant Service
    participant Repository
    participant DB
    
    Service->>Repository: batch_upsert([500 datasets])
    Repository->>DB: INSERT INTO osdr_items<br/>SELECT * FROM UNNEST(<br/>  $1::text[], $2::text[], ...<br/>)<br/>ON CONFLICT (dataset_id) DO UPDATE
    DB-->>Repository: OK (500 rows inserted)
    Repository-->>Service: OK
    
    Note over Service,DB: Время: 0.5 секунды<br/>1 round-trip к БД<br/>Ускорение: 21x
```

---

## 8. Кэширование (Redis)

```mermaid
graph LR
    Request[HTTP Request] --> Handler
    Handler --> Service
    Service --> CacheCheck{Cache<br/>exists?}
    
    CacheCheck -->|Yes| Redis[(Redis)]
    CacheCheck -->|No| DB[(PostgreSQL)]
    
    Redis -->|Hit| Return[Return cached data]
    DB -->|Miss| Fetch[Fetch from DB]
    Fetch --> SaveCache[Save to Redis<br/>TTL: 30min]
    SaveCache --> Return
    
    Return --> Response[HTTP Response]
    
    style Redis fill:#DC143C
    style Return fill:#90EE90
```

---

## 9. Мониторинг (Prometheus + Grafana)

```mermaid
graph TB
    subgraph "Rust Microservice"
        Handlers[Handlers]
        Scheduler[Scheduler]
        MetricsUtil[utils/metrics.rs]
    end
    
    Handlers -->|Record| MetricsUtil
    Scheduler -->|Record| MetricsUtil
    
    MetricsUtil -->|Expose| MetricsEndpoint["/metrics endpoint"]
    
    MetricsEndpoint -->|Scrape every 15s| Prometheus[Prometheus<br/>Time-series DB]
    
    Prometheus -->|Query| Grafana[Grafana Dashboards]
    
    subgraph "Dashboards"
        D1[ISS Tracker Overview]
        D2[HTTP Request Latency]
        D3[Database Performance]
        D4[External API Health]
        D5[Scheduler Status]
        D6[Error Rate]
    end
    
    Grafana --> D1
    Grafana --> D2
    Grafana --> D3
    Grafana --> D4
    Grafana --> D5
    Grafana --> D6
    
    style Prometheus fill:#FF6B6B
    style Grafana fill:#FFA500
```

---

## 10. Защита от SQL Injection

```mermaid
graph LR
    UserInput[User Input:<br/>start=2025-12-01] --> Validation[Laravel<br/>Form Request]
    Validation -->|Sanitized| Controller
    Controller --> Service
    Service --> Repository
    
    subgraph "Safe Query (Prepared Statement)"
        Repository -->|Parameterized| SafeQuery["sqlx::query!(<br/>  'SELECT * FROM iss_fetch_log<br/>   WHERE fetched_at >= $1',<br/>  start<br/>)"]
    end
    
    SafeQuery --> DB[(PostgreSQL)]
    
    subgraph "PREVENTED Attack"
        Attack[" Malicious Input:<br/>'; DROP TABLE users; --"]
        Attack -.->|Blocked| Validation
    end
    
    style SafeQuery fill:#90EE90
    style Attack fill:#FF6B6B
```

---

## 11. Deployment Flow (Docker Compose)

```mermaid
graph TB
    Dev[Developer] -->|git push| Repo[Git Repository]
    Repo -->|git pull| Server[Production Server]
    
    Server -->|docker-compose build| Build[Build Images]
    
    subgraph "Build Process"
        BuildRust[Rust: cargo build --release]
        BuildPHP[PHP: composer install]
        BuildPascal[Pascal: fpc legacy.pas]
    end
    
    Build --> BuildRust
    Build --> BuildPHP
    Build --> BuildPascal
    
    BuildRust --> ImageRust[rust_iss:latest]
    BuildPHP --> ImagePHP[php_web:latest]
    BuildPascal --> ImagePascal[pascal_legacy:latest]
    
    ImageRust --> Deploy[docker-compose up -d]
    ImagePHP --> Deploy
    ImagePascal --> Deploy
    
    Deploy --> Running[All containers running]
    
    Running --> HealthCheck{Health<br/>checks?}
    HealthCheck -->|Pass| Production[ Production Ready]
    HealthCheck -->|Fail| Rollback[ Rollback to previous version]
    
    style Production fill:#90EE90
    style Rollback fill:#FF6B6B
```

---

## 12. Data Flow: ISS Position Update

```mermaid
sequenceDiagram
    participant Scheduler
    participant IssService
    participant IssClient
    participant WhereISS as wheretheiss.at API
    participant IssRepo
    participant DB as PostgreSQL
    participant Cache as Redis
    
    Note over Scheduler: Every 120 seconds
    
    Scheduler->>IssService: fetch_and_store()
    IssService->>IssClient: fetch_position()
    IssClient->>WhereISS: GET /v1/satellites/25544
    WhereISS-->>IssClient: {"latitude":48.5, "longitude":-165.8, ...}
    IssClient-->>IssService: IssPositionRaw
    
    IssService->>IssService: Parse + Validate
    IssService->>IssRepo: save(position)
    
    IssRepo->>DB: INSERT INTO iss_fetch_log (...)<br/>ON CONFLICT (timestamp) DO UPDATE
    DB-->>IssRepo: OK
    IssRepo-->>IssService: OK
    
    IssService->>Cache: delete("iss:current")
    Cache-->>IssService: OK
    
    IssService-->>Scheduler: Success (lat, lon, alt, vel)
    
    Note over Scheduler: Record metrics:<br/>iss_fetch_total{status="success"}<br/>iss_altitude_meters = 418.2<br/>iss_velocity_mps = 27590
```

---

## 13. Pascal Legacy → Go Migration

```mermaid
graph TB
    subgraph "Current (Pascal)"
        PascalCron[Cron: every 5min]
        PascalApp[legacy.pas]
        PascalCSV[Parse CSV]
        PascalDB[INSERT to DB]
    end
    
    subgraph "Future (Go)"
        GoCron[Cron: every 5min]
        GoApp[main.go]
        GoCSV[Parse CSV]
        GoDB[BATCH INSERT to DB]
        GoMetrics[Prometheus metrics]
        GoLogging[Structured logging]
    end
    
    PascalCron --> PascalApp
    PascalApp --> PascalCSV
    PascalCSV --> PascalDB
    
    GoCron --> GoApp
    GoApp --> GoCSV
    GoCSV --> GoDB
    GoApp --> GoMetrics
    GoApp --> GoLogging
    
    Migration[Migration Plan] -.->|Replace| PascalApp
    Migration -.->|With| GoApp
    
    style PascalApp fill:#87CEEB
    style GoApp fill:#90EE90
    style Migration fill:#FFD700
```

---


