#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_PATH:-/home/deploy/trade-forge}"
COMPOSE_FILE="docker-compose.prod.yml"
ARCHIVE_PATH="${1:-/tmp/release.tar.gz}"

cd "$APP_DIR"

if [ -f "$ARCHIVE_PATH" ]; then
  tar -xzf "$ARCHIVE_PATH" -C "$APP_DIR"
  rm -f "$ARCHIVE_PATH"
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

docker compose -f "$COMPOSE_FILE" up -d

bash scripts/fix-permissions.sh "$COMPOSE_FILE"

for i in $(seq 1 30); do
  if docker compose -f "$COMPOSE_FILE" exec -T mysql mysqladmin ping -h localhost --silent; then
    break
  fi
  sleep 2
done

docker compose -f "$COMPOSE_FILE" exec -T php php artisan config:clear
docker compose -f "$COMPOSE_FILE" exec -T php php artisan migrate --force
docker compose -f "$COMPOSE_FILE" exec -T php php artisan optimize
docker compose -f "$COMPOSE_FILE" restart php queue scheduler reverb

echo "Deploy finished successfully."