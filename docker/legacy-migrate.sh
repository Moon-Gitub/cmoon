#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html

log_sql() {
  php -r '
    $msg = file_get_contents("php://stdin");
    try {
      $pdo = new PDO(
        "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
      );
      $pdo->exec("CREATE TABLE IF NOT EXISTS _mig_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        body LONGTEXT NOT NULL
      ) ENGINE=InnoDB");
      $st = $pdo->prepare("INSERT INTO _mig_log (body) VALUES (?)");
      $st->execute([substr($msg, 0, 60000)]);
      echo "logged_to__mig_log\n";
    } catch (Throwable $e) {
      fwrite(STDERR, "log_sql fail: ".$e->getMessage()."\n");
    }
  ' <<< "$1"
}

if [ "${LEGACY_AUTO_MIGRATE:-false}" != "true" ]; then
  echo "LEGACY_AUTO_MIGRATE!=true → skip migrator"
  exit 0
fi

if [ "${LEGACY_FORCE:-false}" = "true" ]; then
  rm -f storage/app/legacy-migrate.done
  echo "LEGACY_FORCE=true → re-run"
fi

if [ -f storage/app/legacy-migrate.done ]; then
  echo "legacy-migrate.done existe → skip"
  exit 0
fi

export LEGACY_DB_PASSWORD="${LEGACY_DB_PASSWORD:-${DB_ROOT_PASSWORD:-}}"
export LEGACY_IMPORT_ENABLED=true
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php 2>/dev/null || true

OUT=/tmp/mig-out.txt
: > "$OUT"

{
  echo "==> env check"
  echo "LEGACY_DB_DATABASE=${LEGACY_DB_DATABASE:-}"
  echo "LEGACY_IMPORT_ONLY=${LEGACY_IMPORT_ONLY:-}"
} | tee -a "$OUT"

php artisan migrate --force >>"$OUT" 2>&1 || true

echo "==> legacy:load-dump" | tee -a "$OUT"
set +e
php artisan legacy:load-dump --database="${LEGACY_DB_DATABASE:-jamrod_legacy}" >>"$OUT" 2>&1
rc=$?
set -e
if [ "$rc" -ne 0 ]; then
  log_sql "$(cat "$OUT")"
  echo "==> load-dump FAILED rc=$rc"
  exit "$rc"
fi

ONLY="${LEGACY_IMPORT_ONLY:-}"
echo "==> legacy:import --create-empresa ${ONLY:+--only=$ONLY}" | tee -a "$OUT"
IMPORT_ARGS=(legacy:import --create-empresa)
if [ -n "$ONLY" ]; then
  IMPORT_ARGS+=(--only="$ONLY")
fi
if [ "${LEGACY_RESET_MAPS:-false}" = "true" ]; then
  IMPORT_ARGS+=(--reset-maps)
  echo "(LEGACY_RESET_MAPS → --reset-maps)" | tee -a "$OUT"
fi
set +e
php artisan "${IMPORT_ARGS[@]}" >>"$OUT" 2>&1
rc=$?
set -e

log_sql "$(cat "$OUT")"

if [ "$rc" -eq 0 ]; then
  echo "==> migración legacy finalizada" | tee -a "$OUT"
  date -u +"completed %Y-%m-%dT%H:%M:%SZ" > storage/app/legacy-migrate.done
  echo "==> done marker written"
else
  echo "==> migrator FAILED rc=$rc (ver cmoon._mig_log)"
  exit "$rc"
fi
