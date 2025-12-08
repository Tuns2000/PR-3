#!/bin/bash
set -e

echo "Starting PHP-FPM entrypoint..."

# Ожидание PostgreSQL
echo "Waiting for PostgreSQL..."
until PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "PostgreSQL is unavailable - sleeping (Host: $DB_HOST, User: $DB_USERNAME, DB: $DB_DATABASE)"
  sleep 2
done
echo "PostgreSQL is up!"

# Создание Laravel проекта если его нет
if [ ! -f "artisan" ]; then
    echo "Installing Laravel..."
    composer create-project --prefer-dist laravel/laravel:^11.0 .
    composer require guzzlehttp/guzzle predis/predis
fi

# Применение патчей из laravel-patches
if [ -d "/opt/laravel-patches" ]; then
    echo "Applying Laravel patches..."
    rsync -av --exclude='*.md' /opt/laravel-patches/ /var/www/html/
fi

# Настройка прав доступа
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Миграции БД
if [ -f "artisan" ]; then
    echo "Running migrations..."
    php artisan migrate --force || echo "Migration failed (may be already applied)"
fi

echo "Starting PHP-FPM..."
exec php-fpm
