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
