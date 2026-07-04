#!/usr/bin/env bash
# Nightly database backup for the BEER Lab ORSEE self-hosted box.
#
# Dumps the MySQL database to a timestamped, gzipped file and prunes old
# backups. The subject pool (participants, consent, session history) is
# irreplaceable PII, so run this every night AND copy the output off the box.
#
# Two modes (auto-detected, override with BACKUP_VIA_DOCKER=1|0):
#   - docker  : dumps from inside the compose `db` service (default when a
#               docker-compose file is present and docker is installed).
#   - direct  : runs mysqldump against a reachable MySQL using env vars
#               (for non-docker / host installs).
#
# Configuration (env or .env, all optional with sensible defaults):
#   MYSQL_DATABASE / ORSEE_DB_NAME   database name        (default: orsee)
#   MYSQL_USER     / ORSEE_DB_USER   username             (default: orsee)
#   MYSQL_PASSWORD / ORSEE_DB_PASS   password
#   ORSEE_DB_HOST / ORSEE_DB_PORT    host/port (direct mode; default 127.0.0.1:3306)
#   BACKUP_DIR                       output dir           (default: ./backups)
#   BACKUP_KEEP_DAYS                 retention in days    (default: 14)
#
# Off-site copy: set BACKUP_RCLONE_REMOTE (e.g. "gdrive:orsee-backups") and have
# rclone configured; the dump is then also uploaded there.
#
# Cron (on the box), nightly at 03:15:
#   15 3 * * * cd /opt/orsee-tec && bash bin/backup.sh >> /var/log/orsee-backup.log 2>&1

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Load .env if present (for MYSQL_*/ORSEE_DB_* values), without clobbering the
# environment that is already set.
if [[ -f .env ]]; then
    set -a; # shellcheck disable=SC1091
    . ./.env; set +a
fi

DB_NAME="${MYSQL_DATABASE:-${ORSEE_DB_NAME:-orsee}}"
DB_USER="${MYSQL_USER:-${ORSEE_DB_USER:-orsee}}"
DB_PASS="${MYSQL_PASSWORD:-${ORSEE_DB_PASS:-}}"
DB_HOST="${ORSEE_DB_HOST:-127.0.0.1}"
DB_PORT="${ORSEE_DB_PORT:-3306}"

BACKUP_DIR="${BACKUP_DIR:-$ROOT/backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$BACKUP_DIR/orsee-$DB_NAME-$STAMP.sql.gz"

mkdir -p "$BACKUP_DIR"

# Decide mode.
if [[ -z "${BACKUP_VIA_DOCKER:-}" ]]; then
    if command -v docker >/dev/null 2>&1 && [[ -f docker-compose.yml ]]; then
        BACKUP_VIA_DOCKER=1
    else
        BACKUP_VIA_DOCKER=0
    fi
fi

echo "[backup] $(date '+%F %T') dumping '$DB_NAME' (docker=$BACKUP_VIA_DOCKER) -> $OUT"

if [[ "$BACKUP_VIA_DOCKER" == "1" ]]; then
    # mysqldump runs inside the db container; no need to publish the DB port.
    docker compose exec -T db \
        mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
        -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT"
else
    mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
        -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT"
fi

# Fail loudly if the dump is suspiciously tiny (empty/failed dump).
SIZE=$(wc -c < "$OUT")
if [[ "$SIZE" -lt 1000 ]]; then
    echo "[backup] ERROR: dump is only ${SIZE} bytes — treating as failure." >&2
    exit 1
fi
echo "[backup] wrote $OUT (${SIZE} bytes)"

# Optional off-site copy via rclone.
if [[ -n "${BACKUP_RCLONE_REMOTE:-}" ]]; then
    if command -v rclone >/dev/null 2>&1; then
        echo "[backup] uploading to $BACKUP_RCLONE_REMOTE"
        rclone copy "$OUT" "$BACKUP_RCLONE_REMOTE"
    else
        echo "[backup] WARNING: BACKUP_RCLONE_REMOTE set but rclone not installed; skipping off-site copy." >&2
    fi
fi

# Prune local backups older than KEEP_DAYS.
find "$BACKUP_DIR" -name "orsee-*.sql.gz" -type f -mtime +"$KEEP_DAYS" -print -delete \
    | sed 's/^/[backup] pruned /' || true

echo "[backup] done."
