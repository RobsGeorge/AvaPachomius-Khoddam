# Deaconia VPS cutover & database migration runbook

Move the live Laravel app **and** its MySQL database from the current Hostinger VPS
(`avapakhomios.com` / `/var/www/avapakhomios`, staging `/var/www/khedma-staging`) onto a
**new VPS dedicated to Deaconia**, with MySQL privileges that can later support Tier‑1
diocese isolation (`CREATE DATABASE` per diocese).

This is a **platform cutover**, not Slice 12 (isolated-diocese provisioning). Do **not**
automate `organizations:provision-isolated` until this cutover is done and the grant probe
in §8 passes on the new box.

Related: [`../tenancy-cutover.md`](../tenancy-cutover.md) (MULTI_TENANT flag),
[`.github/DEPLOY-VPS.md`](../../.github/DEPLOY-VPS.md) (deploy perms / PHP 8.2),
PARKING-LOT “Diocese-tier data residency” (Hostinger CREATE DATABASE still unverified on old infra).

---

## 0. Decisions to lock before day-of

| Decision | Recommendation |
|---|---|
| **What moves** | App code + **one** primary MySQL schema (prod today). Staging can be re-created fresh on the new VPS or migrated separately. |
| **Isolated diocese DBs** | **None exist yet.** Nothing extra to dump. Provision them only *after* §8. |
| **Tenant Zero** | AvaPachomius = `church_id=1` / `organization_id=1`. Migrate its rows as normal shared data. **Never** run isolation provisioning against org `1`. |
| **Domain** | New apex (e.g. `deaconia.app`) **or** keep `avapakhomios.com` pointed at the new IP. DNS cutover is the go-live switch. |
| **APP_KEY** | **Copy the production `APP_KEY` unchanged.** Laravel `encrypted` casts (e.g. `organizations.db_password_encrypted`) and cookies depend on it. Generating a new key breaks decryption. |
| **Old VPS** | Keep read-only / powered for ≥7 days as rollback. Do not delete dumps. |
| **MULTI_TENANT** | Leave production at current value until a separate tenancy sign-off (`docs/tenancy-cutover.md`). Cutover ≠ enabling multi-tenant. |

### Suggested naming on the new MySQL

| Role | Example name | Purpose |
|---|---|---|
| Central / shared (today’s prod DB) | `deaconia_central` | All current tables; registry; future seat reports |
| Staging (optional) | `deaconia_staging` | Parallel to today’s `avapakhomios_staging` |
| Future Tier‑1 diocese | `deaconia_d_{slug}` | Created later by provisioner — **not** in this cutover |
| App runtime user | `deaconia_app` | CRUD on known DBs only — **no** `CREATE DATABASE` |
| Provision / admin user | `deaconia_provision` | `CREATE`/`DROP` DB + `CREATE USER`/`GRANT` — artisan/ops only |

---

## 1. Inventory on the **old** VPS (do this first)

SSH to the current production box and record facts (redact passwords in notes):

```bash
# Paths & PHP
ls -la /var/www/avapakhomios /var/www/khedma-staging
php8.2 -v

# Production .env (do not paste secrets into chat/tickets)
cd /var/www/avapakhomios
grep -E '^(APP_ENV|APP_URL|APP_KEY|DB_|MULTI_TENANT|TENANCY_|SESSION_|MAIL_|WHATSAPP_)' .env \
  | sed -E 's/(PASSWORD|KEY|TOKEN|SECRET)=.*/\1=***/'

# Confirm which schema is live
php8.2 artisan tinker --execute="echo config('database.connections.mysql.database');"
```

Note especially:

- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` (today often remote Hostinger MySQL or local)
- Exact `APP_KEY` (you will copy it; store in a password manager, not git)
- Disk paths that must sync: `storage/app/curriculum`, `storage/app` uploads, `.env` itself
- Size of the DB (drives maintenance-window length):

```bash
# On the MySQL host that holds production data
mysql -e "SELECT table_schema,
  ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb
  FROM information_schema.tables
  WHERE table_schema = 'YOUR_PROD_DB_NAME'
  GROUP BY table_schema;"
