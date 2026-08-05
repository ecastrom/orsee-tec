#!/usr/bin/env bash
#
# BEER Lab / ORSEE: descarga a tu computadora el último respaldo nocturno
# (artefacto del workflow db-backup en GitHub) y lo descifra.
#
# USO:
#   bash bin/descargar-respaldo.sh [carpeta-destino]     # default: ~/orsee-backups
#
# REQUISITOS (una sola vez):
#   - GitHub CLI autenticado:  brew install gh && gh auth login
#   - gpg:                     brew install gnupg
#   - La passphrase de los respaldos (secret BACKUP_PASSPHRASE), exportada:
#       export BACKUP_PASSPHRASE='...'
#     o guardada en ~/.orsee-backup-passphrase (chmod 600).
#     Si no está disponible, el script deja el archivo .gpg sin descifrar.
#
# DESCARGA AUTOMÁTICA DIARIA (macOS/Linux): agrega a `crontab -e`:
#   30 8 * * * bash /ruta/al/repo/bin/descargar-respaldo.sh >/dev/null 2>&1
# (08:30 local, después de que corre el respaldo nocturno.)
#
# No existe una URL pública a propósito: los respaldos contienen datos
# personales de participantes; la descarga exige tu sesión de GitHub.

set -euo pipefail

REPO="ecastrom/orsee-tec"
DEST="${1:-$HOME/orsee-backups}"

command -v gh >/dev/null || { echo "ERROR: falta GitHub CLI. Instala con: brew install gh && gh auth login"; exit 1; }
mkdir -p "$DEST"

# Último artefacto no expirado cuyo nombre empiece con orsee-backup
read -r ART_ID ART_NAME < <(gh api "repos/$REPO/actions/artifacts?per_page=100" \
  --jq '[.artifacts[] | select((.name|startswith("orsee-backup")) and (.expired|not))]
        | sort_by(.created_at) | last | "\(.id) \(.name)"')

if [ -z "${ART_ID:-}" ] || [ "$ART_ID" = "null" ]; then
  echo "ERROR: no hay respaldos todavía. ¿Ya corrió el workflow db-backup?"
  echo "  (Actions -> db-backup -> Run workflow, con los secrets configurados)"
  exit 1
fi

if [ -e "$DEST/$ART_NAME.sql.gz" ] || [ -e "$DEST/$ART_NAME.sql.gz.gpg" ]; then
  echo "Ya descargado: $ART_NAME"
  exit 0
fi

echo "Descargando $ART_NAME ..."
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
gh api "repos/$REPO/actions/artifacts/$ART_ID/zip" > "$TMP/a.zip"
unzip -oq "$TMP/a.zip" -d "$TMP"
GPG_FILE=$(find "$TMP" -name '*.sql.gz.gpg' | head -1)
[ -n "$GPG_FILE" ] || { echo "ERROR: el artefacto no contiene un .sql.gz.gpg"; exit 1; }

# Passphrase: variable de entorno o archivo local
PASS="${BACKUP_PASSPHRASE:-}"
[ -z "$PASS" ] && [ -f "$HOME/.orsee-backup-passphrase" ] && PASS=$(cat "$HOME/.orsee-backup-passphrase")

if [ -n "$PASS" ]; then
  OUT="$DEST/$(basename "${GPG_FILE%.gpg}")"
  gpg --batch --quiet --decrypt --passphrase "$PASS" -o "$OUT" "$GPG_FILE"
  echo "Listo (descifrado): $OUT"
  echo "Para inspeccionarlo: gunzip -k '$OUT' && less '${OUT%.gz}'"
else
  OUT="$DEST/$(basename "$GPG_FILE")"
  mv "$GPG_FILE" "$OUT"
  echo "Listo (CIFRADO, no se encontró passphrase): $OUT"
  echo "Descifra con: gpg --decrypt --passphrase '<BACKUP_PASSPHRASE>' -o '${OUT%.gpg}' '$OUT'"
fi
