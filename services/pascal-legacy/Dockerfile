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

# ============================================
# Stage 1: Builder (компиляция Rust)
# ============================================
FROM rust:slim AS build

# Установка зависимостей
RUN apt-get update && apt-get install -y --no-install-recommends \
    pkg-config \
    libssl-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Копируем Cargo.toml для кэширования зависимостей
COPY Cargo.toml ./

# Создаём dummy main.rs для предварительной загрузки зависимостей
RUN mkdir -p src && \
    printf 'fn main() {}' > src/main.rs && \
    cargo fetch

# Копируем исходники
COPY src ./src

# Устанавливаем DATABASE_URL для SQLx макросов (offline mode)
ENV SQLX_OFFLINE=true

# Компилируем в release режиме
RUN cargo build --release

# ============================================
# Stage 2: Runtime (минимальный образ)
# ============================================
FROM debian:12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Копируем бинарник из builder
COPY --from=build /app/target/release/rust-iss /app/rust-iss

# Порт
EXPOSE 3000

# Запуск
CMD ["/app/rust-iss"]
