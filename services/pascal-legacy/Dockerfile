# ============================================
# Stage 1: Builder (компиляция Pascal)
# ============================================
FROM debian:bookworm-slim AS builder

# Установка Free Pascal Compiler
RUN apt-get update && apt-get install -y \
    fpc \
    fp-compiler \
    fp-units-base \
    fp-units-fcl \
    fp-units-db \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Копирование исходного кода и скриптов
WORKDIR /app
COPY legacy.pas .
COPY csv_to_xlsx.py .

# Компиляция
RUN fpc -Fl/usr/lib/x86_64-linux-gnu \
        -o/app/legacy \
        legacy.pas

# ============================================
# Stage 2: Runtime (минимальный образ)
# ============================================
FROM debian:bookworm-slim

# Установка только runtime библиотек + Python для XLSX
RUN apt-get update && apt-get install -y \
    libpq5 \
    ca-certificates \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Установка Python библиотек для XLSX
RUN pip3 install --no-cache-dir pandas openpyxl --break-system-packages

# Копирование скомпилированного бинарника и скриптов
COPY --from=builder /app/legacy /usr/local/bin/legacy
COPY --from=builder /app/csv_to_xlsx.py /usr/local/bin/csv_to_xlsx.py
RUN chmod +x /usr/local/bin/legacy /usr/local/bin/csv_to_xlsx.py

# Создание директории для CSV/XLSX
RUN mkdir -p /data/csv

# Переменные окружения по умолчанию
ENV CSV_OUT_DIR=/data/csv \
    GEN_PERIOD_SEC=300

WORKDIR /data

# Entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
