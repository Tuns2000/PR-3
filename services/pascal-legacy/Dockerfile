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

# Копирование исходного кода
WORKDIR /app
COPY legacy.pas .

# Компиляция
RUN fpc -Fl/usr/lib/x86_64-linux-gnu \
        -o/app/legacy \
        legacy.pas

# ============================================
# Stage 2: Runtime (минимальный образ)
# ============================================
FROM debian:bookworm-slim

# Установка только runtime библиотек
RUN apt-get update && apt-get install -y \
    libpq5 \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Копирование скомпилированного бинарника
COPY --from=builder /app/legacy /usr/local/bin/legacy
RUN chmod +x /usr/local/bin/legacy

# Создание директории для CSV
RUN mkdir -p /data/csv

# Переменные окружения по умолчанию
ENV CSV_OUT_DIR=/data/csv \
    GEN_PERIOD_SEC=300

WORKDIR /data

# Entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
