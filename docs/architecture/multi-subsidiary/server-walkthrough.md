# Server & Deployment Walkthrough

Two tracks per phase: **🤖 Cursor** (code/config in the repo) and **🧑 You** (server, DNS, TLS,
deploy). The VPS facts (from `.github/DEPLOY-VPS.md`): path `/var/www/avapakhomios`, user `deploy`,
`www-data`, **PHP 8.2 (php8.2-fpm)**, deploys via GitHub Actions running `migrate:deploy`, web
server reloaded with `systemctl reload php8.2-fpm` (assume **nginx** in front — adjust if Apache).

> Most phases (P0–P3) need **no server changes** beyond running migrations — they ship through your
> normal deploy. The real infra work is concentrated in **P4**.

---

## P0–P3 — schema phases (no infra change)

**🤖 Cursor:** writes migrations/code per the phase prompts; pushes to the branch.

**🧑 You — per phase, after merge:**
```bash
cd /var/www/avapakhomios
php8.2 artisan down --retry=60          # heavy ALTERs (P1 NOT NULL, P3 course_id) lock tables
php8.2 artisan migrate:deploy --force
php8.2 artisan up
php8.2 artisan config:cache && php8.2 artisan route:cache
```
**Before P1's NOT NULL migration and P3's MODIFY**, run the verification SQL in
[database-guide.md](database-guide.md#backfill-verification-run-after-p0-before-p1-not-null) — every
count must be 0. Do P1/P3 ALTERs in an **off-peak window** (the deploy doc already enables
maintenance mode).

**🧑 You — one-time .env additions (safe to add at P0, used later):**
```dotenv
TENANCY_MAIN_SLUG=academy                  # the current platform's real slug
TENANCY_MAIN_NAME="<current institution>"
TENANCY_CONSOLE_HOST=admin.<your-domain>
```
Then `php8.2 artisan config:cache`.

**Rollback (any schema phase):** `php8.2 artisan migrate:rollback --step=1 --force` (new tables drop;
backfills are non-destructive — see database-guide.md).

---

## P4 — Subdomains live (the infra-heavy phase)

Do these **in order**. Don't enable `TrustHosts`/`SESSION_DOMAIN` until DNS + TLS resolve.

### Step 1 — 🧑 DNS (your DNS provider)
Add a **wildcard A/AAAA record**:
```
*.<your-domain>   A   <VPS_IP>
admin.<your-domain>  A  <VPS_IP>     # if not already covered by the wildcard
```
Verify: `dig academy.<your-domain> +short` returns the VPS IP.

### Step 2 — 🧑 Wildcard TLS (certbot, DNS-01)
Wildcards require the **DNS-01** challenge (HTTP-01 cannot issue `*.`):
```bash
sudo certbot certonly --manual --preferred-challenges dns \
  -d '<your-domain>' -d '*.<your-domain>'
# add the TXT record certbot prints, wait for propagation, continue.
# Better: use a DNS-plugin (e.g. certbot-dns-cloudflare) for auto-renew:
sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  -d '<your-domain>' -d '*.<your-domain>'
```
Confirm auto-renew: `sudo certbot renew --dry-run`.

### Step 3 — 🧑 Web server (nginx) — one server block for all subdomains
```nginx
server {
    listen 443 ssl;
    server_name <your-domain> *.<your-domain>;          # wildcard + apex + admin
    ssl_certificate     /etc/letsencrypt/live/<your-domain>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<your-domain>/privkey.pem;
    root /var/www/avapakhomios/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
# (keep a :80 server that redirects to :443)
```
```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Step 4 — 🤖 Cursor (code/config, via deploy)
- Enable `TrustHosts` in `app/Http/Kernel.php` global middleware.
- Add the `sessions` table migration; add the console-host route group; verify subdomain resolution.
- Update `.env.example` + `.github/DEPLOY-VPS.md`.

### Step 5 — 🧑 Switch sessions to the database driver
```bash
cd /var/www/avapakhomios
php8.2 artisan migrate:deploy --force          # creates the sessions table (from Cursor's migration)
```
Edit `.env`:
```dotenv
APP_URL=https://<your-domain>
SESSION_DRIVER=database
SESSION_DOMAIN=.<your-domain>          # leading dot → shared across subdomains
SESSION_SECURE_COOKIE=true
```
```bash
php8.2 artisan config:cache && php8.2 artisan route:cache
sudo systemctl reload php8.2-fpm
```
> ⚠️ Changing `SESSION_DRIVER`/`SESSION_DOMAIN` **logs everyone out** (old file/cookie sessions are
> invalidated). Do it in a maintenance window and announce it.

### Step 6 — 🧑 Verify
- `https://academy.<your-domain>` loads the first subsidiary; `https://admin.<your-domain>` loads
  the console (superadmin only).
- Log in on one subdomain → you're authenticated on another you're a member of; a subdomain you are
  NOT a member of returns 403.
- Superadmin "flush all sessions" / force-logout logs you out across subdomains (DB sessions).

### Local dev equivalent (🤖/🧑)
```
# hosts (or dnsmasq:  address=/inst.test/127.0.0.1)
127.0.0.1 inst.test academy.inst.test service.inst.test admin.inst.test
```
```dotenv
APP_URL=http://inst.test
SESSION_DOMAIN=.inst.test
TENANCY_CONSOLE_HOST=admin.inst.test
```

---

## P5 — Provisioning UI (no infra change)

**🤖 Cursor:** ships console/self-service screens + `TenantProvisioningService`.
**🧑 You:** deploy normally (`migrate:deploy`), then create your first *new* subsidiary from
`admin.<domain>`. Because of P4's wildcard DNS/TLS, its subdomain is **live immediately — no DNS,
cert, or deploy step**. (A subsidiary on its own *custom domain* still needs a per-domain DNS record
+ cert added to nginx — that part is not self-service.)

---

## P6 — Pilot (no infra change)

**🧑 You:** onboard the contrasting second subsidiary from the console; run the validation checklist
in [P6-pilot.md](P6-pilot.md); confirm `EXPLAIN` shows `subsidiary_id` indexes used on hot queries;
have the security review run before GA.

---

## Quick reference — what needs a server action

| Phase | Server action |
|---|---|
| P0 | run migrations; add 3 `.env` lines |
| P1 | run migrations (NOT NULL in off-peak window) |
| P2 | run migrations |
| P3 | run migrations (course_id MODIFY in off-peak window) |
| **P4** | **wildcard DNS + wildcard TLS + nginx server_name + sessions→database + SESSION_DOMAIN (logs users out)** |
| P5 | deploy only; new subdomains auto-live |
| P6 | none (operational) |
