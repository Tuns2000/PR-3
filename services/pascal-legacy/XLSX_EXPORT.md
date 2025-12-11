# XLSX Export для Pascal Legacy Telemetry

## Обзор

Автоматическая конвертация CSV файлов телеметрии в формат Excel (XLSX) с сохранением типов данных.

## Компоненты

### 1. Python скрипт `csv_to_xlsx.py`
- **Расположение**: `/usr/local/bin/csv_to_xlsx.py` в контейнере
- **Запуск**: Автоматически после каждой генерации CSV
- **Библиотеки**: pandas, openpyxl

### 2. Функциональность

#### Типизация данных:
- **Timestamp**: ISO 8601 формат (YYYY-MM-DD HH:MM:SS)
- **UnixTimestamp**: Целое число
- **Voltage**: Число с 2 знаками (12.74)
- **Temperature**: Число с 2 знаками (21.36)
- **IsActive**: Boolean (TRUE/FALSE)
- **SourceFile**: Текст в кавычках

#### Форматирование Excel:
- **Заголовки**: Синий фон (#4472C4), белый текст, жирный шрифт
- **Ячейки времени**: Формат `yyyy-mm-dd hh:mm:ss`
- **Числа**: Формат `0.00` для voltage и temperature
- **Boolean**: Центрированный текст TRUE/FALSE
- **Авто-ширина столбцов**: До 50 символов максимум

### 3. Логика работы

```bash
# Цикл в entrypoint.sh
1. Генерация CSV через Pascal Legacy
2. Конвертация CSV → XLSX
3. Ожидание 300 секунд (5 минут)
```

### 4. Файлы

**Входные CSV**:
```csv
Timestamp,UnixTimestamp,Voltage,Temperature,IsActive,SourceFile
2025-12-11T21:20:00Z,1765488000,12.706,21.06,TRUE,"pascal-legacy"
```

**Выходные XLSX**:
- `telemetry_20251209.xlsx`
- `telemetry_20251210.xlsx`
- `telemetry_20251211.xlsx`

## Использование

### Ручная конвертация
```bash
docker compose exec pascal_legacy /usr/local/bin/csv_to_xlsx.py
```

### Копирование XLSX на хост
```bash
docker compose cp pascal_legacy:/data/csv/telemetry_20251211.xlsx ./telemetry.xlsx
```

### Просмотр логов
```bash
docker compose logs --tail 50 pascal_legacy | grep -i xlsx
```

## Технические детали

### Dockerfile изменения:
```dockerfile
# Python + библиотеки
RUN apt-get install -y python3 python3-pip
RUN pip3 install --no-cache-dir pandas openpyxl --break-system-packages

# Копирование скрипта через builder stage
COPY --from=builder /app/csv_to_xlsx.py /usr/local/bin/csv_to_xlsx.py
RUN chmod +x /usr/local/bin/csv_to_xlsx.py
```

### Entrypoint.sh:
```bash
# После каждой генерации
/usr/local/bin/legacy
/usr/local/bin/csv_to_xlsx.py || echo "XLSX conversion failed"
```

## Обработка ошибок

- **Отсутствие CSV**: `[WARN] No CSV files found`
- **Ошибка парсинга**: `[ERROR] Failed to convert X: Error tokenizing data`
- **Успешная конвертация**: `[INFO] Converted: X.csv -> X.xlsx`
- **Итог**: `[INFO] Conversion complete: N file(s) processed`

## Преимущества

✅ Автоматическая конвертация каждые 5 минут
✅ Сохранение типов данных (не всё строки)
✅ Профессиональное форматирование Excel
✅ Обратная совместимость (CSV остаются)
✅ Инкрементальная конвертация (только новые файлы)

## Размеры файлов

- CSV: ~100-1000 байт (текст)
- XLSX: ~5-6 КБ (бинарный с метаданными)

## Зависимости

- Python 3.11+
- pandas 2.x
- openpyxl 3.x

## Статус

✅ **Реализовано и работает** (11 декабря 2024)
