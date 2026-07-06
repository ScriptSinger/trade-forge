#!/usr/bin/env bash
set -euo pipefail

: "${REDIS_HOST:=redis}"
: "${REDIS_PORT:=6379}"
export REDIS_HOST REDIS_PORT
MAX_ATTEMPTS="${REVERB_REDIS_WAIT_ATTEMPTS:-60}"
SLEEP_SECONDS="${REVERB_REDIS_WAIT_SLEEP:-1}"

echo "Waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}..."

attempt=1
until php -r "
    \$host = getenv('REDIS_HOST') ?: 'redis';
    \$port = (int) (getenv('REDIS_PORT') ?: 6379);
    \$redis = new Redis();
    \$redis->connect(\$host, \$port, 2.0);
    \$redis->ping();
"; do
    if (( attempt >= MAX_ATTEMPTS )); then
        echo "Redis is not reachable after ${MAX_ATTEMPTS} attempts." >&2
        exit 1
    fi

    echo "Redis not ready (attempt ${attempt}/${MAX_ATTEMPTS}), retrying in ${SLEEP_SECONDS}s..."
    attempt=$((attempt + 1))
    sleep "${SLEEP_SECONDS}"
done

echo "Redis is ready. Starting Reverb..."
exec php artisan reverb:start --host="0.0.0.0" --port="8080"