```

Replace `YOUR_PROD_DB_NAME` with the real value from `.env` (staging guard expects
`avapakhomios_staging` on the staging app; prod name is whatever prod `.env` has).

---

## 2. Build the **new** VPS (before touching prod traffic)

### 2.1 Base stack

Match current ops (PHP **8.2**, MySQL 8, Nginx, deploy user):

```bash
sudo apt update
sudo apt install -y nginx mysql-server \
  php8.2-cli php8.2-fpm php8.2-mysql php8.2-sqlite3 \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
  git unzip curl

php8.2 -v
php8.2 -m | grep -E 'pdo_mysql|dom|gd|xml|curl|mbstring'
which mysqldump mysql
```

Install Composer for `php8.2`. Create deploy user, clone repo to e.g.
`/var/www/deaconia` (and later `/var/www/deaconia-staging` if needed). Install the
least-privilege deploy helper from [`.github/DEPLOY-VPS.md`](../../.github/DEPLOY-VPS.md).

### 2.2 MySQL roles (enables future Slice 12)

As MySQL root on the **new** server:

```sql
-- Central DB empty shell (data loaded in §4)
CREATE DATABASE deaconia_central
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Runtime app user: DML only on central (add more DBs to GRANT when Tier-1 ships)
CREATE USER 'deaconia_app'@'localhost' IDENTIFIED BY 'REDACT_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, TRIGGER
  ON deaconia_central.* TO 'deaconia_app'@'localhost';
-- Note: CREATE/ALTER/DROP *tables* are needed for migrations; that is not CREATE DATABASE.

-- Provision user: schema create for future diocese isolation (Slice 12)
CREATE USER 'deaconia_provision'@'localhost' IDENTIFIED BY 'REDACT_OTHER_STRONG_PASSWORD';
GRANT CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE USER ON *.* TO 'deaconia_provision'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `deaconia_%`.* TO 'deaconia_provision'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

Adjust host (`localhost` vs `%` / app server IP) if MySQL is remote from PHP.

**Probe (must pass before you claim Tier‑1-ready):**

```bash
mysql -u deaconia_provision -p -e "
  CREATE DATABASE khedma_provision_probe_tmp;
  DROP DATABASE khedma_provision_probe_tmp;
"
mysql -u deaconia_app -p -e "
  CREATE DATABASE should_fail_if_locked_down;
"   # expect Access denied — good
```

### 2.3 App skeleton on new VPS

```bash
sudo mkdir -p /var/www/deaconia
sudo chown -R deploy:www-data /var/www/deaconia
sudo -u deploy git clone git@github.com:RobsGeorge/AvaPachomius-Khoddam.git /var/www/deaconia
cd /var/www/deaconia
sudo -u deploy git checkout staging   # or main — match the branch you intend to run
sudo -u deploy php8.2 /usr/local/bin/composer install \
  --no-interaction --prefer-dist --optimize-autoloader --no-dev
```

Create `.env` from production (see §3) **before** migrate/restore verification. Point Nginx
`root` at `/var/www/deaconia/public`; use a temporary hostname or IP until DNS flips.
Issue TLS (Let’s Encrypt) for the eventual apex + wildcard if multi-tenant subdomains stay.

---

## 3. Prepare the new `.env` (before restore go-live)

Copy production `.env` to the new box securely (`scp`, not Slack/git).

Change at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_NEW_APEX

# KEEP THE SAME production APP_KEY — do not regenerate
APP_KEY=base64:PASTE_EXACT_OLD_VALUE

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=deaconia_central
DB_USERNAME=deaconia_app
DB_PASSWORD=...

# Optional later for Slice 12 (not required for cutover):
# DB_PROVISION_HOST=127.0.0.1
# DB_PROVISION_USERNAME=deaconia_provision
# DB_PROVISION_PASSWORD=...

# Update if domain changes:
# TENANCY_BASE_DOMAIN=your.new.apex
# TENANCY_CONSOLE_HOST=admin.your.new.apex
# SESSION_DOMAIN=.your.new.apex
# SESSION_DRIVER=database   # only if already used in prod / staging cutover

