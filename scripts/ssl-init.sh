#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_PATH:-/home/deploy/trade-forge}"
COMPOSE_FILE="docker-compose.prod.yml"
SERVER_IP="136.243.214.62"
CERT_EMAIL="${CERTBOT_EMAIL:?Set CERTBOT_EMAIL}"

cd "$APP_DIR"

docker compose -f "$COMPOSE_FILE" up -d nginx

docker compose -f "$COMPOSE_FILE" run --rm certbot certonly \
  --webroot \
  -w /var/www/certbot \
  -d "$SERVER_IP" \
  --email "$CERT_EMAIL" \
  --agree-tos \
  --no-eff-email \
  --certificate-profile shortlived \
  --ip-address "$SERVER_IP"

if ! grep -q '^NGINX_CONF=prod.conf' .env 2>/dev/null; then
  echo 'NGINX_CONF=prod.conf' >> .env
fi

docker compose -f "$COMPOSE_FILE" up -d nginx

echo "SSL certificate issued. HTTPS is enabled via NGINX_CONF=prod.conf"