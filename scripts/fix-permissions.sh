#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${1:-docker-compose.prod.yml}"

APP_UID="$(grep '^UID=' .env | cut -d= -f2 | tr -d ' \"' || true)"
APP_GID="$(grep '^GID=' .env | cut -d= -f2 | tr -d ' \"' || true)"
APP_UID="${APP_UID:-1000}"
APP_GID="${APP_GID:-1000}"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

fix_inside_container() {
  docker compose -f "$COMPOSE_FILE" exec -T -u root php \
    chown -R "${APP_UID}:${APP_GID}" /var/www/html/storage /var/www/html/bootstrap/cache
  docker compose -f "$COMPOSE_FILE" exec -T -u root php \
    chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache
}

if docker compose -f "$COMPOSE_FILE" ps --status running -q php >/dev/null 2>&1; then
  fix_inside_container
elif docker image inspect trade-forge-php:prod >/dev/null 2>&1; then
  docker compose -f "$COMPOSE_FILE" run --rm --user root --no-deps php \
    sh -c "chown -R ${APP_UID}:${APP_GID} storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache"
else
  chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true
fi