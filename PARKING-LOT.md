# Parking Lot

Out-of-phase findings. Captured, deliberately NOT built now.

## Environment / config debt (fold into P0 where cheap)
- Server has PHP 8.2, 8.4 AND 8.5 installed. CLI defaulted to 8.4 while FPM runs 8.2.
  Pinned CLI to 8.2 via update-alternatives. Consider removing 8.4/8.5 once stable.
- ext-curl and ext-gd were missing from PHP 8.2 despite being required in composer.json
  (simple-qrcode needs gd; reverb/pusher needs curl). Installed. Implies those features
  were never exercised in production — verify.
- Production sends `Access-Control-Allow-Origin: http://localhost:3000` — dev leftover
  in config/cors.php. Should be env-driven and locked down in production.
- GitHub Actions deploy pipeline is unreliable (SSH i/o timeouts). Manual deploy.sh is
  the current path. Revisit CI/CD deployment after P0.

## Product ideas (see master-plan §12 parking lot for the full list)
- The `user` table has many NOT NULL columns with no defaults (profile_photo, 
  national_id, job, date_of_birth...). This makes programmatic user creation 
  (seeders, tests, admin tools) painful and forces placeholder data. Most of 
  these are profile attributes, not auth attributes — they should be nullable, 
  and in P2 they migrate to `people` anyway.

## Service above Course (department layer)
- Spec: year-agnostic Service owns membership/RBAC/org; Course alone owns
  attendance, grades, exams, lectures, graduation.
- Users have a primary Service; admins may cross-add existing Service users
  into another Service. Course enroll requires Service membership.
- Distinct from multi-subsidiary P6 “Service” tenant type.
- Expand-contract implementation started under user request (schema + membership
  + Roles Hub Service section). Deferred: Service applications, full org lift
  of announcements/roster, `course.service_id` NOT NULL contraction, BelongsToChurch
  when tenancy lands. Plan: `.cursor/plans/service_entity_layer_c1010b64.plan.md`