# Mail / WhatsApp / CORS / FRONTEND_URL — point at new public URLs
```

Then:

```bash
cd /var/www/deaconia
php8.2 artisan key:generate --show   # ONLY to verify format; do NOT write a new key
php8.2 artisan config:clear
```

---

## 4. Database migration — step-by-step (detailed)

Goal: consistent copy of **one** production schema → `deaconia_central` on the new VPS,
with row-count verification and a rehearsed rollback.

### 4.1 Maintenance window (old prod)

Announce downtime. On **old** app:

```bash
cd /var/www/avapakhomios
php8.2 artisan down --retry=60 --refresh=15
# Stop queue workers / scheduler if any (cron still OK; jobs should no-op or pause)
```

Prefer a short window where writes stop so dump ≈ final state. For a large DB, do a
**dress rehearsal** dump/restore days earlier (§4.7), then a final delta dump in the window.

### 4.2 Take the dump on the MySQL that holds prod data

Run where you can reach the **source** database (often the old VPS or Hostinger MySQL host).
Use the same credentials as old prod `.env` (or a read-only replica if you have one).

```bash
export SRC_HOST=...          # from old DB_HOST
export SRC_USER=...          # from old DB_USERNAME
export SRC_DB=...            # from old DB_DATABASE
export DUMP_DIR=/var/backups/deaconia-cutover
sudo mkdir -p "$DUMP_DIR"
sudo chown "$(whoami)" "$DUMP_DIR"

# Timestamped logical dump — single-transaction keeps InnoDB consistent without long LOCK
mysqldump \
  -h"$SRC_HOST" -u"$SRC_USER" -p \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  --default-character-set=utf8mb4 \
  --set-gtid-purged=OFF \
  "$SRC_DB" \
  | gzip -c > "$DUMP_DIR/${SRC_DB}-$(date +%Y%m%d-%H%M%S).sql.gz"

# Record counts for later compare (save output)
mysql -h"$SRC_HOST" -u"$SRC_USER" -p "$SRC_DB" -N -e "
  SELECT table_name, table_rows
  FROM information_schema.tables
  WHERE table_schema = '$SRC_DB'
  ORDER BY table_name;
" > "$DUMP_DIR/row-estimates-before.txt"

# Exact counts for critical tables (adjust names if needed)
mysql -h"$SRC_HOST" -u"$SRC_USER" -p "$SRC_DB" -N -e "
  SELECT 'user', COUNT(*) FROM user
  UNION ALL SELECT 'church', COUNT(*) FROM church
  UNION ALL SELECT 'organizations', COUNT(*) FROM organizations
  UNION ALL SELECT 'church_user', COUNT(*) FROM church_user
  UNION ALL SELECT 'people', COUNT(*) FROM people
  UNION ALL SELECT 'migrations', COUNT(*) FROM migrations;
" > "$DUMP_DIR/exact-counts-before.txt"

