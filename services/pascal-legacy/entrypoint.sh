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

echo "[pascal-legacy] Initial XLSX conversion..."
/usr/local/bin/csv_to_xlsx.py || echo "[pascal-legacy] Initial XLSX conversion failed"

while true; do
  echo "[pascal-legacy] Sleeping for $GEN_PERIOD_SEC seconds..."
  sleep "$GEN_PERIOD_SEC"
  
  echo "[pascal-legacy] Generating telemetry data..."
  /usr/local/bin/legacy || {
    echo "[pascal-legacy] ERROR: Legacy program failed"
  }
  
  echo "[pascal-legacy] Converting CSV to XLSX..."
  /usr/local/bin/csv_to_xlsx.py || {
    echo "[pascal-legacy] WARNING: XLSX conversion failed"
  }
done