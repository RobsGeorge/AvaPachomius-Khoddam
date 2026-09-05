# SPIMS academic parity — gap report & implementation plan

**Cross-repository analysis. This directory contains no code for this repository.**

It compares the **Academic Hub** of this app (AvaPachomius-Khoddam / Khedma) against the academic
surface of the sibling project [`RobsGeorge/spims-edu`](https://github.com/RobsGeorge/spims-edu)
(SPIMS — Coptic Orthodox online school SIS + LMS, `spims-edu.com`), and specifies the work needed
in **spims-edu** to reach full SIS + LMS coverage with a mobile API for student and instructor apps.

> **Scope note for this repo.** Nothing here changes Khedma behaviour, schema, or phase order.
> It is reference documentation only, and does not supersede
> [`docs/khedma-master-plan.md`](../../khedma-master-plan.md) §7. The implementation described in
> [`implementation-plan.md`](implementation-plan.md) is to be carried out **in the spims-edu
> repository**, not here.

## Documents

| Document | Purpose |
|---|---|
| [`gap-analysis.md`](gap-analysis.md) | The evidence-backed gap report: feature-by-feature comparison, severity, and what SPIMS already does better |
| [`implementation-plan.md`](implementation-plan.md) | Phased build plan (S0–S9) for spims-edu: schema, services, permissions, UI, APIs, and per-phase tests |
| [`mobile-api-spec.md`](mobile-api-spec.md) | The `/api/v1` contract for the SPIMS mobile app — student and instructor surfaces, conventions, and error shapes |
| [`verify-gap-claims.sh`](verify-gap-claims.sh) | Re-runnable proof of every factual claim in the gap report (78 checks) |

## Reproducing the evidence

Every count, absence, and code-level claim in the gap report is machine-checked. Clone spims-edu
next to this repo and run the script:

```bash
git clone https://github.com/RobsGeorge/spims-edu.git /tmp/spims-edu
bash docs/plans/spims/verify-gap-claims.sh
```

It asserts the baseline scale of both codebases, the absence of the mobile API, the
`AuthorizeService` scoping defect, the Zoom-bound attendance model, the eleven absent subsystems,
and — importantly — that the areas where **SPIMS is ahead** still exist, so the report cannot
silently invent a gap. It exits non-zero if any claim has gone stale.

## Baselines compared

| | AvaPachomius-Khoddam | spims-edu |
|---|---|---|
| Ref | `main` @ `39e411f` | `main` @ `d764d1e` (2026-08-13) |
| Stack | Laravel 10 · PHP 8.2 · MySQL 8 | Laravel 10 · PHP 8.2 · PostgreSQL 16 · Redis |
| Primary keys | auto-increment, domain-named (`course_id`) | ULID (`id`) |
| Migrations | 165 | 18 |
| Models | 171 | 63 |
| Controllers | 164 | 56 |
| Service classes | 156 | 47 |
| Test files | 220 | 35 |
| Web routes | 600 | 166 |
| Mobile API routes | 57 (`/api/v1`) | **0** |
| Permission keys | 139 | 56 |
| Feature capability switches | 14 | 0 |
| Locales | ar, en (110 lang files) | ar, en, fr (54 lang files) |

## Executive summary

**SPIMS is not a smaller version of the Khedma academic hub — the two systems are strong in
different halves of the problem.** SPIMS is the better *student information system*: it has
programs, prerequisites, academic years and semesters, course offerings, an admissions engine,
enrollment with waitlists and holds, transcripts, GPA, degree audit, credentials with public
verification, and a complete dual-currency finance domain. None of that exists in Khedma.

Khedma is the better *learning and engagement platform*. Against it, SPIMS is missing eleven
subsystems outright and has thin versions of eight more. The three findings that dominate the
report are:

1. **There is no mobile API in SPIMS at all.** `routes/api.php` contains only the default Sanctum
   scaffold (`GET /api/user`). Khedma ships 57 versioned `/api/v1` endpoints behind bearer tokens
   with a documented student feature matrix. Every mobile requirement in this task is therefore
   greenfield, and it is the single largest item of work.

2. **Attendance is not an SIS record in SPIMS.** `attendance_records` is bound to
   `live_sessions` and populated by Zoom import (`source` defaults to `ZOOM_IMPORT`) with a manual
   override. There is no in-person session, no roster marking, no self check-in, no excused state,
   no student-facing history, and no report or export. Khedma treats attendance as a first-class
   record with its own policy table, optimistic-locking (`lock_version`), guardian check-in, and
   six reporting routes.

3. **Authorization in SPIMS is role-level, not resource-scoped.** `AuthorizeService::authorize()`
   accepts a `$resource` argument and never reads it, and no call site passes one. The `O` ("own")
   level in `config/permissions.php` is therefore documentation rather than enforcement: any user
   holding the Instructor role can drive `/admin/offerings/{any}/gradebook/lock` for an offering
   they do not teach. The Teach hub scopes correctly via `TeachAccessService`; the `/admin/*`
   routes that wrap the same services do not. This must be fixed before a mobile instructor API is
   exposed, because an API multiplies the reachable surface.

Missing outright: the mobile API, team projects, live quiz, feedback surveys, events and
reservations, per-course graduation criteria, communications logging, staff-editable email
templates, module-level student assessments and instructor notes, student-facing attendance
history and reporting, and realtime broadcasting.

Thin: attendance itself (see finding 2), announcements (create-only, no publish workflow or
fan-out), assignments (no dashboard, reminders, or resubmission), roster (read-only, no export, no
birthdays), notifications (one `notify_email` boolean, no per-event or per-channel preferences and
no reminders), certificates (issued manually, no per-course templates), curriculum (weeks and items,
but no reusable module catalog or lecture materials), and exam integrity (focus-loss counting, but
no proctor event log or termination).

Several of these sit in SPIMS's own [`PARKING-LOT.md`](https://github.com/RobsGeorge/spims-edu/blob/main/PARKING-LOT.md)
as explicitly deferred — a JSON API, WebSockets, mobile apps, WhatsApp, an excused attendance
state, and hard proctoring. The plan promotes them deliberately rather than quietly, and calls out
each promotion so the SPIMS phase owner can accept or reject it.

The plan is sequenced in ten phases. **S0–S2 are prerequisites** (authorization scoping, API
foundation, notification and communications spine) and everything else depends on them. **S3–S7**
port the missing LMS and SIS subsystems, each landing its web UI, its API slice, and its tests
together. **S8–S9** complete the instructor mobile surface and realtime. Full detail, including
table shapes and per-phase acceptance criteria, is in [`implementation-plan.md`](implementation-plan.md).
