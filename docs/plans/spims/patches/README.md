# Ready-to-apply patches for spims-edu

Two defect fixes from [`gap-analysis.md`](../gap-analysis.md), implemented and verified against
[`RobsGeorge/spims-edu`](https://github.com/RobsGeorge/spims-edu) `main` @ `d764d1e`.

They are delivered as patches rather than a pull request because this agent's git credentials are
scoped to this repository — pushing to spims-edu returns
`Permission to RobsGeorge/spims-edu.git denied to cursor[bot]`.

## Applying

```bash
git clone https://github.com/RobsGeorge/spims-edu.git
cd spims-edu
git checkout -b fix/submission-integrity-and-notify-email
git am /path/to/docs/plans/spims/patches/*.patch
composer install
cp .env.example .env && php artisan key:generate
php artisan test --compact
```

Both apply cleanly to `main` with `git am` and need no conflict resolution.

## What each patch does

### `0001` — notifications honour `notify_email` (gap G-12)

`NotificationService::notify()` always called `sendEmailChannel()` when `$alsoEmail` was true and
never read `users.notify_email`, so the toggle in Settings was inert: a user who opted out still
received every transactional email plus an `EMAIL` channel row.

The fix gates the mail channel on the preference, defaulting to opted-in so users predating the
column behave as before (matching the column default). Callers that already pass `alsoEmail: false`
are unaffected.

One file changed plus a test. No schema change.

### `0002` — resubmissions stop destroying the previous attempt (gap G-17)

`AssignmentService::submit()` used `updateOrCreate` keyed on `(assignment_id, student_id)`, so a
second submission overwrote the first in place. Two distinct consequences, the second worse than
the first:

1. The earlier text, file URL, timestamp and late flag were lost, with no history row and nothing in
   the audit trail recording what was replaced.
2. The grade columns were **not** reset, so an instructor's score stayed attached to content they
   had never seen. A student could resubmit after grading and keep the mark.

The unique `(assignment_id, student_id)` index is deliberately left intact — the existing row stays
canonical and prior states are archived **additively** into a new `assignment_submission_versions`
table, so the change respects SPIMS's additive-migrations rule. Resubmitting now bumps `attempt_no`,
snapshots the previous attempt together with its grade, and clears the stale grade so the work is
re-marked. `assignments.allow_resubmission` (default `true`, preserving today's behaviour) lets an
instructor close resubmission, in which case a second attempt is refused with a localized message.

Audit distinguishes `assignments.resubmit` from `assignments.submit`.

## Verification

Full output in [`../evidence/spims-fixes-evidence.log`](../evidence/spims-fixes-evidence.log).

| Check | Result |
|---|---|
| Upstream baseline before any change | 124 passed |
| New tests against the **original** service code | 5 failed, 2 passed — the failures are the bugs |
| New tests against the fixed code | 7 passed |
| Full suite after both patches | **131 passed, 587 assertions** |
| Applied to a clean clone of `main` via `git am` | clean, then 131 passed |
| `pint --test` on the 8 changed PHP files | pass |
| PostgreSQL 16 `migrate:fresh --seed` | 29 steps completed |
| Migration rollback then re-apply on PostgreSQL | both clean |

The middle row is the one that matters. Run against the code as it ships today, the new tests
report exactly the two defects:

```
Failed asserting that 88.0 is null.          # graded score survived onto content never seen
-'only draft' +'sneaky replacement'          # original submission destroyed outright
Failed asserting that true is false.         # EMAIL row written for a user who opted out
```

`lang/*/assessment.php` is excluded from the pint check because those files use `array()` syntax and
two-space indentation at `HEAD`; the added keys match the surrounding style rather than reformatting
files this change does not own.

## Scope

These are the two standalone defects. They do **not** depend on phase S0 (resource-scoped
authorization) and can land before it. The wider work — including the `resubmission_deadline`
window, the staff assignment dashboard, reminders, and the full per-event notification preference
model — is specified in [`../implementation-plan.md`](../implementation-plan.md) under S2 and S5.
