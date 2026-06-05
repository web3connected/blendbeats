#!/usr/bin/env sh
set -eu

REMOTE_HOST="${DEPLOY_REMOTE_HOST:-bledbeats}"
REMOTE_PATH="${DEPLOY_REMOTE_PATH:-/home/forge/theblenbattlegroundsusa.com}"
APP_NAME="${DEPLOY_PM2_APP:-bledbeats}"
APP_PORT="${DEPLOY_PORT:-3000}"
APP_HOST="${DEPLOY_HOST:-0.0.0.0}"
SITE_URL="${DEPLOY_SITE_URL:-https://theblenbattlegroundsusa.com}"

if ! command -v rsync >/dev/null 2>&1; then
  echo "deploy: rsync is not installed or not on PATH." >&2
  echo "deploy: install rsync locally, then retry the push." >&2
  exit 1
fi

if ! command -v ssh >/dev/null 2>&1; then
  echo "deploy: ssh is not installed or not on PATH." >&2
  exit 1
fi

echo "deploy: syncing source to ${REMOTE_HOST}:${REMOTE_PATH}"

rsync \
  --archive \
  --compress \
  --delete \
  --human-readable \
  --itemize-changes \
  --exclude ".git/" \
  --exclude ".githooks/" \
  --exclude "node_modules/" \
  --exclude "dist/" \
  --exclude ".vite/" \
  --exclude "coverage/" \
  --exclude ".env" \
  --exclude ".env.*" \
  --exclude "vendor/" \
  --exclude "backend/node_modules/" \
  --exclude "backend/public/build/" \
  --exclude "backend/public/hot" \
  --exclude "backend/public/media/" \
  --exclude "backend/public/storage" \
  --exclude "backend/storage/" \
  --exclude "_docs/*.log" \
  ./ "${REMOTE_HOST}:${REMOTE_PATH}/"

echo "deploy: installing frontend, backend, building, migrating, and restarting ${APP_NAME}"

ssh "${REMOTE_HOST}" "
  set -eu
  cd '${REMOTE_PATH}'

  npm ci
  npm run build

  if [ -d backend ]; then
    cd backend
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    php artisan adminlte:install --only=assets --force
    php artisan migrate --force
    php artisan db:seed --class=AdminRoleSeeder --force
    php artisan storage:link || true
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    cd ..
  fi

  if pm2 describe '${APP_NAME}' >/dev/null 2>&1; then
    PORT='${APP_PORT}' HOST='${APP_HOST}' SITE_URL='${SITE_URL}' pm2 restart '${APP_NAME}' --update-env
  else
    PORT='${APP_PORT}' HOST='${APP_HOST}' SITE_URL='${SITE_URL}' pm2 start dist/server.bundle.mjs --name '${APP_NAME}' --time --update-env
  fi
  pm2 save
"

echo "deploy: complete"
