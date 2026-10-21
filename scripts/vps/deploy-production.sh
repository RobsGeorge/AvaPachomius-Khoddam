set -e
cd /var/www/avapakhomios
# Keep group-write on mkdir 02775 (default umask 0022 → 2755 breaks www-data).
umask 0002

PHP_BIN=php8.2
DEPLOY_USER="$(whoami)"
DEPLOY_FIX_PERMS=/usr/local/sbin/avapakhomios-deploy-perms
DEPLOY_START=$(date +%s)
step() { echo "==> $1 ($(date -Iseconds))"; }
fix_runtime_permissions() {
  # Least-privilege helper (see .github/DEPLOY-VPS.md) — not bare chown/chmod.
  sudo -n "$DEPLOY_FIX_PERMS" "$(pwd)" "${DEPLOY_USER}:www-data"
}

if ! $PHP_BIN -m 2>/dev/null | grep -qi pdo_mysql; then
  echo "ERROR: PHP MySQL driver (pdo_mysql) is not installed for $PHP_BIN."
  echo "On Ubuntu/Debian run:"
  echo "  sudo apt update"
  echo "  sudo apt install -y php8.2-mysql php8.2-sqlite3 php8.2-cli php8.2-fpm php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd"
  echo "  sudo systemctl restart php8.2-fpm"
  exit 1
fi

if ! $PHP_BIN -m 2>/dev/null | grep -qi sqlite; then
  echo "WARN: PHP SQLite extension missing; superadmin events test dashboard requires pdo_sqlite."
  echo "Install with: sudo apt install -y php8.2-sqlite3 && sudo systemctl restart php8.2-fpm"
fi

step "reclaim storage for git"
# PHP-FPM (www-data) creates cache/session files at runtime. Without
# reclaiming ownership first, `git reset --hard` fails with:
#   unable to unlink old 'storage/framework/cache/...': Permission denied
if ! sudo -n "$DEPLOY_FIX_PERMS" --version >/dev/null 2>&1; then
  echo "ERROR: least-privilege deploy sudo is not configured."
  echo "See .github/DEPLOY-VPS.md — as root on the VPS:"
  echo "  sudo install -o root -g root -m 0755 scripts/vps/avapakhomios-deploy-perms.sh $DEPLOY_FIX_PERMS"
  echo "  sudo visudo -f /etc/sudoers.d/avapakhomios-deploy"
  echo "Add only:"
  echo "  ${DEPLOY_USER} ALL=(root) NOPASSWD: $DEPLOY_FIX_PERMS"
  echo "  ${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm, /bin/systemctl reload php8.2-fpm"
  echo "Do NOT grant bare chown/chmod/systemctl."
  echo "Recover (if git sync already broke):"
  echo "  sudo $DEPLOY_FIX_PERMS /var/www/avapakhomios ${DEPLOY_USER}:www-data"
  exit 1
fi
fix_runtime_permissions

step "git sync"
git fetch origin main
git reset --hard origin/main

step "composer install"
COMPOSER_ALLOW_SUPERUSER=1 $PHP_BIN /usr/local/bin/composer install \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --no-dev \
  --no-progress \
  --no-scripts
COMPOSER_ALLOW_SUPERUSER=1 $PHP_BIN /usr/local/bin/composer dump-autoload \
  --optimize \
  --no-dev \
  --no-interaction

step "maintenance mode"
$PHP_BIN artisan down --retry=60 --refresh=15 || true

step "migrations"
$PHP_BIN artisan migrate:deploy --force

step "optimize"
$PHP_BIN artisan optimize:clear
# optimize:clear flushes file-cache hash dirs; recreate the root so the
# first post-deploy request cannot race a missing data/ path.
mkdir -p storage/framework/cache/data
fix_runtime_permissions
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
fix_runtime_permissions

step "storage link"
if [ ! -L public/storage ]; then
  $PHP_BIN artisan storage:link
else
  echo "public/storage link already exists, skipping"
fi

step "permissions"
fix_runtime_permissions

step "reload php-fpm"
if sudo -n systemctl reload php8.2-fpm 2>/dev/null; then
  echo "php8.2-fpm reloaded"
else
  echo "WARN: could not reload php-fpm without password — allow only: systemctl reload php8.2-fpm (see .github/DEPLOY-VPS.md)"
fi

step "ensure scheduler cron"
$PHP_BIN artisan scheduler:ensure-cron --php=$PHP_BIN || echo "WARN: could not ensure scheduler cron"

step "live"
$PHP_BIN artisan up || true

DEPLOY_END=$(date +%s)
echo "Deployment complete in $((DEPLOY_END - DEPLOY_START))s: $(date)"
