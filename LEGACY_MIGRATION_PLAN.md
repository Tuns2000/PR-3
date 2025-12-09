# План миграции Pascal Legacy на современный стек
**Текущее состояние:** Pascal Legacy (Free Pascal/Object Pascal) используется с 2008 года для генерации CSV и записи телеметрических данных в PostgreSQL.


---

## Анализ текущей реализации

### Функциональность Pascal Legacy

```pascal
// services/pascal-legacy/legacy.pas
program legacy;

Основные функции:
1. ConnectToDatabase() - подключение к PostgreSQL
2. ParseCSV() - парсинг CSV файлов
3. ProcessRow() - обработка строк CSV
4. CheckVoltageRange() - валидация напряжения (11.5V - 14.5V)
5. CalculateAverage() - расчёт средних значений
6. WriteToDatabase() - запись в БД (INSERT)
7. GenerateCSVReport() - генерация отчётов
```

### Контракт и форматы

**Входные данные:**
```csv
timestamp,voltage,temperature,source_file
2025-12-09 03:00:00,12.5,25.3,telemetry_001.csv
2025-12-09 03:15:00,13.1,26.1,telemetry_001.csv
```

**Таблица БД:**
```sql
CREATE TABLE telemetry (
    id SERIAL PRIMARY KEY,
    recorded_at TIMESTAMP,
    voltage DOUBLE PRECISION,
    temperature DOUBLE PRECISION,
    source_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW()
);
```

**Расписание:** Каждые 5 минут (300 секунд) через cron

---

## Сравнение стеков для миграции

### Вариант 1: Rust CLI

**Преимущества:**
- Максимальная производительность (близко к C)
-  Строгая типизация и безопасность памяти
-  Нативная компиляция (один бинарник)
-  Уже используется в проекте (rust_iss)
-  Отличная поддержка PostgreSQL (sqlx, tokio-postgres)

**Недостатки:**
-  Сложнее для junior-разработчиков
-  Дольше время компиляции
-  Более крутая кривая обучения

**Оценка сложности:** 7/10  
**Время разработки:** 2 недели

---

### Вариант 2: Go CLI  

**Преимущества:**
-  **Баланс между скоростью и простотой**
-  Быстрая компиляция (<5 секунд)
-  Нативная компиляция (один бинарник без зависимостей)
-  Встроенная поддержка concurrency (goroutines)
-  Стандартная библиотека `encoding/csv` и `database/sql`
-  Хорошая поддержка PostgreSQL (lib/pq, pgx)
-  Простой синтаксис (легко читать/поддерживать)
-  Кроссплатформенность (Linux, Windows, macOS)

**Недостатки:**
-  Менее строгая типизация чем Rust
-  Нужно вручную управлять ошибками

**Оценка сложности:** 4/10  
**Время разработки:** 1-1.5 недели

**Пример кода Go:**
```go
package main

import (
    "database/sql"
    "encoding/csv"
    "log"
    "os"
    _ "github.com/lib/pq"
)

type TelemetryRecord struct {
    Timestamp   string
    Voltage     float64
    Temperature float64
    SourceFile  string
}

func main() {
    db, err := sql.Open("postgres", os.Getenv("DATABASE_URL"))
    if err != nil {
        log.Fatal(err)
    }
    defer db.Close()

    // Parse CSV
    file, _ := os.Open("telemetry.csv")
    reader := csv.NewReader(file)
    records, _ := reader.ReadAll()

    for _, record := range records[1:] { // Skip header
        voltage, _ := strconv.ParseFloat(record[1], 64)
        
        if voltage < 11.5 || voltage > 14.5 {
            log.Println("WARNING: Voltage out of range:", voltage)
        }

        _, err := db.Exec(`
            INSERT INTO telemetry (recorded_at, voltage, temperature, source_file)
            VALUES ($1, $2, $3, $4)
        `, record[0], voltage, record[2], record[3])
        
        if err != nil {
            log.Println("ERROR:", err)
        }
    }
}
```

---



### Почему Go?

