# Plan: fix Portal UAT failures

**Status:** Plan only — do not implement in this PR.  
**Source:** Cloud-agent pass of the Portal UAT checklist (Claude artifact `50b8a1a2-9463-4573-9820-4654b5bdebbf`) on 2026-08-14, local Tenant Zero.  
**Results log:** `/opt/cursor/artifacts/uat_public_access_plan_results.md` (agent run).  
**Constraints:** Additive schema only; Policies + permission keys; localize ar+en; tests with every change; tenant isolation suite must stay green. In-phase bugfixes (existing auth / curriculum / RBAC). Nothing here is a new master-plan phase.

This plan covers **confirmed failures and security findings** from that pass. Untouched UAT sections (photo gate, applications, exams, events, …) stay out of scope until the lecture-create path works.

---

## Verdict table

| UAT ID | Verdict | Priority | Treat as |
|---|---|---|---|
| RT-I18N-#2, #5 | **FAIL** | P0 | Bug — locale is session-only and logout wipes it |
| RT-ISO-#7 `/users`, `/users/{id}` | **FAIL** (authz) | P0 | Bug — stub `UserController` is `auth`-only and returns 200 empty |
| RT-PWD-#3 English token string | **FAIL** (i18n) | P0 | Bug — no `lang/{ar,en}/passwords.php` |
| RT-OTP-#3 English `digits:6` | **FAIL** (i18n) | P0 | Bug — Laravel default `validation.digits` + weak OTP input |
| RT-CURR-#3 | **FAIL** (UX / blocked path) | P1 | Bug — lecture form is hidden until a session exists; nearby exams `+` is what testers click |
| RT-DASH-#2 | **Inconclusive** | P2 | Product — multi-course admin hits picker; empty review queue hides the admin card |
| RT-CTX-#6 System Settings | **Not a course-scope bug** | — | Church/service nav is permission-based; course manage URLs already 403 (ISO-#1) |
| Course title English in `ar` | **Seed data** | P2 | Sandbox `title` is English; `localizedTitle()` is unused on some chrome |

Do **not** “fix” RT-CTX-#6 by hiding System Settings whenever the current course role is student. `admin2` is also service-admin (`qa:course-testers`). `NavigationHub::hasSystem()` is correct if any system link is permitted. Course-scoped denial is already proven on `/courses/6/grades/manage` and `/courses/6/curriculum/manage`.

---

## P0-1 — Persist UI locale across logout / login

**UAT:** RT-I18N-#2, RT-I18N-#5. **Spec:** UC-AUTH-12 (`docs/product/use-cases/auth-and-registration.md`).

### Root cause

`LocaleController::switch` only writes `session('locale')`. `SetLocale` reads only that session key. `LoginController::logout` calls `$request->session()->invalidate()`, so the choice dies.

`user.communication_locale` already exists (email/WhatsApp). Account center and notification settings treat it as **mail** locale, not chrome locale. Do not overload it silently.

### Design

1. Add nullable `user.ui_locale` (`ar`/`en`) — **additive** column.  
   Guest / pre-login: keep a long-lived cookie (`ui_locale`, 1 year, `SameSite=Lax`, not HttpOnly so the existing theme pattern can stay consistent — or HttpOnly if only the server reads it; prefer **HttpOnly cookie + session**).
2. `LocaleController::switch`:
   - validate against `config('translation.supported_locales')`;
   - `session(['locale' => $locale])`;
   - queue cookie;
   - if `auth()->check()`, persist `ui_locale` (and optionally leave `communication_locale` alone unless the user is on the notifications form).
3. `SetLocale` resolution order:
   1. `session('locale')` if valid;
   2. authenticated `user.ui_locale`;
   3. cookie;
   4. `config('app.locale')` (`ar`).
4. After successful login, if the user has `ui_locale`, put it back on the new session (logout already destroyed the old one).
5. Account appearance section: locale links already hit `locale.switch` — they pick up persistence for free.

### Files

- New migration `*_add_ui_locale_to_user_table.php`
- `app/Http/Controllers/LocaleController.php`
- `app/Http/Middleware/SetLocale.php`
- `app/Http/Controllers/Auth/LoginController.php` (post-login session seed)
- `app/Models/User.php` fillable/casts
- `lang/ar/auth.php` + `lang/en/auth.php` only if new strings appear

