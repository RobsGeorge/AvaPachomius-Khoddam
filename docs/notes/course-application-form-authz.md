# Course application form-builder — authorization note

**For:** the owner of course-applications / RBAC.
**Context:** `CourseApplicationReviewTest > admin can build form and enable it` was failing
with **403** after the permission-based authz (T3 authz-debt) was completed. This documents
the root cause and the fix applied, plus the open design decision.

## How form-builder access works today

The form-builder routes live in the generic admin group:

```php
// routes/web.php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/courses/{course}/application-form', [CourseApplicationFormController::class, 'edit'])
        ->name('courses.application-form.edit');
    // …update / steps / fields …
});
```

Access is decided entirely by the `admin` middleware (`AdminMiddleware`) — the controller
(`CourseApplicationFormController`) performs **no per-course authorization**. A request passes when
the user is:

1. a superadmin, **or**
2. holder of a **system** permission in `AdminMiddleware::SYSTEM_PERMS` (which includes
   `course_application.form_builder`), **or**
3. a course admin — `isAdmin()` → `role.manage`.

Crucially, **`course_application.form_builder` is treated as a *system-level* permission** in two
places:

- `AdminMiddleware::SYSTEM_PERMS`
- `RequirePermission::isSystemPermission()` (so even `permission:course_application.form_builder`
  resolves via `canInSystem`, never `canInCourse`).

## Why the test failed

The test granted `course_application.form_builder` as a **course** permission and gave the admin no
`role.manage`:

```php
$adminRole = $this->courseRoleWithPermissions($course, 'admin', ['course_application.form_builder']);
$this->assignCourseRole($admin, $course, $adminRole);
```

That matches **none** of the three access paths above (it's a course-scoped grant of a
system-checked permission, and there's no `role.manage`), so `AdminMiddleware` returned 403. This is
**test-setup debt** exposed once role-name authz was fully removed — not a production regression.

## Fix applied (in this PR)

Grant `course_application.form_builder` via a **system role**, matching how the app checks it:

```php
$formAdminRole = Role::create([... 'slug' => 'application-form-admin', 'is_template' => false]);
$formAdminRole->permissions()->sync(Permission::where('key', 'course_application.form_builder')->pluck('permission_id'));
UserSystemRole::create(['user_id' => $admin->user_id, 'role_id' => $formAdminRole->role_id]);
```

The test now verifies the intended behavior (the `form_builder` permission grants form-building) at
the level the app enforces it. No application code changes.

## Open decision for the owner

Is application-form building meant to be **system-level** (platform/registration admins build the
form for a course) or **course-level** (a course's own admin builds its form)?

- **System-level (current, and what this PR assumes).** No change needed.
- **Course-level (if desired).** Then: (1) drop `course_application.form_builder` from
  `AdminMiddleware::SYSTEM_PERMS` and `RequirePermission::isSystemPermission()`, (2) gate the
  `courses.application-form.*` routes with a course-contextual `permission:course_application.form_builder`
  (resolves `canInCourse` via the `{course}` param), and (3) add a per-course `authorize`/`canInCourse`
  check in `CourseApplicationFormController` so an admin can only edit **their** course's form. This is
  a deliberate behavior change and should be its own PR.
