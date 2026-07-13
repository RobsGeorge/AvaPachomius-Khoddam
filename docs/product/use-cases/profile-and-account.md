# Use cases — Profile & Account

Personas: **All authenticated users**. Controllers: `Auth\ProfileController`, `OnboardingController`,
`ThemeController`, `LocaleController`, `ProfilePhotoGateService`, `NotificationSettingsController`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-ACC-01 | User | Complete student onboarding (first login) | Skipped once completed | `auth` |
| UC-ACC-02 | User | View/edit own profile; upload profile photo | Photo gate: must upload within grace period or be limited; pending review state | own profile |
| UC-ACC-03 | User | Change own password | Wrong current password → error | own account |
| UC-ACC-04 | User | Switch theme (light/dark) and locale (ar/en); set font-size preference | Persisted per user | own preferences |
| UC-ACC-05 | User | Manage notification channel preferences & reminders | Mandatory categories protected | own preferences |
| UC-ACC-06 | User | Profile-photo grace/deadline enforced; admin may reject with note → user notified | Rejected → re-upload required | `ProfilePhotoGateService` |

**Coverage:** `StudentOnboardingTest`, `ProfilePhotoAdminTest`, `LoginPageTest`, `NotificationHubTest`
(preferences). Gaps: unified self-service account center, data export/print (feature-gap-analysis).