### Tests

- Feature: switch to `en` → logout → login → `app()->getLocale() === 'en'` and dashboard shows English chrome (not only course titles).
- Guest: switch to `en` without an account → cookie survives a new session on `/login`.
- Invalid locale still 404.
- Extend `tests/Feature/CourseContextTest` locale assertions or add `tests/Feature/Auth/LocalePersistenceTest.php`.
- Tenant isolation suite unchanged (column is on `user`, not tenant-scoped).

---

## P0-2 — Close the stub `/users` resource

**UAT:** RT-ISO-#7 (`GET /users` → 200 blank), RT-ISO-#6 (`GET /users/27` → 200 blank).

### Root cause

```378:378:routes/web.php
    Route::resource('users', UserController::class);
```

This sits in the `auth` group (`routes/web.php` ~145). `UserController` methods are empty stubs — they return an empty 200, not 403. Authorization is not via a Policy.

Real user admin lives at `admin/users/unverified` (`admin` + controller), and People hub is permission-gated.

### Design

1. **Remove** `Route::resource('users', UserController::class)` (or restrict to zero verbs). Confirm no `route('users.*')` callers in views/mail.
2. If any legitimate consumer exists, replace with an explicit named route behind `permission:…` + a Policy. Do **not** revive the stub.
3. Keep `UserController.php` only if still referenced; otherwise delete it in the same PR (file delete is fine; it is not a schema contraction).

### Tests

- Guest → `/users` and `/users/1` → redirect login (not 200).
- Approved student → 403 or 404 (never 200 empty).
- Instructor / course admin → same unless a real permission is granted.
- Grep-guard or smoke: no `users.index` / `users.show` named routes unless authorized.

Add to `tests/Feature/UseCases/AuthorizationMatrixTest.php` (already covers guest + student privileged GETs).

---

## P0-3 — Localize Laravel password-reset and validation lines

**UAT:** RT-PWD-#3 (`This password reset token is invalid.`), RT-OTP-#3 (`The otp field must be 6 digits`).

### Root cause

There is **no** `lang/ar/passwords.php` or `lang/{ar,en}/validation.php`. The broker uses vendor English `passwords.token`. `OTPController::verify` uses `'otp' => 'required|digits:6'`, which emits `validation.digits` from the English vendor file when the validator runs (and when locale is `en`). Hard-coded Arabic on the *invalid code* path is fine; the **format** path is not localized.

`LocaleKeyParityTest` + `TenantIsolationTest::test_language_files_have_locale_parity` require **ar and en counterparts** for every new file.

### Design

1. Add full-key-parity files (copy Laravel 10 vendor keys, translate ar):
   - `lang/en/passwords.php`, `lang/ar/passwords.php`
   - `lang/en/validation.php`, `lang/ar/validation.php`
2. Attribute names: `lang/{ar,en}/validation.php` `attributes.otp` → «رمز التحقق» / «verification code» so the message is not “the otp field…”.
3. OTP blade (`resources/views/auth/otp.blade.php`):
   - `inputmode="numeric"`, `pattern="[0-9]{6}"`, `minlength="6"`, `maxlength="6"`, `autocomplete="one-time-code"`;
   - `title="{{ __('auth.otp_digits_hint') }}"` (new ar+en keys) so HTML5 is Arabic-first.
4. Prefer Laravel validation errors in `@error('otp')` rather than a raw English tooltip.

Do **not** change reset semantics (still generic, no enumeration).

### Tests

- `PasswordResetTest`: used/invalid token asserts `__('passwords.token')` in `ar` and `en`.
- `OtpVerificationTest`: 5-digit submit asserts Arabic `validation.digits` (or `auth.otp_digits_hint`) when locale is `ar`.
- `LocaleKeyParityTest` stays empty-baseline.

---

## P1 — Lecture create without a session (unblocks the rest of UAT)

**UAT:** RT-CURR-#3. Tester created a module, then clicked a `+` and landed on `/exams/dashboard`.

### Root cause (two stacked issues)

