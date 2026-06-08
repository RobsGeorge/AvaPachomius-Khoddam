# Platform demo mode (main site `/demo`)

The demo runs on the **same domain and deployment** as production, e.g.:

`https://yourdomain.com/demo`

No subdomain or second app copy is required.

## How data is isolated

**Do not create separate tables.** Use the same schema with an `is_demo` flag on:

- `user`, `course`, `modules`, `content`, `assignments`

`php artisan demo:reset` deletes **only** demo-tagged rows and their children. Real students, courses, and admins are not affected.

## VPS setup (single install at `/var/www/avapakhomios`)

### 1. Deploy the code

Push to `main` so the GitHub Action runs (or on the VPS):

```bash
cd /var/www/avapakhomios
git pull origin main
php8.2 artisan migrate:deploy --force
```

This adds the `is_demo` columns if not already present.

### 2. Enable demo in `.env`

Edit `/var/www/avapakhomios/.env`:

```env
DEMO_ENABLED=true
DEMO_PASSWORD=Demo2026!

# Optional — keep false so real users can still register on the main site
DEMO_BLOCK_REGISTRATION=false
```

Clear config cache:

```bash
php8.2 artisan config:clear
php8.2 artisan config:cache
```

### 3. Seed demo data (one time, or after reset)

```bash
cd /var/www/avapakhomios
php8.2 artisan demo:seed
```

### 4. Open the demo

Visit: **`https://yourdomain.com/demo`**

Click **Enter as student** — no password needed.

A **Try the student demo** link also appears on the login page when `DEMO_ENABLED=true`.

### 5. Optional nightly reset

```bash
crontab -e
```

```cron
0 3 * * * cd /var/www/avapakhomios && php8.2 artisan demo:reset >> storage/logs/demo-reset.log 2>&1
```

## Demo accounts

| Role | Email | Password (via `/login`) |
|------|-------|-------------------------|
| Student | `demo.student@demo.local` | `Demo2026!` |
| Instructor | `demo.instructor@demo.local` | `Demo2026!` |
| Admin | `demo.admin@demo.local` | `Demo2026!` |

## Commands

```bash
php artisan demo:seed    # Replace demo-tagged data with fresh sample content
php artisan demo:reset   # Wipe demo tags + re-seed
```

## What gets seeded

- Roles: `admin`, `instructor`, `student`
- Course: **Servants Prep (Demo)** (current year)
- 2 modules, 4 sessions, lectures, materials, content
- Assignment + submission, grades, attendance
- Online quiz (MCQ + T/F) and offline exam with a sample grade

## Safety notes

- Demo users have `is_demo=true` and are never superadmins.
- With `DEMO_BLOCK_REGISTRATION=false` (default), real registration continues to work.
- Set `DEMO_BLOCK_REGISTRATION=true` only if you want a demo-only site with no new signups.
- After changing `.env`, run `php artisan config:cache` on the VPS.

## Disable demo

```env
DEMO_ENABLED=false
```

Then `config:cache` — `/demo` returns 404. Demo rows remain in the DB until you run `demo:reset` or delete them manually.
