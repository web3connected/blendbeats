#!/usr/bin/env sh
set -eu

REMOTE_HOST="${DEPLOY_REMOTE_HOST:-bledbeats}"
REMOTE_PATH="${DEPLOY_REMOTE_PATH:-/home/forge/theblenbattlegroundsusa.com}"
APP_NAME="${DEPLOY_PM2_APP:-bledbeats}"
APP_PORT="${DEPLOY_PORT:-3000}"
APP_HOST="${DEPLOY_HOST:-0.0.0.0}"
SITE_URL="${DEPLOY_SITE_URL:-https://theblenbattlegroundsusa.com}"

if ! command -v ssh >/dev/null 2>&1; then
  echo "deploy: ssh is not installed or not on PATH." >&2
  exit 1
fi

echo "deploy: updating server from GitHub and restarting ${APP_NAME}"

ssh "${REMOTE_HOST}" "
  set -eu

  cd '${REMOTE_PATH}'

  git pull origin main

  npm ci
  npm run build

  if pm2 describe '${APP_NAME}' >/dev/null 2>&1; then
    PORT='${APP_PORT}' HOST='${APP_HOST}' SITE_URL='${SITE_URL}' pm2 restart '${APP_NAME}' --update-env
  else
    PORT='${APP_PORT}' HOST='${APP_HOST}' SITE_URL='${SITE_URL}' pm2 start dist/server.bundle.mjs --name '${APP_NAME}' --time --update-env
  fi

  pm2 save
"

echo "deploy: complete"