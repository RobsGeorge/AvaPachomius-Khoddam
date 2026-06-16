# UI & UX — Exact Instructions

The app is **Arabic-first / RTL**, Bootstrap 5 + Bootstrap Icons, Alpine.js (`x-show`, `@click`),
Blade layouts (`layouts/app.blade.php`, `layouts/navigation.blade.php`), with an existing theme
toggle (`ThemeController`) and locale switcher (`config/translation.php`, `SetLocale`). **Match
these conventions** — every new string goes through `__()` / the `translations` table; every new
screen is RTL-correct and theme-aware.

## Global behaviours

- **Per-subdomain branding** (P5): `IdentifySubsidiary` shares `$currentSubsidiary`. The navbar
  brand (`layouts/navigation.blade.php` line ~5) shows the subsidiary logo (`settings.logo`) +
  `name` instead of the static `__('app.name')`. Theme palette + default locale come from
  `settings`. Fall back to platform defaults when unset.
- **Capability-driven nav** (P2): replace the hardcoded `@if(hasAnyRole(...))` feature gates with
  `@capability('exams')`, `@capability('attendance')`, `@capability('curriculum')`,
  `@capability('reporting')`. "Manage vs view" links additionally gated by `@can('exam.grade')` etc.
  A subsidiary without a capability simply never renders that nav item.
- **403 / 404 copy** (Arabic): capability-missing → 404 ("هذه الخدمة غير متاحة في هذا الفرع");
  permission-missing → 403 (reuse `pages.not_authorized`); non-member → 403 with a link to
  `subsidiary.switch`.

## Subsidiary switcher (P4)

- A dropdown in the navbar user menu: "التبديل بين الجهات / Switch entity".
- Lists `auth()->user()->subsidiaries` (active memberships); each links to that subdomain.
- Shows the current subsidiary as active. Hidden if the user belongs to only one.
- Superadmins also see a "Console" link to `admin.<domain>`.

## Console (superadmin host `admin.<domain>`)

A distinct console shell (can reuse `layouts/app` with a console sidebar). Screens:

### 1. Subsidiaries list — `console.subsidiaries.index`
Table: logo, name, slug (subdomain), status badge (active/suspended/archived), capability count,
member count, created. Row actions: open, edit, suspend/restore, archive. Primary button:
**"+ إنشاء جهة جديدة / Create subsidiary"**.

### 2. Create wizard — `console.subsidiaries.create` (multi-step, Alpine)
1. **Identity** — name (req), slug (req, unique, lowercase/kebab, live "→ slug.<domain>" preview),
   optional custom domain, logo upload (`intervention/image`), theme/locale defaults.
2. **Capabilities** — checkbox list from `config('capabilities')`; each enabled capability expands
   its config form (e.g. attendance: mode select strict/lenient/none, min %, penalty toggle;
   curriculum: modules on/off, recurring-years toggle).
3. **Admins** — pick existing users (search by name/email) and/or invite by email; these become the
   subsidiary's admins.
4. **Review** — summary; **Create** posts to `console.subsidiaries.store` →
   `TenantProvisioningService`. Success → toast "الجهة جاهزة على slug.<domain>" + link.

### 3. Subsidiary overview — `console.subsidiaries.show`
Cards: identity/branding (edit), capabilities (edit), members (manage), roles & permissions
(manage), recent audit (filtered).

### 4. Capabilities editor — `PUT console.capabilities.update`
Per capability: enable toggle + inline config form. Saving busts the caps cache. Disabling a
capability warns it will hide features/routes for that subsidiary.

### 5. Members — `console.members.*`
Table: name, email, role(s) in this subsidiary, membership status (active/invited/suspended),
joined. Actions: add existing user, invite by email, change role, suspend/remove. Invite uses the
existing set-password flow.

### 6. Roles & permissions matrix — `console.roles.*` (the key screen)
- Grid: **rows = roles** of this subsidiary, **columns = permissions grouped by capability**.
- Only permissions whose capability is **enabled** appear (platform-level perms shown to superadmin
  only). Checkbox per cell = grant.
- Create-role inline (slug + display name + base template). `is_system` roles: name editable, not
  deletable; the admin role's `role.manage` cell is locked (lockout protection).
- Save → policy check + `permissions_version` bump + audit. Show "saved" inline, no full reload.
- RTL: roles column pinned on the right; capability groups as collapsible column sets.

### 7. Branding editor — `console.subsidiaries.edit`
Logo upload + preview, theme palette (primary/accent colour pickers writing `settings`), default
locale select. Live preview of the navbar.

### 8. Audit — `console.audit.index`
Reuse `superadmin/audit`; add a subsidiary filter.

## Subsidiary self-service (in-subdomain `manage.*`)

Same Members / Roles-matrix / Capability-**config** / Branding screens, scoped to
`Tenancy::current()`, visible to users with `role.manage` / `subsidiary.members.manage`. **Cannot**
enable new capabilities (superadmin-only) — only configure enabled ones. Platform-level permission
columns never appear. All actions pass `RolePermissionPolicy`.

## Product-shape UX per subsidiary type

- **Reporting-only**: nav shows only Home + Reports; no exams/attendance/curriculum items; dashboard
  is a reports landing. Roles like `reporter` see read-only dashboards.
- **No-exam / Service (خدمة)**: exams hidden; attendance present but lenient; roles `servant/خادم`,
  `served/مخدوم` instead of student. Attendance screens read the lenient config (no penalty styling).
- **Recurring years without modules**: curriculum screen shows years/sessions but the modules panel
  is hidden when `curriculum.config.modules=false`.

## Invitation UX (P5)

- Invite by email → if new user: create unverified + `subsidiary_user(status=invited)` and send the
  existing set-password email; if existing email: add membership only (no new account) and notify.
- Invited rows show an "invited" badge until first login flips them to active.
- Message clearly when an existing person is added vs a new account created.

## Accessibility / consistency checklist (every new screen)

- All copy via `__()`; add keys to `lang` + `translations`. Provide AR + EN.
- RTL layout correct (use existing `me-`/`ms-` bootstrap logical classes as the codebase does).
- Theme-aware (works in light/dark via the existing toggle).
- Mobile nav parity (the navbar has a separate `d-md-none` block — update both like the current file).
- Destructive actions (suspend/archive/remove/delete role) confirm first.