ls -lh "$DUMP_DIR"
sha256sum "$DUMP_DIR"/*.sql.gz | tee "$DUMP_DIR/SHA256SUMS"
```

Notes:

- `--single-transaction` is correct for **InnoDB** (this app). Avoid `--lock-all-tables` unless you have MyISAM leftovers.
- Do **not** commit dump files to git. Store on encrypted disk / off-box backup.
- If MySQL is remote and `mysqldump` is slow, run dump on a host close to the DB, then `scp` the `.sql.gz` to the new VPS.

### 4.3 Copy dump to the new VPS

```bash
scp -P 22 /var/backups/deaconia-cutover/*.sql.gz \
  deploy@NEW_VPS_IP:/var/backups/deaconia-cutover/
scp /var/backups/deaconia-cutover/SHA256SUMS \
  deploy@NEW_VPS_IP:/var/backups/deaconia-cutover/
scp /var/backups/deaconia-cutover/exact-counts-before.txt \
  deploy@NEW_VPS_IP:/var/backups/deaconia-cutover/
```

On the new VPS:

```bash
cd /var/backups/deaconia-cutover
sha256sum -c SHA256SUMS
```

### 4.4 Restore into `deaconia_central`

```bash
# Empty target (safe if never served traffic)
mysql -u root -p -e "DROP DATABASE IF EXISTS deaconia_central;
  CREATE DATABASE deaconia_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Re-apply grants if DROP removed them (MySQL may keep user; re-GRANT to be sure)
mysql -u root -p -e "
  GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, TRIGGER
    ON deaconia_central.* TO 'deaconia_app'@'localhost';
  FLUSH PRIVILEGES;
"

DUMP=$(ls -1t /var/backups/deaconia-cutover/*.sql.gz | head -1)
gunzip -c "$DUMP" | mysql -u root -p deaconia_central
```

### 4.5 Verify restore

```bash
mysql -u deaconia_app -p deaconia_central -N -e "
  SELECT 'user', COUNT(*) FROM user
  UNION ALL SELECT 'church', COUNT(*) FROM church
  UNION ALL SELECT 'organizations', COUNT(*) FROM organizations
  UNION ALL SELECT 'church_user', COUNT(*) FROM church_user
  UNION ALL SELECT 'people', COUNT(*) FROM people
  UNION ALL SELECT 'migrations', COUNT(*) FROM migrations;
" > /var/backups/deaconia-cutover/exact-counts-after.txt

diff -u /var/backups/deaconia-cutover/exact-counts-before.txt \
        /var/backups/deaconia-cutover/exact-counts-after.txt
# Expect empty diff

mysql -u deaconia_app -p deaconia_central -e "
  SELECT organization_id, type, subdomain, placement_policy, db_isolated
  FROM organizations
  WHERE organization_id = 1;
"
# Expect Tenant Zero row, placement shared / db_isolated = 0
```

App-side check (new VPS, `.env` already pointing at `deaconia_central`):

```bash
cd /var/www/deaconia
php8.2 artisan migrate:status
# Should list migrations as Ran — matching old prod. Do NOT migrate:fresh.

php8.2 artisan tinker --execute="
  echo 'org1=' . \\App\\Models\\Organization::query()->find(1)?->subdomain . PHP_EOL;
  echo 'users=' . \\App\\Models\\User::query()->count() . PHP_EOL;
"
```

If `migrate:status` shows **pending** migrations that already ran on old prod under different names, stop and reconcile — do not blindly `migrate` without understanding drift.

### 4.6 Sync file storage (not in mysqldump)

On old VPS → new VPS (examples):

```bash
# Curriculum private files
rsync -avz --progress \
  deploy@OLD:/var/www/avapakhomios/storage/app/curriculum/ \
  /var/www/deaconia/storage/app/curriculum/

# Other local disks under storage/app (uploads, etc.) — exclude cache/sessions/logs if desired
rsync -avz --progress \
  --exclude 'framework/' --exclude 'logs/' \
  deploy@OLD:/var/www/avapakhomios/storage/app/ \
  /var/www/deaconia/storage/app/

cd /var/www/deaconia
php8.2 artisan storage:link
sudo /usr/local/sbin/avapakhomios-deploy-perms /var/www/deaconia deploy:www-data
```

### 4.7 Dress rehearsal (strongly recommended)

Days before go-live: dump → restore → boot app on a **private** hostname / hosts-file entry,
log in as superadmin, open Tenant Zero church, spot-check attendance/people/billing. Fix gaps
while old prod still serves traffic. Final cutover then repeats §4.1–4.6 with a fresh dump.

---

## 5. DNS / TLS go-live

1. Lower TTL on the apex / www / admin / wildcard records **24–48h before** cutover.  
2. After restore + app smoke on the new IP (hosts file or temporary A record):  
   - Point apex + `www` (+ wildcard `*.apex` if used) to **new VPS IP**.  
   - Confirm certbot / TLS covers apex and wildcard.  
3. On new app:

```bash
cd /var/www/deaconia
php8.2 artisan up
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
php8.2 artisan scheduler:ensure-cron --php=php8.2
```

4. Smoke from a clean browser/network:

   - Login, OTP/mail path, Tenant Zero home  
   - Superadmin console host  
   - One write (e.g. harmless settings) then confirm it appears only on **new** DB  
5. Leave old app in `artisan down` (or stop Nginx vhost) so it cannot accept writes and diverge.

---

## 6. Update CI / deploy pipelines

After go-live, point GitHub Actions SSH secrets / paths at the new VPS:

| Secret / path | Old (typical) | New |
|---|---|---|
| `SSH_HOST` | old Hostinger IP | new VPS IP |
| Deploy path prod | `/var/www/avapakhomios` | `/var/www/deaconia` |
| Deploy path staging | `/var/www/khedma-staging` | e.g. `/var/www/deaconia-staging` |
| Staging DB guard | `DB_DATABASE=avapakhomios_staging` | update workflow grep to `deaconia_staging` (code change in `.github/workflows/deploy-staging.yml`) |

Until workflows are updated, use `/var/www/deploy.sh` (or manual SSH) so you do not deploy to the retired box.

---

## 7. What you must **not** do during cutover

- Do **not** `migrate:fresh` / `db:wipe` on production data.  
- Do **not** regenerate `APP_KEY`.  
- Do **not** run diocese isolation provisioning against `organization_id = 1` (Tenant Zero).  
- Do **not** flip `MULTI_TENANT` as part of this move unless staging tenancy is already signed off.  
- Do **not** delete the old VPS or old dumps until post-cutover soak (≥7 days) and a second off-site dump exists.  
- Do **not** commit `.env`, dump files, or provision passwords.

---

## 8. After cutover — gate for Slice 12 (isolated diocese DBs)

Only when these pass on the **new** VPS should you implement / run Tier‑1 provisioning:

```bash
# Provision role can create/drop databases
mysql -u deaconia_provision -p -e "CREATE DATABASE khedma_provision_probe_tmp; DROP DATABASE khedma_provision_probe_tmp;"

# mysqldump works for N databases (central today; diocese DBs later)
mysqldump -u deaconia_app -p deaconia_central --single-transaction --no-data >/dev/null && echo DUMP_OK

# App still sees Tenant Zero shared
cd /var/www/deaconia
php8.2 artisan tinker --execute="
  \$o = \\App\\Models\\Organization::query()->find(1);
  echo \$o->placement_policy . ' isolated=' . (int)\$o->db_isolated . PHP_EOL;
"
```

Then: throwaway `type=diocese` org → provision → migrate → seat COUNT-only reporter → per-DB backup restore test. Keep AvaPachomius on `placement_policy=shared`.

---

## 9. Rollback (if new VPS fails after DNS)

1. Point DNS A/AAAA records back to the **old** VPS IP.  
2. On old VPS: `cd /var/www/avapakhomios && php8.2 artisan up`.  
3. Treat the new DB as disposable until the next attempt (or keep it as a stale copy — do not write to both).  
4. If any writes happened only on the new DB during the failed window, dump those tables and plan a manual merge before retrying — avoid split-brain.

---

## 10. Checklist summary

**Before window**

- [ ] New VPS: Nginx, PHP 8.2, MySQL 8, deploy perms, git clone, composer  
- [ ] Users `deaconia_app` + `deaconia_provision`; CREATE DATABASE probe OK  
- [ ] `.env` copied; **same** `APP_KEY`; `DB_DATABASE=deaconia_central`  
- [ ] Dress-rehearsal dump/restore + login smoke  
- [ ] TTL lowered; TLS plan ready  

**During window**

- [ ] Old: `artisan down`  
- [ ] `mysqldump --single-transaction` + gzip + SHA256 + exact counts  
- [ ] `scp` + restore into `deaconia_central`  
- [ ] Count diff empty; `migrate:status` sane  
- [ ] `rsync` `storage/app` (curriculum+)  
- [ ] DNS → new IP; `artisan up`; smoke writes  

**After**

- [ ] Old stays down / retired for soak  
- [ ] Off-site copy of dump  
- [ ] Update GitHub deploy secrets/paths (+ staging DB name guard)  
- [ ] Only then schedule Slice 12 isolated-diocese work  

---

## 11. Staging on the new VPS (optional companion)

Either:

**A. Fresh staging** — empty `deaconia_staging`, `php8.2 artisan migrate:deploy --force`, seed pilot church; or  

**B. Copy staging DB** — same dump/restore steps with source `avapakhomios_staging` → `deaconia_staging`, then update `.github/workflows/deploy-staging.yml` safety grep accordingly.

Never point a staging deploy at `deaconia_central` / production data.