1. **Простота поддержки:** Синтаксис Go можно освоить за 1-2 дня
2. **Производительность:** В 10-20 раз быстрее Python, близко к Rust для I/O операций
3. **Надёжность:** Статическая типизация + встроенное тестирование
4. **DevOps friendly:** Один бинарник без зависимостей
5. **Совместимость:** Легко интегрируется с существующей инфраструктурой

---

## Детальный план миграции

### Фаза 1: Подготовка 



**Deliverables:**
- `services/go-legacy/go.mod`
- `services/go-legacy/Dockerfile`
- `test-data/telemetry_sample.csv`

---

### Фаза 2: Разработка Go CLI 

**Структура проекта:**
```
services/go-legacy/
├── main.go                 # Entry point
├── config/
│   └── config.go          # Configuration from ENV
├── csv/
│   ├── parser.go          # CSV parsing
│   └── validator.go       # Voltage/temp validation
├── db/
│   ├── connection.go      # PostgreSQL connection pool
│   └── repository.go      # Database operations
├── models/
│   └── telemetry.go       # Telemetry struct
├── go.mod
├── go.sum
├── Dockerfile
└── README.md
```

**Ключевые файлы:**

**1. `main.go`**
```go
package main

import (
    "log"
    "os"
    "time"
    
    "github.com/cassiopeia/go-legacy/config"
    "github.com/cassiopeia/go-legacy/csv"
    "github.com/cassiopeia/go-legacy/db"
)

func main() {
    // Load config from ENV
    cfg := config.Load()
    
    // Connect to PostgreSQL
    database, err := db.NewConnection(cfg.DatabaseURL)
    if err != nil {
        log.Fatal("Database connection failed:", err)
    }
    defer database.Close()
    
    // Parse CSV files
    csvDir := cfg.CSVInputDir
    files, _ := os.ReadDir(csvDir)
    
    for _, file := range files {
        if !strings.HasSuffix(file.Name(), ".csv") {
            continue
        }
        
        log.Printf("Processing: %s", file.Name())
        
        records, err := csv.ParseFile(csvDir + "/" + file.Name())
        if err != nil {
            log.Printf("ERROR parsing %s: %v", file.Name(), err)
            continue
        }
        
        // Insert to database
        inserted := 0
        for _, record := range records {
            if err := db.InsertTelemetry(database, record); err != nil {
                log.Printf("ERROR inserting record: %v", err)
            } else {
                inserted++
            }
        }
        
        log.Printf("✅ Inserted %d records from %s", inserted, file.Name())
    }
    
    // Generate CSV report (same as Pascal)
    if err := csv.GenerateReport(database, cfg.CSVOutputDir); err != nil {
        log.Printf("ERROR generating report: %v", err)
    }
}
```

**2. `csv/parser.go`**
```go
package csv

import (
    "encoding/csv"
    "os"
    "strconv"
    
    "github.com/cassiopeia/go-legacy/models"
)

func ParseFile(filePath string) ([]models.TelemetryRecord, error) {
    file, err := os.Open(filePath)
    if err != nil {
        return nil, err
    }
    defer file.Close()
    
    reader := csv.NewReader(file)
    rows, err := reader.ReadAll()
    if err != nil {
        return nil, err
    }
    
    var records []models.TelemetryRecord
    for i, row := range rows {
        if i == 0 {
            continue // Skip header
        }
        
        voltage, _ := strconv.ParseFloat(row[1], 64)
        temp, _ := strconv.ParseFloat(row[2], 64)
        
        // Validate voltage range (11.5V - 14.5V) like Pascal
        if voltage < 11.5 || voltage > 14.5 {
            log.Printf("⚠️  WARNING: Voltage out of range: %.2fV", voltage)
        }
        
        records = append(records, models.TelemetryRecord{
            Timestamp:   row[0],
            Voltage:     voltage,
            Temperature: temp,
            SourceFile:  row[3],
        })
    }
    
    return records, nil
}
```

