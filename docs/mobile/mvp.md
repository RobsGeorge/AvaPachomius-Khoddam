# Mobile MVP (student-first)

Confirmed product slice for the first native release (Expo / React Native).
Staff and admin flows remain web-only.

## In scope (v1)

| Area | API / UX |
|------|----------|
| Auth | Sanctum token login / logout / session self (`/api/v1/me`) |
| Notifications | Inbox list, unread count, mark all read |
| Announcements | Student inbox (published deliveries) |
| Attendance | Own attendance records + monthly/overall stats |
| Profile | Display name, email, mobile, locale preference |
| Theme | Shared design tokens (light/dark) for parity with web |

## Explicitly out of scope for v1

- Staff roster, grading entry, announcement publish, Roles Hub
- SMS send, WhatsApp Cloud API from the device
- Offline-first sync, App Store / Play listing automation
- Full grades transcript editor (read-only summary may land in a later iteration)

## Repos

- **Backend:** this Laravel app (`AvaPachomius-Khoddam`) — JSON under `/api/v1`
- **Client:** sibling repo `AvaPachomius-Khoddam-Mobile` (single Expo codebase → iOS + Android)

## Design tokens ownership

Canonical JSON: [`resources/design-tokens/khoddam.tokens.json`](../resources/design-tokens/khoddam.tokens.json)  
Mirrored into the mobile app at `src/theme/tokens.ts`. After changing CSS/`khoddam.tokens.json`, sync the mobile copy (documented in both READMEs).

## Feature parity tracker

Full student Web → API → Mobile matrix (waves A–E):  
[`docs/mobile/student-feature-matrix.md`](student-feature-matrix.md)
