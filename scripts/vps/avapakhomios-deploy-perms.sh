#!/usr/bin/env bash
# Reclaim Laravel runtime dir ownership/modes for GitHub Actions deploy.
# Install once as root (see .github/DEPLOY-VPS.md):
#   sudo install -o root -g root -m 0755 scripts/vps/avapakhomios-deploy-perms.sh \
#     /usr/local/sbin/avapakhomios-deploy-perms
#
# Intentionally narrow: only known app roots, only storage + bootstrap/cache.
set -euo pipefail

ALLOWED_ROOTS=(
  /var/www/avapakhomios
  /var/www/khedma-staging
)

usage() {
  echo "Usage: $0 <app-root> [owner]" >&2
  echo "       $0 --version" >&2
  echo "Allowed roots: ${ALLOWED_ROOTS[*]}" >&2
  exit 1
}

if [[ "${1:-}" == "--version" ]]; then
  echo "avapakhomios-deploy-perms 2"
  exit 0
fi

APP_ROOT="${1:-}"
OWNER="${2:-deploy:www-data}"

[[ -n "$APP_ROOT" ]] || usage

if command -v realpath >/dev/null 2>&1; then
  APP_ROOT="$(realpath -m -- "$APP_ROOT")"
else
  APP_ROOT="$(cd -- "$APP_ROOT" && pwd)"
fi

allowed=0
for root in "${ALLOWED_ROOTS[@]}"; do
  if [[ "$APP_ROOT" == "$root" ]]; then
    allowed=1
    break
  fi
done

if [[ "$allowed" -ne 1 ]]; then
  echo "Refusing unknown app root: $APP_ROOT" >&2
  exit 1
fi

# Deploy SSH user + www-data group only (e.g. deploy:www-data).
if [[ ! "$OWNER" =~ ^[a-z_][a-z0-9_-]*:www-data$ ]]; then
  echo "Refusing unexpected owner: $OWNER" >&2
  exit 1
fi

STORAGE="${APP_ROOT}/storage"
CACHE="${APP_ROOT}/bootstrap/cache"

if [[ ! -d "$STORAGE" || ! -d "$CACHE" ]]; then
  echo "Missing storage or bootstrap/cache under $APP_ROOT" >&2
  exit 1
fi

chown -R "$OWNER" "$STORAGE" "$CACHE"
find "$STORAGE" "$CACHE" -type d -exec chmod 2775 {} +
find "$STORAGE" "$CACHE" -type f -exec chmod 664 {} +

# Leftovers from ResilientFileStore rename-recovery of deploy-owned hash dirs.
if [[ -d "$STORAGE/framework/cache" ]]; then
  find "$STORAGE/framework/cache" -depth -type d -name '*.unwritable.*' -exec rm -rf {} +
fi
