#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_PATH:-/var/www/trade-forge}"
if [ -z "$APP_DIR" ]; then
  APP_DIR="/var/www/trade-forge"
fi
COMPOSE_FILE="docker-compose.prod.yml"

cd "$APP_DIR"

git fetch origin main
git reset --hard origin/main

docker compose -f "$COMPOSE_FILE" build
docker compose -f "$COMPOSE_FILE" up -d

docker compose -f "$COMPOSE_FILE" exec -T php php artisan migrate --force
docker compose -f "$COMPOSE_FILE" exec -T php php artisan config:cache
docker compose -f "$COMPOSE_FILE" exec -T php php artisan route:cache
docker compose -f "$COMPOSE_FILE" exec -T php php artisan view:cache

docker compose -f "$COMPOSE_FILE" restart queue scheduler reverb

echo "Deploy finished successfully."