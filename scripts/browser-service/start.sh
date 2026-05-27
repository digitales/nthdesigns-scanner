#!/usr/bin/env sh
set -e

cd /app

echo "[browser-service] starting as $(id -u):$(id -g) PORT=${PORT:-8080}"
echo "[browser-service] node $(node --version 2>&1)"

if [ ! -f scripts/browser-service/server.mjs ]; then
    echo "[browser-service] ERROR: server.mjs not found" >&2
    ls -la scripts/browser-service 2>&1 || true
    exit 1
fi

exec node scripts/browser-service/server.mjs
