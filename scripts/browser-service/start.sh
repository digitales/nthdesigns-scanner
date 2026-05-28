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

if [ -z "${CHROME_PATH:-}" ]; then
    CHROME_PATH="$(find /ms-playwright -name chrome -type f 2>/dev/null | head -1)"
    if [ -n "$CHROME_PATH" ]; then
        export CHROME_PATH
        echo "[browser-service] CHROME_PATH=$CHROME_PATH"
    fi
fi

exec node scripts/browser-service/server.mjs
