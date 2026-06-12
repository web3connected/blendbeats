#!/usr/bin/env sh
set -eu

SSH_HOST="${DEPLOY_SSH_HOST:-bledbeats}"
APP_ROOT="${DEPLOY_APP_ROOT:-/home/forge/theblenbattlegroundsusa.com/backend}"
REPO_URL="${DEPLOY_REPO_URL:-https://github.com/web3connected/blendbeats.git}"
BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${DEPLOY_PHP_BIN:-php}"
STAMP="$(date +%Y%m%d%H%M%S)"

echo "deploy: connecting to ${SSH_HOST}"
echo "deploy: reinstalling ${APP_ROOT} from ${REPO_URL}#${BRANCH}"

ssh "$SSH_HOST" "APP_ROOT='$APP_ROOT' REPO_URL='$REPO_URL' BRANCH='$BRANCH' PHP_BIN='$PHP_BIN' STAMP='$STAMP' bash -se" <<'REMOTE'
set -euo pipefail

PARENT_DIR="$(dirname "$APP_ROOT")"
KEEP_DIR="$PARENT_DIR/.blendbeats-deploy-$STAMP"
APP_NAME="$(basename "$APP_ROOT")"

echo "deploy: preparing keep directory $KEEP_DIR"
mkdir -p "$KEEP_DIR"

if [ -d "$APP_ROOT" ]; then
  echo "deploy: preserving .env, storage, and media"
  [ -f "$APP_ROOT/.env" ] && cp "$APP_ROOT/.env" "$KEEP_DIR/.env"
  [ -d "$APP_ROOT/storage" ] && cp -a "$APP_ROOT/storage" "$KEEP_DIR/storage"
  if [ -d "$APP_ROOT/public/media" ]; then
    mkdir -p "$KEEP_DIR/public"
    cp -a "$APP_ROOT/public/media" "$KEEP_DIR/public/media"
  fi

  echo "deploy: moving old app to backup"
  rm -rf "$PARENT_DIR/${APP_NAME}.previous"
  mv "$APP_ROOT" "$PARENT_DIR/${APP_NAME}.previous"
fi

echo "deploy: cloning fresh app"
git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$APP_ROOT"
cd "$APP_ROOT"

if [ -f "$KEEP_DIR/.env" ]; then
  cp "$KEEP_DIR/.env" .env
else
  cp .env.example .env
  "$PHP_BIN" artisan key:generate --force
fi

if [ -d "$KEEP_DIR/storage" ]; then
  rm -rf storage
  cp -a "$KEEP_DIR/storage" storage
fi

if [ -d "$KEEP_DIR/public/media" ]; then
  mkdir -p public
  rm -rf public/media
  cp -a "$KEEP_DIR/public/media" public/media
fi

echo "deploy: installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "deploy: installing frontend dependencies"
npm ci --no-audit --no-fund

echo "deploy: building frontend"
npm run build

echo "deploy: clearing caches before migration"
"$PHP_BIN" artisan optimize:clear

mkdir -p storage/framework
cat > storage/framework/deploy-baseline-migrations.php <<'PHP'
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$baselines = [
    '2026_06_10_000000_create_admins_table' => ['admins', 'admin_password_reset_tokens'],
    '2026_06_11_123659_create_permission_tables' => ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'],
    '2026_06_11_140000_create_media_library_tables' => ['media_accounts', 'media_files', 'media_manager_audit_logs', 'user_feature_activations'],
    '2026_06_12_010000_create_dj_profile_tables' => ['dj_profiles', 'dj_genres', 'dj_profile_genres', 'dj_social_links', 'dj_booking_settings', 'dj_media'],
    '2026_06_12_020000_create_dj_hub_tables' => ['dj_featured_status', 'followers'],
    '2026_06_12_030000_create_dj_lounge_post_tables' => ['dj_lounge_posts', 'dj_lounge_comments', 'dj_lounge_reactions', 'dj_lounge_reposts', 'dj_lounge_bookmarks', 'dj_lounge_reports'],
];

$batch = (int) (DB::table('migrations')->max('batch') ?? 0);

foreach ($baselines as $migration => $tables) {
    $alreadyRecorded = DB::table('migrations')->where('migration', $migration)->exists();

    if ($alreadyRecorded) {
        continue;
    }

    $schemaExists = collect($tables)->every(fn (string $table): bool => Schema::hasTable($table));

    if (! $schemaExists) {
        continue;
    }

    DB::table('migrations')->insert([
        'migration' => $migration,
        'batch' => $batch,
    ]);

    echo "baselined {$migration}\n";
}
PHP

echo "deploy: baselining existing dev schema migrations"
"$PHP_BIN" artisan tinker --execute="$(cat storage/framework/deploy-baseline-migrations.php)"
rm -f storage/framework/deploy-baseline-migrations.php

echo "deploy: running database migrations"
"$PHP_BIN" artisan migrate --force --no-interaction

echo "deploy: linking storage"
"$PHP_BIN" artisan storage:link || true

echo "deploy: caching production app"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan queue:restart || true

echo "deploy: complete"
REMOTE
