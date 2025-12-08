#!/usr/bin/env bash
set -e

echo "[pascal-legacy] Starting legacy service..."

if [ -z "$PGHOST" ] || [ -z "$PGUSER" ] || [ -z "$PGDATABASE" ]; then
  echo "[pascal-legacy] ERROR: Missing PostgreSQL environment variables"
  exit 1
fi

echo "[pascal-legacy] CSV output directory: $CSV_OUT_DIR"
echo "[pascal-legacy] Generation period: $GEN_PERIOD_SEC seconds"

mkdir -p "$CSV_OUT_DIR"

echo "[pascal-legacy] Initial generation..."
/usr/local/bin/legacy || echo "[pascal-legacy] Initial generation failed"

while true; do
  echo "[pascal-legacy] Sleeping for $GEN_PERIOD_SEC seconds..."
  sleep "$GEN_PERIOD_SEC"
  
  echo "[pascal-legacy] Generating telemetry data..."
  /usr/local/bin/legacy || {
    echo "[pascal-legacy] ERROR: Legacy program failed"
  }
done