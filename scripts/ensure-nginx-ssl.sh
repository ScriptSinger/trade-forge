#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
SERVER_IP="${SERVER_IP:-136.243.214.62}"
ENV_FILE="${ENV_FILE:-.env}"

if [ ! -f "$ENV_FILE" ]; then
  echo "Missing ${ENV_FILE}"
  exit 1
fi

if docker compose -f "$COMPOSE_FILE" run --rm --no-deps --entrypoint sh certbot \
  -c "test -f /etc/letsencrypt/live/${SERVER_IP}/fullchain.pem" 2>/dev/null; then
  if grep -q '^NGINX_CONF=' "$ENV_FILE"; then
    sed -i "s/^NGINX_CONF=.*/NGINX_CONF=prod.conf/" "$ENV_FILE"
  else
    echo 'NGINX_CONF=prod.conf' >> "$ENV_FILE"
  fi

  echo "HTTPS: NGINX_CONF=prod.conf (certificate found)."
else
  echo "HTTPS: certificate not found, keeping NGINX_CONF from ${ENV_FILE}."
fi