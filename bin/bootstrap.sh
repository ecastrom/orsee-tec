#!/usr/bin/env bash
# One-command setup for the BEER Lab ORSEE box (Ubuntu/Debian).
#
# Run this ON the mini-PC after installing Ubuntu Server and cloning this repo:
#
#   git clone <repo-url> /opt/orsee-tec
#   cd /opt/orsee-tec
#   bash bin/bootstrap.sh
#
# It is idempotent — safe to re-run. It will:
#   1. install Docker, the compose plugin, and git (via apt)
#   2. enable Docker to start on boot
#   3. create .env from the template with strong random DB passwords (first run)
#   4. build and start the stack (ORSEE + MySQL + cron)
#   5. install a nightly backup cron job
#
# It does NOT configure the public domain / Cloudflare Tunnel or SMTP — those need
# your secrets. Edit .env afterwards and follow bin/self-host.md §5–§7.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

log() { printf '\n\033[1;34m[bootstrap]\033[0m %s\n' "$*"; }
warn() { printf '\n\033[1;33m[bootstrap]\033[0m %s\n' "$*" >&2; }
die()  { printf '\n\033[1;31m[bootstrap]\033[0m %s\n' "$*" >&2; exit 1; }

# --- sanity checks --------------------------------------------------------
command -v apt-get >/dev/null 2>&1 || die "This script targets Ubuntu/Debian (apt not found)."
if [[ "$(id -u)" -eq 0 ]]; then SUDO=""; else
    command -v sudo >/dev/null 2>&1 || die "Run as root or install sudo."
    SUDO="sudo"
fi

# --- 1. system packages ---------------------------------------------------
log "Installing Docker, compose plugin, git, openssl ..."
$SUDO apt-get update -qq
# docker.io + docker-compose-v2 are in the Ubuntu repos and are the simplest,
# most reliable choice for a set-and-forget box.
$SUDO apt-get install -y --no-install-recommends \
    docker.io docker-compose-v2 git openssl ca-certificates

# --- 2. docker on boot + group -------------------------------------------
log "Enabling Docker to start on boot ..."
$SUDO systemctl enable --now docker

# Decide how we call docker in THIS session. A fresh docker-group membership
# does not apply until re-login, so fall back to sudo if the socket isn't usable.
if docker info >/dev/null 2>&1; then
    DOCKER="docker"
elif $SUDO docker info >/dev/null 2>&1; then
    DOCKER="$SUDO docker"
    if [[ -n "$SUDO" ]]; then
        $SUDO usermod -aG docker "$USER" || true
        warn "Added $USER to the 'docker' group. Log out and back in to run docker without sudo."
    fi
else
    die "Docker was installed but is not responding. Check: $SUDO systemctl status docker"
fi

# --- 3. .env with strong random passwords (first run only) ----------------
if [[ -f .env ]]; then
    log ".env already exists — leaving it untouched."
else
    log "Creating .env with strong random database passwords ..."
    cp .env.example .env
    gen() { openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }
    DBPW="$(gen)"; ROOTPW="$(gen)"
    # Replace the placeholder values only.
    sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=${DBPW}|" .env
    sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=${ROOTPW}|" .env
    warn "Database passwords were generated and written to .env. Keep .env safe (it is git-ignored)."
fi

# --- 4. build & start the stack ------------------------------------------
log "Building and starting the stack (ORSEE + MySQL + cron). First run pulls images and imports the schema ..."
$DOCKER compose up -d --build

# --- 5. nightly backup cron ----------------------------------------------
CRON_LINE="15 3 * * * cd ${ROOT} && bash bin/backup.sh >> /var/log/orsee-backup.log 2>&1"
if crontab -l 2>/dev/null | grep -Fq "bin/backup.sh"; then
    log "Backup cron already installed."
else
    log "Installing nightly backup cron (03:15) ..."
    $SUDO touch /var/log/orsee-backup.log && $SUDO chown "$USER" /var/log/orsee-backup.log || true
    ( crontab -l 2>/dev/null; echo "$CRON_LINE" ) | crontab -
fi

# --- done -----------------------------------------------------------------
IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
cat <<EOF

============================================================
 ORSEE is starting up.
 Local test URL:   http://${IP:-<box-ip>}:8080/admin
 First login:      user 'orsee_install'  password 'install'
                   >>> CHANGE THIS PASSWORD IMMEDIATELY <<<

 Next steps (see bin/self-host.md):
   - Edit .env: set ORSEE_SERVER_URL / _PROTOCOL, SMTP, and (for public
     access) CLOUDFLARE_TUNNEL_TOKEN.
   - For a public HTTPS domain, start with the tunnel overlay:
       $DOCKER compose -f docker-compose.yml -f docker-compose.tunnel.yml up -d
   - Verify a backup runs:  bash bin/backup.sh
============================================================
EOF
