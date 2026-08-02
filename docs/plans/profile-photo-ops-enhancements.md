# Profile Photo Ops Enhancements (Features 1–8)

**Status:** Planned — implement after shipping profile-photo review ops (#139 / #140).  
**Module:** Profile photo gate + `/admin/profile-photos` review hub.  
**Constraints:** Additive only; localize ar+en; Policies + permission keys; `audit_log` on destructive actions; tests (incl. tenant isolation where scoped); no npm build step.

## Current baseline (already shipped)

- Upload → `pending` → course-admin portal+email notify (high priority).
- Approve → student email with dashboard link; Reject → student email (existing).
- Review modal with actions under the photo; actions gated by compliance status.
- Status action matrix: pending = approve/reject; rejected/in_grace/overdue = extend+reset; not_started = extend; approved = none.

---

## Epic goals

1. Make the pending queue operable at volume (bulk + aging + keyboard flow).
2. Close the student loop after reject (reminders + clearer pending copy).
3. Make every review decision auditable and reversible when needed.
4. Soften upload quality friction without over-engineering ML.

---

## Feature backlog

### P0 — Operational must-haves

#### 1. Bulk approve / reject
**Why:** Urgent admin notifies will pile up; one-by-one review does not scale.  
**Scope:**
- Pending filter: checkbox column + “Select all on page”.
- Bulk bar: Approve selected | Reject selected (shared optional rejection note).
- Server: `ProfilePhotoAdminService::approveMany` / `rejectMany` in a DB transaction; per-user eligibility via `adminActions()`; skip ineligible IDs with a flash summary.
- Emit existing per-student approval/rejection emails (or batch-safe queue jobs if SMTP rate-limits).
- Permission: existing `profile_photo.review`.
- Tests: multi-student approve/reject; ineligible IDs ignored; emails/notifications asserted.

**UI:** Desktop table first; mobile cards get a compact multi-select if low cost, else desktop-only v1.

#### 4. Audit log on review actions
**Why:** Hard product rule — destructive/compliance actions → `audit_log`.  
**Scope:**
- Log: approve, reject (include note), extend deadline (old/new), reset grace, bulk variants, revoke (feature 5).
- Payload: actor `user_id`, target `user_id`, before/after status, note, source (`table` | `modal` | `bulk`).
- Follow existing audit helper/pattern in the codebase (do not invent a parallel logger).
- Tests: each action writes an audit row with expected metadata.

#### 3. Pending aging in the queue
**Why:** Pair with urgent notify so oldest pending is worked first.  
**Scope:**
- Default sort for `filter=pending_review`: `profile_photo_uploaded_at` ASC (nulls last).
- Badge / relative age in table + modal header (“waiting 2h”, “waiting 1d”) — ar+en via localized relative time or simple day/hour buckets.
- Optional filter chips: &lt; 24h | 1–3d | &gt; 3d (query params).
- Tests: ordering and age label for fixed timestamps.

---

### P1 — Student loop + admin speed

#### 2. Reject → re-upload reminder
**Why:** Rejected students can go overdue silently.  
**Scope:**
- Scheduled command/scanner (mirror `NotificationScannerService` patterns): students with `profile_photo_status = rejected`, no new pending upload, and `profile_photo_reviewed_at` older than N days (portal setting, default 2).
- Channels: portal + email (hub type e.g. `profile_photo_reupload_reminder`); audience student; dedupe per student per reject cycle (`reviewed_at` in dedupe key).
- Stop when they upload (status → pending) or are approved.
- Tests: fires once in window; does not fire if re-uploaded; respects preferences.

#### 6. Clearer student copy while pending
**Why:** Pending is not hard-blocked; users often think they are locked.  
**Scope:**
- Revise `pages.profile_photo_pending_banner` / profile success flash (ar+en): explicit “access remains open until staff decides”.
- Optional one-line on profile page under pending state.
- No schema change. Smoke/UX assertion in an existing gate UX test if present.

#### 7. Keyboard / next-student flow in review modal
**Why:** Bulk of time is open → decide → open next.  
**Scope:**
- After successful approve/reject from modal: if more pending on the current page (or fetch next pending id), open next student’s review modal without full reload **or** redirect back with `?filter=pending_review&focus={user_id}` that auto-opens modal.
- Prefer progressive enhancement: form POST redirects with flash + `focus` query (reliable, no SPA). Optional: `fetch` + JSON later.
- Shortcuts (when modal open): `A` approve, `R` focus reject note / submit with confirm — only if no input focused; document in a small help hint in modal.
- Tests: approve redirects/focuses next pending; last pending closes cleanly.

---

### P2 — Safety valve + soft quality

#### 5. Revoke approval
**Why:** Wrong photo can slip through; today no clean path.  
**Scope:**
- New action for `approved` status only: “Revoke approval” → status `rejected` (or dedicated `revoked` only if needed — prefer reuse `rejected` + note prefix to avoid schema churn).
- Required note; clear photo file like reject **or** keep file and force re-upload — product choice: **clear file + rejected** (consistent with reject).
- Email student (reuse rejection mail or dedicated revoke copy).
- Update `adminActions()`: approved → `revoke` only (still no extend/reset).
- Audit log (feature 4).
- Tests: revoke path, email, actions matrix, 422 if not approved.

#### 8. Soft client upload checks
**Why:** Reduce obvious bad uploads before they hit the queue.  
**Scope (keep soft — no ML):**
- Client-side before submit: min dimensions (e.g. 200×200), max already 2MB server-side, warn if extreme aspect ratio; show guidance tip (face clearly visible, no group photo).
- Do **not** block server accept beyond current `image|max:2048` unless product insists — warnings first.
- Shared JS used by profile web + keep API unconstrained or return clearer 422 messages only.
- Localized tip strings. No npm package; vanilla JS in existing asset pipeline.

---

## Cross-cutting design rules

| Topic | Decision |
|-------|----------|
| Permissions | Stay on `profile_photo.review`; no role-name checks |
| Notifications | Hub (`NotificationGeneratorService`) + existing mailables; register types in `config/notifications.php` |
| Tenancy | User photo is user-scoped today; do not invent per-church photo tables in this epic. If MULTI_TENANT paths touch admin report, keep BelongsToChurch discipline on any new models |
| i18n | All new strings ar+en; RTL-safe badges/bulk bar |
| Settings | Reminder days / age thresholds → `portal_settings` additive columns (expand-only) |
| Jobs | Prefer queued mail if bulk volume warrants; keep sync acceptable for single actions |

---

## Suggested implementation order (PRs)

| PR | Contents | Depends on |
|----|----------|------------|
| A | **#4 Audit log** on existing approve/reject/extend/reset | — |
| B | **#3 Pending aging** (sort + badges) | — |
| C | **#1 Bulk approve/reject** + audit for bulk | A |
| D | **#6 Pending copy** + **#7 Modal next-focus** | — (can parallel B) |
| E | **#2 Re-upload reminder** scanner + settings | A optional |
| F | **#5 Revoke approval** | A |
| G | **#8 Soft client checks** | — |

Ship A→C first for ops value; D/E for student experience; F/G as follow-ups.

---

## Out of scope (this epic)

- Face detection / OCR / third-party moderation APIs.
- Per-course photo ownership split (defer to tenancy/multi-church product work).
- Changing hard-block rules for `pending` (must stay unlocked while pending).
- Frontend build step / React extraction.

---

## Acceptance checklist (epic done when)

- [ ] Admins can clear a full pending page with bulk approve/reject and see audit rows.
- [ ] Pending queue defaults to oldest-first with visible wait age.
- [ ] Rejected students get a reminder if they do not re-upload within configured days.
- [ ] Pending banner copy states access is not locked.
- [ ] Modal review can advance to the next pending student after a decision.
- [ ] Approved photos can be revoked with a required note + student email + audit.
- [ ] Upload UI warns on tiny/extreme images without breaking API clients.
- [ ] `ProfilePhotoAdminTest` (and new feature tests) green; MySQL migrate gate green.

---

## Open product questions (resolve before/during PR F/G)

1. Reminder default: **2 days** after reject OK?
2. Revoke: delete stored file (like reject) vs keep file visible to admin only?
3. Bulk reject: one shared note for all selected, or require note?
4. Keyboard shortcuts: ship in v1 of #7 or defer to v2?
