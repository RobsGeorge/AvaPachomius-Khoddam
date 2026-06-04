# VPS setup for GitHub Actions deploy

The deploy workflow SSHs in **without a terminal**, so `sudo` cannot ask for a password.

## One-time fix (on the VPS as root)

Replace `deploy` with your SSH user (`SSH_USER` secret), then:

```bash
sudo visudo -f /etc/sudoers.d/avapakhomios-deploy
```

Add:

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/systemctl reload php8.2-fpm, /bin/systemctl reload php8.2-fpm.service
```

Save and verify:

```bash
sudo -u deploy sudo -n true && echo OK
sudo -u deploy sudo -n systemctl reload php8.2-fpm && echo FPM OK
```

## App ownership (recommended)

The deploy user should own the project (or at least `storage/` and `bootstrap/cache/`):

```bash
sudo chown -R deploy:www-data /var/www/avapakhomios
sudo chmod -R ug+rwx /var/www/avapakhomios/storage /var/www/avapakhomios/bootstrap/cache
```

Add deploy to the `www-data` group if needed:

```bash
sudo usermod -aG www-data deploy
```

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
