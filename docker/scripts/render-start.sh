#!/usr/bin/env bash
#
# Container entrypoint for the Render deployment.
#
# The point of this script is that the API process starts even when the
# database does not. The previous CMD chained every boot step with `&&`:
#
#   php artisan config:clear && php artisan migrate --force && \
#   php artisan db:seed ... && php artisan serve ...
#
# so a database that was unreachable for any reason took the whole container
# down with exit 1, and Render restarted it into the same failure — a crash
# loop that turned a database incident into total unavailability. The API can
# serve cached routes, static content and a truthful /api/health while the
# database is away; it should not die alongside it.
#
# Deliberately NOT `set -e`: each step decides for itself whether it is fatal.
# Only the final exec is allowed to end this script.
set -uo pipefail

log() { printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"; }

APP_PORT="${PORT:-8000}"
DB_WAIT_ATTEMPTS="${DB_WAIT_ATTEMPTS:-10}"
DB_WAIT_DELAY="${DB_WAIT_DELAY:-3}"

# ---------------------------------------------------------------------------
# Database reachability
#
# Uses PDO rather than `nc`, which is not installed in the php:8.2-fpm image
# (the older docker/scripts/startup.sh assumed it was). A real connection also
# catches the failure mode a port check misses: the TCP port answering while
# the MySQL handshake never completes, which is what a stopped managed database
# behind a still-live proxy looks like —
#   "PDO::__construct(): Error while reading greeting packet"
# ---------------------------------------------------------------------------
db_ready() {
  php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: "3306";
    $name = getenv("DB_DATABASE") ?: "";
    $user = getenv("DB_USERNAME") ?: "";
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
      new PDO(
        "mysql:host={$host};port={$port};dbname={$name}",
        $user,
        $pass,
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
      );
      exit(0);
    } catch (Throwable $e) {
      fwrite(STDERR, "  " . $e->getMessage() . PHP_EOL);
      exit(1);
    }
  ' 2>&1
}

wait_for_db() {
  local attempt=1
  while [ "${attempt}" -le "${DB_WAIT_ATTEMPTS}" ]; do
    if db_ready; then
      log "✅ Database reachable (attempt ${attempt}/${DB_WAIT_ATTEMPTS})"
      return 0
    fi
    log "⏳ Database not reachable yet (attempt ${attempt}/${DB_WAIT_ATTEMPTS})"
    attempt=$((attempt + 1))
    [ "${attempt}" -le "${DB_WAIT_ATTEMPTS}" ] && sleep "${DB_WAIT_DELAY}"
  done
  return 1
}

# ---------------------------------------------------------------------------
# Boot
# ---------------------------------------------------------------------------
log "🚀 Starting SchoolSaavy API on port ${APP_PORT}"

# Safe without a database: reads config files only.
php artisan config:clear || log "⚠️  config:clear failed; continuing"

if wait_for_db; then
  log "🗄️  Running migrations"
  if php artisan migrate --force; then
    log "✅ Migrations applied"
  else
    log "⚠️  Migrations failed — starting anyway so the API stays reachable."
    log "    Check /api/health and the migration output above."
  fi

  # Seeding every boot is wasteful and risks clobbering live rows, so it is
  # now opt-in. Set RUN_SEEDERS=true for a first deploy into an empty database.
  if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    log "🌱 RUN_SEEDERS=true — seeding"
    php artisan db:seed --class=SuperAdminSeeder --force || log "⚠️  SuperAdminSeeder failed"
    php artisan db:seed --class=SchoolSeeder --force   || log "⚠️  SchoolSeeder failed"
  else
    log "🌱 Skipping seeders (set RUN_SEEDERS=true to run them)"
  fi
else
  log "⚠️  Database unreachable after ${DB_WAIT_ATTEMPTS} attempts."
  log "    Starting the API anyway — database-backed routes will error, but the"
  log "    process stays up instead of crash-looping. /api/health reports status."
fi

log "🏁 Serving"
exec php artisan serve --host=0.0.0.0 --port="${APP_PORT}"
