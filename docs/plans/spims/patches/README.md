# Ready-to-apply patches for spims-edu

Two defect fixes plus **phases S0 and S1** from [`implementation-plan.md`](../implementation-plan.md),
implemented and verified against
[`RobsGeorge/spims-edu`](https://github.com/RobsGeorge/spims-edu) `main` @ `d764d1e`.

Apply in order — `0003` depends on the fixture helper conventions introduced alongside it, and `0004`
assumes `0003`'s `sanctum` guard-adjacent config is present. The suite counts below assume all four.

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

### `0004` — `/api/v1` foundation (gap G-01 part 1, phase S1)

Before this, `routes/api.php` had one route: the default Sanctum scaffold. Everything downstream (S6
student API, S8 instructor API) depends on this shape existing once, before it multiplies across 60+
endpoints.

```
POST /api/v1/login  { email, password, device_name? } -> { data: { token, token_type, user } }
POST /api/v1/logout                                    -> 204, revokes only this token
GET  /api/v1/me                                        -> the caller's profile + roles
GET  /api/v1/branding                                  -> public, safe pre-login
```

**The plan's own assumption about auth was wrong, and was caught before writing code.**
`implementation-plan.md` originally said login "mirrors the web OTP lifecycle" — that's how Khedma
works, not spims-edu. `AuthService::login()` has always been email + password; OTP exists only for
email verification and password reset. Reading `LoginController` and `AuthService` directly before
implementing caught this.

`AuthService` gains `issueApiToken()`/`revokeApiToken()`, sharing the existing credential-and-status
check with the web login rather than forking it. `issueApiToken()` deliberately does not call
`Auth::login()` — that needs a started session, which the stateless `api` middleware group never
provides. `config/auth.php` gains the `sanctum` guard, which was missing entirely: `auth:sanctum`
would have thrown `Auth guard [sanctum] is not defined` for every request, including the pre-existing
`/api/user` scaffold, which no test exercises and was therefore never known to be broken.

One error shape for every `/api/v1` failure — `{ message, code, errors? }` — resolved through two
non-obvious pieces of Laravel behavior, each confirmed against framework source rather than assumed:
`App\Exceptions\AuthorizationException` defines its own `render()`, which Laravel checks *before* any
`Handler::renderable()` callback runs, so it is special-cased directly instead of registered
generically. Locale (`Accept-Language` → stored preference → `en`) is resolved directly at the point
each translated string is produced, not only via a middleware side-effect — Laravel's global
middleware-priority list runs auth checks before ordinary route middleware regardless of registration
order, so an unauthenticated request's 401 can fire before a locale-setting middleware ever runs.
Caught by a failing test during development, not discovered later.

**Independently reviewed by a second agent** with no memory of building it, hunting specifically for
bugs in the exception-handling and locale logic. It verified three of the commit's own technical
claims against framework source (Laravel's `AuthorizationException` render precedence, the
middleware-priority reordering, Sanctum's per-instance `RequestGuard` user-caching) and found two real
gaps, both fixed in the same commit:

- **`codeForStatus()` had no 401/403 mapping.** Harmless for this app's own
  `AuthorizationException`, but Laravel's own `Illuminate\Auth\Access\AuthorizationException` — what
  Policies and `Gate::authorize()` throw — has no `render()` of its own and *does* reach that method.
  Reproduced: reverting the mapping turns `code: "FORBIDDEN"` into `code: "ERROR"` for an identical
  403. Two exception types, one status, two codes — the exact thing "one shape" exists to prevent.
- **`OpenApiCoverageTest` filtered on route *name*, not URI.** A route under `/api/v1/*` with an
  unrelated name passed the coverage check while being live, reachable, and undocumented. Reproduced
  exactly as described, then fixed to filter on `$route->uri()`.

One finding was deliberately left unfixed and is flagged instead: `AuthorizeService::authorize(null,
...)` throws a 403-only exception for what is semantically a 401 — pre-existing behavior in code S0
shipped untouched, not reachable via any S1 route today, and its own blast radius belongs in its own
dedicated patch. See `implementation-plan.md`'s S1 section for the full writeup.

## Verification

Full output in [`../evidence/spims-fixes-evidence.log`](../evidence/spims-fixes-evidence.log)
(`0001`–`0003`) and [`../evidence/spims-s1-evidence.log`](../evidence/spims-s1-evidence.log) (`0004`).

| Check | Result |
|---|---|
| Upstream baseline before any change | 124 passed |
| Defect tests against the **original** service code | 5 failed, 2 passed — the failures are the bugs |
| Full suite after `0001` + `0002` | 131 passed, 587 assertions |
| `ResourceScopeTest` against the **pre-S0** authorizer | 4 failed, 4 passed |
| Full suite after `0001`–`0003` | 139 passed, 608 assertions |
| Two review-found S1 bugs, reproduced against the pre-fix code | both confirmed (see below) |
| Full suite after all four | **176 passed, 712 assertions** |
| All four applied to a clean clone of `main` via `git am` | clean, then 176 passed |
| `pint --test` on the changed PHP files | pass |
| PostgreSQL 16 `migrate:fresh --seed` | 29 steps completed |
| Migration rollback then re-apply on PostgreSQL | both clean |

The "against the original/pre-fix code" rows are the ones that matter — a regression test that was
never red proves nothing. Run against the code as it ships today, or with a specific fix reverted,
the tests report exactly the problems:

```
Failed asserting that 88.0 is null.          # graded score survived onto content never seen
-'only draft' +'sneaky replacement'          # original submission destroyed outright
Failed asserting that true is false.         # EMAIL row written for a user who opted out

Expected [gradebook.addComponent] to be denied across offerings, but it was allowed.
Expected [ta.addComponent cross-offering] to be denied across offerings, but it was allowed.

status=403 body={"message":"nope","code":"ERROR"}     # should be "FORBIDDEN"
```

`lang/*/assessment.php` is excluded from the pint check because those files use `array()` syntax and
two-space indentation at `HEAD`; the added keys match the surrounding style rather than reformatting
files this change does not own.

## Scope

`0001` and `0002` are standalone defects and do not depend on S0. `0003` **is** S0. `0004` **is** S1
and depends on `0003` (the `sanctum` guard it adds sits in the same config file S0 didn't touch, but
`0004` was built and tested on top of `0003` throughout, not independently).

Deliberately not included here: the `resubmission_deadline` window, the staff assignment dashboard,
reminders, the full per-event notification preference model, and every S6/S8 endpoint beyond `login`,
`logout`, `me`, and `branding` — all specified under S2, S5, S6, and S8 in
[`../implementation-plan.md`](../implementation-plan.md). The build order for what remains is in
[`../execution-order.md`](../execution-order.md).

## Two things S0 changes for everything built afterwards

1. **A new offering-owned permission key must be added to `config/permission_scopes.php`**, or it
   will be enforced at role level only — the exact bug S0 fixes.
2. **A new offering-owned model must be added to `ResourceScopeResolver::offeringIdsFor()`**, or it
   resolves to no offering and is treated as out of scope. That fails safe, but presents as an
   unexplained 403.
