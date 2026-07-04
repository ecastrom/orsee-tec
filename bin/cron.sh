#!/usr/bin/env bash
# ORSEE cron runner for the BEER Lab deployment.
#
# ORSEE needs a periodic task that flushes the mail queue and fires scheduled
# reminders/cronjobs. Upstream installs a crontab that does:
#     cd $ORSEEROOT/admin && php -q $ORSEEROOT/admin/cron.php
# cron.php uses paths relative to admin/, so we replicate that working dir.
#
# Heroku:  add a Heroku Scheduler job (every 10 minutes) with command:
#              bash bin/cron.sh
# Docker:  the dedicated `cron` service invokes this on a loop (see docker-compose.yml).
# VPS:     call it from a real crontab every 5 minutes.

set -euo pipefail

# Resolve repo root regardless of where we're invoked from.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

cd "$ROOT/orsee/admin"
exec "$PHP_BIN" -q "$ROOT/orsee/admin/cron.php"