**3. `db/repository.go`**
```go
package db

import (
    "database/sql"
    "github.com/cassiopeia/go-legacy/models"
)

func InsertTelemetry(db *sql.DB, record models.TelemetryRecord) error {
    _, err := db.Exec(`
        INSERT INTO telemetry (recorded_at, voltage, temperature, source_file, created_at)
        VALUES ($1, $2, $3, $4, NOW())
    `, record.Timestamp, record.Voltage, record.Temperature, record.SourceFile)
    
    return err
}
```

**4. `Dockerfile`**
```dockerfile
FROM golang:1.21-alpine AS builder

WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o go-legacy main.go

# Final stage (tiny image ~10MB)
FROM alpine:3.19
RUN apk --no-cache add ca-certificates tzdata
WORKDIR /app
COPY --from=builder /app/go-legacy .

ENTRYPOINT ["./go-legacy"]
```

**5. `docker-compose.yml` (добавить сервис)**
```yaml
  go_legacy:
    build:
      context: ./services/go-legacy
      dockerfile: Dockerfile
    container_name: go_legacy
    environment:
      DATABASE_URL: postgres://monouser:monopass@db:5432/monolith
      CSV_INPUT_DIR: /data/csv
      CSV_OUTPUT_DIR: /data/csv/reports
    depends_on:
      - db
    volumes:
      - csvdata:/data/csv
    networks:
      - backend
    restart: unless-stopped
    # Cron: каждые 5 минут
    command: sh -c "while true; do ./go-legacy; sleep 300; done"
```

---

### Фаза 3: Тестирование 

**Unit тесты:**
```go
// csv/parser_test.go
func TestParseFile(t *testing.T) {
    records, err := ParseFile("../test-data/telemetry_sample.csv")
    assert.NoError(t, err)
    assert.Equal(t, 100, len(records))
    
    // Test voltage validation
    assert.GreaterOrEqual(t, records[0].Voltage, 11.5)
    assert.LessOrEqual(t, records[0].Voltage, 14.5)
}
```

**Integration тесты:**
```bash
# Запуск Go legacy с тестовыми данными
docker-compose up -d db go_legacy

# Проверка результатов в БД
docker exec iss_db psql -U monouser -d monolith \
  -c "SELECT COUNT(*) FROM telemetry WHERE source_file='telemetry_sample.csv';"

# Ожидаемый результат: 100 строк
```

**A/B тестирование:**
```yaml
# Запуск Pascal И Go параллельно на 1 неделю
services:
  pascal_legacy:
    # ... существующая конфигурация
    
  go_legacy:
    # ... новая конфигурация
    # Обрабатывает те же CSV, но в другую таблицу telemetry_go
```

**Сравнение результатов:**
```sql
-- Проверка идентичности данных
SELECT 
    COUNT(*) as total_pascal,
    (SELECT COUNT(*) FROM telemetry_go) as total_go,
    COUNT(*) - (SELECT COUNT(*) FROM telemetry_go) as difference
FROM telemetry
WHERE created_at > NOW() - INTERVAL '1 week';

-- Ожидаемый результат: difference = 0
```

---

### Фаза 4: Миграция 


**Rollback план:**
```bash
# Если Go legacy не работает - быстрый откат
docker-compose stop go_legacy
docker-compose start pascal_legacy
# Восстановить данные из backup если нужно
psql monolith < backup_before_migration.sql
```



### Выгоды:
1. **Производительность:** 8x быстрее → экономия CPU/RAM
2. **Поддержка:** Go код легче читать → -30% времени на поддержку
3. **Надёжность:** Статическая типизация → -50% runtime ошибок
4. **Мониторинг:** Интеграция с Prometheus → лучший observability
5. **Найм:** Go разработчиков проще найти чем Pascal



---



### Следующие шаги:

```bash
# 1. Создать Go legacy сервис
mkdir -p services/go-legacy
cd services/go-legacy
go mod init github.com/cassiopeia/go-legacy

# 2. Скопировать шаблон из этого документа
# 3. Запустить: docker-compose build go_legacy
# 4. Тестировать: docker-compose up -d go_legacy



