# Ready-to-apply patches for spims-edu

Two defect fixes plus **phase S0** from [`implementation-plan.md`](../implementation-plan.md),
implemented and verified against
[`RobsGeorge/spims-edu`](https://github.com/RobsGeorge/spims-edu) `main` @ `d764d1e`.

Apply in order — `0003` depends on the fixture helper conventions introduced alongside it, and the
suite counts below assume all three.

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

### `0003` — resource-scoped authorization (gap G-02, phase S0)

`AuthorizeService::authorize()` accepted a `$resource` argument and never read it, and no call site
passed one. Every level containing `O` collapsed to a plain allow, so "own" was documentation rather
than enforcement. The Teach hub and course player scoped by hand; the `/admin/*` routes wrapping the
same services did not. **Any Instructor could lock, submit or reopen grades, add gradebook
components, author assessments, import attendance or moderate discussions for any offering in the
school.**

What "own" means depends on the role, which a level alone cannot express — for a student it is "on
my own behalf", for an instructor it is "an offering I staff". `config/permission_scopes.php` makes
that explicit: 14 offering-scoped keys, and the roles whose grants those keys confine. Self-scoped
keys (`profile.edit_own`, `finance.pay`, `assignments.submit`, `assessments.take`,
`enrollment.register`, `discussions.post`) are deliberately absent and keep working without a
resource.

- `ResourceScopeResolver` maps a resource to its offering(s) and checks `offering_staff`. Resolution
  is explicit per model, so a new offering-owned model must opt in rather than silently inherit
  access.
- `AuthorizeService` resolves the *granting* role: an unscoped grant from any role wins outright, so
  an Academic Admin who also teaches is not confined. A scoped grant on a scoped key requires a
  resource and **fails closed** without one.
- `RequirePermission` hands the authorizer the route-bound model, fixing every `/admin/*` route at
  once with no changes to `routes/web.php`.
- 20 service call sites thread their resource through.

Six existing fixtures created instructors who held the role but were staffed on nothing — which is
why they passed before. They now staff explicitly through a shared `TestCase::staffOffering()`
helper. That is the corrected setup, not a workaround.

`ResourceScopeTest` is the invariant suite: 11 cross-offering operations denied, the same operations
allowed on a staffed offering, admins unaffected, TA denied the lock even on their own offering,
fail-closed without a resource, and self-scoped permissions still working.

## Verification

Full output in [`../evidence/spims-fixes-evidence.log`](../evidence/spims-fixes-evidence.log).

| Check | Result |
|---|---|
| Upstream baseline before any change | 124 passed |
| Defect tests against the **original** service code | 5 failed, 2 passed — the failures are the bugs |
| Full suite after `0001` + `0002` | 131 passed, 587 assertions |
| `ResourceScopeTest` against the **pre-S0** authorizer | 4 failed, 4 passed |
| Full suite after all three | **139 passed, 608 assertions** |
| All three applied to a clean clone of `main` via `git am` | clean, then 139 passed |
| `pint --test` on the changed PHP files | pass |
| PostgreSQL 16 `migrate:fresh --seed` | 29 steps completed |
| Migration rollback then re-apply on PostgreSQL | both clean |

The "against the original code" rows are the ones that matter — a regression test that was never red
proves nothing. Run against the code as it ships today, the new tests report exactly the three
problems:

```
Failed asserting that 88.0 is null.          # graded score survived onto content never seen
-'only draft' +'sneaky replacement'          # original submission destroyed outright
Failed asserting that true is false.         # EMAIL row written for a user who opted out

Expected [gradebook.addComponent] to be denied across offerings, but it was allowed.
Expected [ta.addComponent cross-offering] to be denied across offerings, but it was allowed.
```

`lang/*/assessment.php` is excluded from the pint check because those files use `array()` syntax and
two-space indentation at `HEAD`; the added keys match the surrounding style rather than reformatting
files this change does not own.

## Scope

`0001` and `0002` are standalone defects and do not depend on S0. `0003` **is** S0, the prerequisite
for every remaining phase.

Deliberately not included here: the `resubmission_deadline` window, the staff assignment dashboard,
reminders, and the full per-event notification preference model — all specified under S2 and S5 in
[`../implementation-plan.md`](../implementation-plan.md). The build order for what remains is in
[`../execution-order.md`](../execution-order.md).

## Two things S0 changes for everything built afterwards

1. **A new offering-owned permission key must be added to `config/permission_scopes.php`**, or it
   will be enforced at role level only — the exact bug S0 fixes.
2. **A new offering-owned model must be added to `ResourceScopeResolver::offeringIdsFor()`**, or it
   resolves to no offering and is treated as out of scope. That fails safe, but presents as an
   unexplained 403.