1. `LectureController@store` **requires** `session_id` (`exists:session,session_id`). The inline add form in `course-content/admin.blade.php` is rendered **only inside an existing session card**. A brand-new module with zero sessions has **no lecture form**.
2. The only prominent `+` on that empty module is:

```328:330:resources/views/course-content/admin.blade.php
                    <a href="{{ route('exams.dashboard') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg"></i> {{ __('pages.manage_exams') }}
                    </a>
```

That is exams, not lectures. Easy to mis-click; also a dead-end for RT-CURR-#3.

### Design

1. Empty-module state: if `$module->courseSessions` is empty, show a lecture block with:
   - copy: «لا توجد جلسات — أنشئ جلسة أو أضف محاضرة غير مربوطة»;
   - primary: link to `sessions.create` (or inline session create) for this course/module;
   - secondary: **orphan lecture form** (reuse `lecture-add-form`) with `session_id` optional.
2. Relax `lectures.store` validation: `session_id` → `nullable|exists:session,session_id`. Keep the existing “session must belong to module/course” check when present (`LectureController` already has that).
3. Relabel the exams control so it cannot be read as “add lectures” (`pages.manage_exams` is already the key — verify the Arabic string; add `aria-label`). Do not use a lone `+` icon for exams.
4. Do **not** invent a freeform builder (T10d / parking lot).

### Tests

- Instructor with `curriculum.manage` (or existing course permission): create module → POST lecture with only `module_id` + `course_id` → 302 + lecture row → student `/curriculum` sees the title.
- POST lecture with a session from **another** course → 422.
- Student POST `lectures.store` → 403.
- Feature assertion: curriculum manage HTML for a session-less module contains `lectures.store` and does not make the exams `+` the only plus control.

---

## P2 — Dashboard for multi-course admins (product, optional)

**UAT:** RT-DASH-#2. `qa.matrix.admin1` (admin on four courses) was sent to the course picker, not an “admin queue” panel.

### Root cause

`CourseContextService::requiresCourseContext` is true for every non-superadmin. Four courses ⇒ picker. `DashboardService::reviewQueueCard` only renders when there is a **pending** application count > 0. Sandbox had none, so even after a pick there is no admin card.

### Options (pick one in the implementing PR; do not do both)

- **A (recommended):** Keep picker. After a course is selected, always show a staff “focus” card (roster / applications / attendance) even when counts are zero (empty state, not omit). Matches F-01 “persona dashboard”.
- **B:** Allow course-admins to open `/dashboard` with no course context and show a **cross-course** review queue (still scoped to `accessibleCourses`). Bigger change; document in the PR.

Ship A unless product asks for B. Add a Feature test: admin with zero pending applications still sees the review-queue empty state after context is set.

---

## Implementation order

1. **P0-2** `/users` stub — smallest, security, no migration.  
2. **P0-3** `passwords` + `validation` + OTP input — no migration.  
3. **P0-1** `ui_locale` + cookie/session — one additive migration.  
4. **P1** lecture empty-state + optional `session_id`.  
5. **P2** only if product confirms A or B.

Each item is its own commit (or stacked PRs). Do not batch with unrelated work.

---

## Explicit non-goals

- Re-running the full 303-case UAT in this plan’s implementing PRs (re-test only the IDs above).
- Photo gate, course applications, exams sitting, events, live quiz, surveys, announcements.
- Hiding church System Settings based on current course role.
- Dropping/renaming columns.
- Frontend build / npm.

---

## Re-test checklist (manual, after code)

| ID | Pass |
|---|---|
| RT-I18N-#2 / #5 | Switch to English → logout → login → chrome stays English |
| RT-ISO-#7 `/users` | Student gets 403/404, never 200 |
| RT-PWD-#3 | Reused reset link shows Arabic `passwords.token` when locale is `ar` |
| RT-OTP-#3 | 5-digit code: Arabic validation, stays on OTP |
| RT-CURR-#3 | New module → add lecture (no session) → student curriculum shows it |
| RT-DASH-#2 | Only if P2 ships |

Automated: `php artisan test --testsuite=Feature` filters for Auth / Localization / UseCases / Curriculum; then `--testsuite=Tenancy`.
