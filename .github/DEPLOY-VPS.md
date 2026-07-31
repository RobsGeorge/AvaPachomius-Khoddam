# VPS setup for GitHub Actions deploy

Pushes to `main` run the **CI** workflow first (unit, integration, and load tests). Production deploy runs only if those tests pass (`deploy` job `needs: test`).

Pull requests run **CI** only; they do not deploy.

## Deploy blocked: `unable to unlink old 'storage/...': Permission denied`

GitHub Actions SSH has no password prompt. The deploy user **must** have passwordless
`sudo` for `chown`, `chmod`, and `systemctl reload php8.2-fpm`, and must reclaim
`storage/` + `bootstrap/cache/` before `git reset --hard`.

**One-time fix as root** (replace `deploy` with your `SSH_USER` secret):

```bash
# 1) Allow deploy to fix permissions without a TTY
sudo visudo -f /etc/sudoers.d/avapakhomios-deploy
```

Add (one line, no line breaks):

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/chown, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
```

Save (`visudo` validates syntax). Then verify — use `chown`, not `true` (only the commands above are allowed):

```bash
sudo -u deploy sudo -n chown --version && echo CHOWN OK
sudo -u deploy sudo -n chmod --version && echo CHMOD OK
sudo -u deploy sudo -n systemctl reload php8.2-fpm && echo FPM OK
```

**Recover a failed deploy** (git sync already broke):

```bash
sudo chown -R deploy:www-data /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache
sudo chmod -R ug+rwx /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache
```

Then **Re-run** the failed GitHub Actions deploy job (or push again).

## Deploy blocked: `Permission denied` writing `storage/framework/cache/data`

PHP-FPM runs as `www-data`. Cache directories must be **group-writable** with the
`www-data` group (setgid `2775` on directories is recommended so new hash folders
inherit the group).

**Immediate recovery as root:**

```bash
sudo chown -R deploy:www-data /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache
sudo find /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache -type d -exec chmod 2775 {} +
sudo find /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache -type f -exec chmod 664 {} +
sudo systemctl reload php8.2-fpm
```

Replace `deploy` with your deploy SSH user if different.

## One-time fix (on the VPS as root)

Replace `deploy` with your SSH user (`SSH_USER` secret), then:

```bash
sudo visudo -f /etc/sudoers.d/avapakhomios-deploy
```

Add (one line):

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/chown, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
```

Save and verify:

```bash
sudo -u deploy sudo -n chown --version && echo OK
sudo -u deploy sudo -n systemctl reload php8.2-fpm && echo FPM OK
```

## App ownership (recommended)

The deploy user should own the project (or at least `storage/` and `bootstrap/cache/`):

```bash
sudo chown -R deploy:www-data /var/www/avapakhomios
sudo chmod -R ug+rwx /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache

# Staging (same pattern):
sudo chown -R deploy:www-data /var/www/khedma-staging/storage /var/www/khedma-staging/bootstrap/cache
sudo chmod -R ug+rwx /var/www/khedma-staging/storage /var/www/khedma-staging/bootstrap/cache
```

Deploy scripts reclaim `storage/` + `bootstrap/cache/` ownership **before** `git reset --hard`,
because PHP-FPM (`www-data`) creates cache/session files that otherwise block git with
`unable to unlink old 'storage/...': Permission denied`.

If a deploy fails on git sync with that error, run the chown above once as root (or as a
sudoer), then re-run the failed GitHub Actions deploy job.
Add deploy to the `www-data` group if needed:

```bash
sudo usermod -aG www-data deploy
```

## PHP and Composer requirements

Production deploy uses **PHP 8.2** (`php8.2`), not the system default `php` binary. Run Composer with the same binary:

```bash
php8.2 /usr/local/bin/composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
```

Required extensions (Ubuntu/Debian package names for PHP 8.2):

```bash
sudo apt update
sudo apt install -y \
  php8.2-cli php8.2-fpm php8.2-mysql php8.2-sqlite3 \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd
sudo systemctl restart php8.2-fpm
```

Verify before `composer install`:

```bash
php8.2 -v
php8.2 -m | grep -E 'pdo_mysql|dom|gd|xml|curl|mbstring'
```

If `composer install` fails with missing `ext-*` errors, install/enable those extensions first. On Windows, uncomment the matching lines in `php.ini` (e.g. `extension=pdo_mysql`, `extension=gd`, `extension=dom` is bundled with `php-xml`, `extension=curl`, `extension=mbstring`).

Local development may use PHP 8.5+, but the VPS and CI target **8.2**.

## Storage link message

`The [public/storage] link already exists` is normal on repeat deploys; the workflow now skips `storage:link` when the symlink is present.

## Deploy timeouts

If the workflow stops at `==> migrations`:

1. **Check the last log line** — that step is where it hung.
2. **Run migrations once manually on the VPS** (puts the site in maintenance briefly):

```bash
cd /var/www/avapakhomios
php8.2 artisan down --retry=60
php8.2 artisan migrate:deploy --force
php8.2 artisan up
```

3. **Re-run the GitHub deploy** — it should skip pending migrations and finish quickly.

Heavy schema changes (new columns on `lectures` / `session`) need a quiet window. The deploy workflow enables maintenance mode before migrations to reduce table locks.

If `migrate:deploy` fails with a lock error, wait for traffic to drop or run the manual commands above during off-peak hours.

## Laravel scheduler (cron)

Daily birthday emails, reminders, and other automatic jobs depend on the scheduler.
Without a system cron entry, tasks never run even though they are registered in
`app/Console/Kernel.php`.

Deploy workflows call `php8.2 artisan scheduler:ensure-cron` so the crontab line is
installed (or verified) on every production and staging deploy.

Manual install / repair on the VPS:

```bash
cd /var/www/avapakhomios   # or /var/www/khedma-staging
php8.2 artisan scheduler:ensure-cron --php=php8.2
crontab -l | grep schedule:run
```

Expected line (paths vary by environment):

```bash
* * * * * cd '/var/www/avapakhomios' && 'php8.2' artisan schedule:run >> '/var/www/avapakhomios/storage/logs/scheduler-cron.log' 2>&1
```

Verify:

```bash
cd /var/www/avapakhomios
php8.2 artisan schedule:list | grep -E 'heartbeat|birthdays'
php8.2 artisan schedule:run
# Superadmin → Scheduled tasks should show a healthy heartbeat within 1–2 minutes.
```

## Curriculum private uploads (`storage/app/curriculum`)

Hosted curriculum files (PDFs/slides) are stored on the **`curriculum`** disk (`storage/app/curriculum/`), not under `public/`. After deploy, ensure the directory exists and is writable:

```bash
sudo mkdir -p /var/www/avapakhomios/storage/app/curriculum
sudo chown -R deploy:www-data /var/www/avapakhomios/storage/app/curriculum
sudo chmod -R ug+rwx /var/www/avapakhomios/storage/app/curriculum
```

Repeat for staging (`/var/www/khedma-staging/storage/app/curriculum`). Confirm PHP `upload_max_filesize` / `post_max_size` and Nginx `client_max_body_size` are at least **20M** (see `config/curriculum.php`).

Reconcile per-church usage if needed: `php8.2 artisan church:reconcile-storage`.
