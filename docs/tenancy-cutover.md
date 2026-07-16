# T7 — Tenancy cutover runbook

Contract phase for Khedma multi-church (master-plan §7 T7 / P6 pilot).

## What this phase does

1. **Schema contract:** backfill remaining `NULL church_id` → Tenant Zero, then
   `NOT NULL` on tenant tables (MySQL). Platform `roles` templates stay nullable.
2. **BelongsToChurch:** while `MULTI_TENANT=false`, inserts still stamp Tenant Zero
   so NOT NULL never breaks production dormancy.
3. **Pilot church:** `php artisan tenancy:seed-pilot-church` provisions a contrasting
   second tenant (limited capabilities — no exams by default).

## Staging enablement (order matters)

1. Deploy T7 migrations; confirm zero NULL `church_id` on tenant tables
   (except template rows in `roles`).
2. Wildcard DNS + TLS for `{slug}.TENANCY_BASE_DOMAIN`.
3. Set in staging `.env`:
   - `MULTI_TENANT=true`
   - `TENANCY_BASE_DOMAIN=…`
   - `TENANCY_CONSOLE_HOST=admin.…`
   - `SESSION_DRIVER=database` (and run sessions migration if not already)
4. `php artisan tenancy:seed-pilot-church pilot-service --name="…" --admin=you@example.com`
5. Smoke: main slug, pilot slug, console host; isolation suite green;
   church switcher + login membership gate.

## Production

Keep `MULTI_TENANT=false` until staging pilot is signed off. Flipping production is a
dedicated ops change after the P6 checklist in
`docs/architecture/multi-subsidiary/P6-pilot.md`.

## Rollback

- Suspend the pilot church (`status=suspended`) — ResolveTenant 404s it.
- Set `MULTI_TENANT=false` to restore dormant Tenant Zero behavior (scopes unbound).
- Do **not** reverse NOT NULL in production without a dedicated contraction PR.
