# VPS setup for GitHub Actions deploy

Pushes to `main` run the **CI** workflow first (unit, integration, and load tests). Production deploy runs only if those tests pass (`deploy` job `needs: test`).

Pull requests run **CI** only; they do not deploy.

## Deploy blocked: `unable to unlink old 'storage/...': Permission denied`

GitHub Actions SSH has no password prompt. The deploy user **must** reclaim
`storage/` + `bootstrap/cache/` before `git reset --hard`, and may reload
`php8.2-fpm` after deploy.

Do **not** grant passwordless sudo on the full `chown` / `chmod` / `systemctl`
binaries — that is root-equivalent if the deploy account is compromised. Use the
least-privilege helper + a single `systemctl reload` rule below.

### One-time fix as root (least privilege)

Replace `deploy` with your `SSH_USER` secret. From a checkout of this repo on the VPS
(or after copying the script):

```bash
# 1) Install the permission helper (root-owned; deploy cannot modify it)
sudo install -o root -g root -m 0755 \
  scripts/vps/avapakhomios-deploy-perms.sh \
  /usr/local/sbin/avapakhomios-deploy-perms

# 2) Allow only that helper + php-fpm reload (no bare chown/chmod/systemctl)
sudo visudo -f /etc/sudoers.d/avapakhomios-deploy
```

Put exactly these lines (no bare `/usr/bin/systemctl` without args):

```
deploy ALL=(root) NOPASSWD: /usr/local/sbin/avapakhomios-deploy-perms
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm, /bin/systemctl reload php8.2-fpm
```

Save (`visudo` validates syntax). Then verify:

```bash
sudo -u deploy sudo -n /usr/local/sbin/avapakhomios-deploy-perms --version && echo PERMS OK
sudo -u deploy sudo -n systemctl reload php8.2-fpm && echo FPM OK
# These must FAIL (password required / not allowed):
# sudo -u deploy sudo -n systemctl status ssh
# sudo -u deploy sudo -n chown root:root /tmp
```

If you previously installed the broad rule
(`NOPASSWD: /usr/bin/chown, … /usr/bin/systemctl`), replace that file with the
lines above.

**Recover a failed deploy** (git sync already broke) — as root, or via the helper:

```bash
sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/avapakhomios deploy:www-data
# Staging:
# sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/khedma-staging deploy:www-data
```

Then **Re-run** the failed GitHub Actions deploy job (or push again).

## Deploy blocked: `Permission denied` writing `storage/framework/cache/data`

PHP-FPM runs as `www-data`. Cache directories must be **group-writable** with the
`www-data` group (setgid `2775` on directories is recommended so new hash folders
inherit the group). The deploy helper applies those modes automatically.

**Immediate recovery as root:**

```bash
sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/avapakhomios deploy:www-data
sudo systemctl reload php8.2-fpm
```

Replace `deploy` with your deploy SSH user if different.

## Runtime 500: `chmod(): Operation not permitted` (login / impersonate / cache)

Symptom in `storage/logs/laravel.log`:

```text
chmod(): Operation not permitted ... Illuminate/Filesystem/Filesystem.php:288
```

Cause: deploy/cron create file-cache hash dirs as the deploy user. With default
`umask 0022`, `mkdir(..., 02775)` becomes `2755` (no group write). PHP-FPM
(`www-data`) can still hit Laravel's post-write `chmod`, which requires
**ownership** and throws — often during permission-cache writes on login or
impersonation.

App-side mitigation (ResilientFileStore + SoftChmodFilesystem) soft-fails chmod
and recreates unwritable hash dirs when the cache root is group-writable. Ops
still need a healthy tree:

```bash
# One-shot heal (prod). Same helper as deploy.
sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/avapakhomios deploy:www-data
sudo systemctl reload php8.2-fpm
```

Deploy workflows set `umask 0002` before artisan so new dirs keep group-write.
Re-install the helper after pulling if the script on the VPS is stale:

```bash
sudo install -o root -g root -m 0755 \
  scripts/vps/avapakhomios-deploy-perms.sh \
  /usr/local/sbin/avapakhomios-deploy-perms
```

## Runtime `ERR_TOO_MANY_REDIRECTS` for some users (broken cache shard)

Symptom: a user opening the bare domain gets *"This page isn't working — redirected you
too many times"*, while other users browse normally. The affected browser bounces between
`/` and `/login`.

Cause: one file-cache hash directory is not writable by `www-data`, so every request that
has to write a key hashing into it fails. Cache keys are per user and per locale
(`translations.db.ar`, `perms:system:<user_id>`, …), which is why only some users are hit.

Confirm from the VPS:

```bash
# 1) Does the bare domain bounce? (a healthy guest gets exactly one 302 to /login)
curl -sIL --max-redirs 10 -o /dev/null \
  -w 'final=%{http_code} redirects=%{num_redirects}\n' https://avapakhomios.com/

# 2) Which cache dirs www-data cannot write?
#    cd /tmp first: www-data cannot read /home/deploy, and find then fails to
#    restore its working directory.
cd /tmp && sudo -u www-data find /var/www/avapakhomios/storage/framework/cache/data \
  -type d ! -writable -printf '%p %u:%g %m\n'

# 3) Matching errors in the log
grep -E 'Permission denied|chmod\(\)|mkdir\(\)' /var/www/avapakhomios/storage/logs/laravel.log | tail
```

A line such as `.../cache/data/02/50 root:www-data 2755` is the signature: owner `root`
and mode `2755` (setgid set, **group-write missing**) means an artisan command ran as
root under `umask 0022`. `www-data` can then neither write into that shard nor create a
missing one — the log shows `file_put_contents(...): Permission denied` for shards that
exist and `mkdir(): Permission denied` for shards that do not.

Run artisan as the deploy user (or `sudo -u www-data`) with `umask 0002`, never as root.

Recovery is the same permission heal as above:

```bash
sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/avapakhomios deploy:www-data
sudo systemctl reload php8.2-fpm
```

Since the storage-permission fix, an unwritable cache only degrades performance
(values are recomputed uncached) and any unrecoverable storage failure renders a
terminal 503 page — never a redirect.

## App ownership (recommended)

The deploy user should own the project (or at least `storage/` and `bootstrap/cache/`):

```bash
sudo chown -R deploy:www-data /var/www/avapakhomios
sudo chmod -R ug+rwx /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache
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
