#!/bin/sh
set -e

echo "[EIDOLON PATHWAY] Container starting up..."

# Prepare persistent data directory
DATA_DIR="${PATHWAY_DATA_DIR:-/app/var}"
mkdir -p "$DATA_DIR"
chmod 0700 "$DATA_DIR" 2>/dev/null || true

# Initialize database schema v8 idempotently if needed
if [ -f "bin/init.php" ]; then
    echo "[EIDOLON PATHWAY] Checking SQLite schema initialization..."
    php bin/init.php || echo "[EIDOLON PATHWAY] Database initialization check finished: $?"
fi

# Ensure correct permissions on SQLite database file if present
DB_PATH="${PATHWAY_DATABASE_PATH:-$DATA_DIR/pathway.sqlite}"
if [ -f "$DB_PATH" ]; then
    chmod 0600 "$DB_PATH" 2>/dev/null || true
fi

echo "[EIDOLON PATHWAY] Storage verified. Launching web service on port ${PORT:-8080}..."

if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp run --config /app/Caddyfile --adapter caddyfile
elif command -v caddy >/dev/null 2>&1; then
    exec caddy run --config /app/Caddyfile --adapter caddyfile
else
    echo "[EIDOLON PATHWAY] Fallback: using PHP built-in server on 0.0.0.0:${PORT:-8080}"
    exec php -S "0.0.0.0:${PORT:-8080}" -t /app/public /app/app/router.php
fi
