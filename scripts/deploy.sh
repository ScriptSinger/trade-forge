#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_PATH:-/home/deploy/trade-forge}"
if [ -z "$APP_DIR" ]; then
  APP_DIR="/home/deploy/trade-forge"
fi

COMPOSE_FILE="docker-compose.prod.yml"
ARCHIVE_PATH="${1:-}"

cd "$APP_DIR"

if [ -n "$ARCHIVE_PATH" ]; then
  tar -xzf "$ARCHIVE_PATH" -C "$APP_DIR"
else
  git fetch origin main
  git reset --hard origin/main
fi

docker compose -f "$COMPOSE_FILE" down
docker compose -f "$COMPOSE_FILE" up -d --build

for i in $(seq 1 30); do
  if docker compose -f "$COMPOSE_FILE" exec -T mysql mysqladmin ping -h localhost --silent; then
    break
  fi
  sleep 2
done

docker compose -f "$COMPOSE_FILE" exec -T php php artisan migrate --force
docker compose -f "$COMPOSE_FILE" exec -T php php artisan config:cache
docker compose -f "$COMPOSE_FILE" exec -T php php artisan route:cache
docker compose -f "$COMPOSE_FILE" exec -T php php artisan view:cache
docker compose -f "$COMPOSE_FILE" restart queue scheduler reverb

echo "Deploy finished successfully."