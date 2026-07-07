#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${DEPLOY_PATH:-/home/deploy/trade-forge}"
REPO_URL="${REPO_URL:-git@github.com:ScriptSinger/trade-forge.git}"

if ! command -v docker >/dev/null; then
  curl -fsSL https://get.docker.com | sh
fi

if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose plugin is required." >&2
  exit 1
fi

mkdir -p "$APP_DIR"
cd "$APP_DIR"

if [ ! -d .git ]; then
  git clone "$REPO_URL" .
fi

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example — edit it before starting production services."
fi

chmod +x scripts/deploy.sh scripts/ssl-init.sh

ufw allow OpenSSH || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true

echo "VPS bootstrap complete."
echo "Next steps:"
echo "  1. Edit $APP_DIR/.env for production"
echo "  2. docker compose -f docker-compose.prod.yml up -d"
echo "  3. docker compose -f docker-compose.prod.yml exec php php artisan key:generate"
echo "  4. docker compose -f docker-compose.prod.yml exec php php artisan migrate --force"
echo "  5. CERTBOT_EMAIL=you@example.com ./scripts/ssl-init.sh"