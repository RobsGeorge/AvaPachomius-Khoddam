#!/usr/bin/env bash
# Verifies the factual claims made in docs/plans/spims/gap-analysis.md
# against both codebases. Prints PASS/FAIL per claim.
set -uo pipefail

AVA=/workspace
SPIMS=/tmp/spims-edu
pass=0; fail=0

check() { # check <description> <expected> <actual>
  if [ "$2" = "$3" ]; then
    printf 'PASS  %-58s %s\n' "$1" "$3"; pass=$((pass+1))
  else
    printf 'FAIL  %-58s expected=%s actual=%s\n' "$1" "$2" "$3"; fail=$((fail+1))
  fi
}

nfiles()  { find "$1" -name '*.php' 2>/dev/null | wc -l | tr -d ' '; }
nroutes() { grep -cE '^[[:space:]]*Route::(get|post|put|patch|delete)' "$1" || true; }
# fixed-string count in one file
fc()      { local n; n=$(grep -cF -- "$1" "$2" 2>/dev/null) || n=0; echo "$n"; }
# regex count in one file
rc()      { local n; n=$(grep -cE -- "$1" "$2" 2>/dev/null) || n=0; echo "$n"; }
# how many files under the given roots mention the term (case-insensitive)
sweep()   { local t=$1; shift; grep -rliF -- "$t" "$@" 2>/dev/null | wc -l | tr -d ' '; }
# how many migration files create the given table
mktbl()   { grep -rlF -- "Schema::create('$1'" "$SPIMS/database/migrations" 2>/dev/null | wc -l | tr -d ' '; }
exists()  { [ -e "$1" ] && echo present || echo absent; }

echo "=============================================================================="
echo " Baseline scale"
echo "=============================================================================="
check "ava: migrations"          165 "$(ls $AVA/database/migrations | wc -l | tr -d ' ')"
check "ava: models"              171 "$(nfiles $AVA/app/Models)"
check "ava: controllers"         164 "$(nfiles $AVA/app/Http/Controllers)"
check "ava: service classes"     156 "$(nfiles $AVA/app/Services)"
check "ava: test files"          220 "$(find $AVA/tests -name '*Test.php' | wc -l | tr -d ' ')"
check "ava: web routes"          600 "$(nroutes $AVA/routes/web.php)"
check "ava: api/v1 routes"        57 "$(nroutes $AVA/routes/api.php)"
check "ava: permission keys"     139 "$(rc "^ {12}'[a-z_.]+' => \[" $AVA/config/permissions.php)"
check "ava: capabilities"         14 "$(rc "^ {4}'[a-z_]+' => \[" $AVA/config/capabilities.php)"
echo
check "spims: migrations"         18 "$(ls $SPIMS/database/migrations | wc -l | tr -d ' ')"
check "spims: models"             63 "$(nfiles $SPIMS/app/Models)"
check "spims: controllers"        56 "$(nfiles $SPIMS/app/Http/Controllers)"
check "spims: service classes"    47 "$(nfiles $SPIMS/app/Services)"
check "spims: test files"         35 "$(find $SPIMS/tests -name '*Test.php' | wc -l | tr -d ' ')"
check "spims: web routes"        166 "$(nroutes $SPIMS/routes/web.php)"
check "spims: api route defs"      0 "$(nroutes $SPIMS/routes/api.php)"
check "spims: permission keys"    56 "$(rc "^ {4}'[a-z_.]+' =>" $SPIMS/config/permissions.php)"

echo
echo "=============================================================================="
echo " G-01  No mobile API in SPIMS"
echo "=============================================================================="
check "spims: routes/api.php lines"          19 "$(wc -l < $SPIMS/routes/api.php | tr -d ' ')"
check "spims: 'v1' in api.php"                0 "$(fc v1 $SPIMS/routes/api.php)"
check "spims: Api/V1 controller dir"     absent "$(exists $SPIMS/app/Http/Controllers/Api/V1)"
check "spims: Http/Resources dir"        absent "$(exists $SPIMS/app/Http/Resources)"
check "spims: Api/ controllers (webhook)"     4 "$(nfiles $SPIMS/app/Http/Controllers/Api)"
check "ava:   Api/V1 controllers"            23 "$(nfiles $AVA/app/Http/Controllers/Api/V1)"

echo
echo "=============================================================================="
echo " G-02  AuthorizeService ignores \$resource (live defect)"
echo "=============================================================================="
AS=$SPIMS/app/Support/AuthorizeService.php
check "spims: authorize() declares \$resource" 1 "$(fc 'mixed $resource = null' $AS)"
check "spims: \$resource refs in whole file"   1 "$(fc '$resource' $AS)"
check "spims: 'O' collapses to allow"          1 "$(fc "str_contains(\$level, 'O')" $AS)"
check "spims: call sites passing a resource"   0 \
  "$(grep -rhoE '\->authorize\([^;)]*\)' $SPIMS/app --include='*.php' | grep -cE '^->authorize\([^,]+,[^,]+,' || true)"
check "spims: GradebookCtrl scope check"       0 "$(rc 'assertCanTeachOffering|scopedTo|OfferingStaff' $SPIMS/app/Http/Controllers/Admin/GradebookController.php)"
check "spims: TeachCtrl DOES scope"            2 "$(fc 'assertCanTeachOffering' $SPIMS/app/Http/Controllers/Teach/TeachController.php)"
check "ava:   CoursePermissionResolver"  present "$(exists $AVA/app/Services/CoursePermissionResolver.php)"

echo
echo "=============================================================================="
echo " G-03/G-04  Attendance is Zoom-bound in SPIMS"
echo "=============================================================================="
LIVE=$SPIMS/database/migrations/2026_07_28_100006_create_live_tables.php
check "spims: attendance_records.live_session_id" 1 "$(fc "foreignUlid('live_session_id')" $LIVE)"
check "spims: source default ZOOM_IMPORT"         1 "$(fc "default('ZOOM_IMPORT')" $LIVE)"
check "spims: 'excused' anywhere"                 0 "$(sweep excused $SPIMS/app $SPIMS/database $SPIMS/routes)"
check "spims: 'lock_version' anywhere"            0 "$(sweep lock_version $SPIMS/app $SPIMS/database)"
check "spims: attendance self check-in"           0 "$(sweep check_in $SPIMS/app $SPIMS/routes $SPIMS/database)"
check "ava:   attendance lock_version migration" present \
  "$(exists $AVA/database/migrations/2026_08_11_000002_add_lock_version_to_attendance.php)"
check "ava:   attendance_policy migration"       present \
  "$(exists $AVA/database/migrations/2026_06_20_000002_create_attendance_policy_table.php)"

echo
echo "=============================================================================="
echo " Absent subsystems in SPIMS (sweeps over app/routes/database/config)"
echo "=============================================================================="
for term in deliverable peer_rating peer_evaluation live_quiz feedback_survey \
            reservation email_template graduation birthday communication_log \
            announcement_delivery notification_preference student_note; do
  check "spims: files matching '$term'" 0 "$(sweep "$term" $SPIMS/app $SPIMS/routes $SPIMS/database $SPIMS/config)"
done
for t in projects project_assessments events event_reservations live_quizzes \
         feedback_surveys communication_logs email_templates course_graduations; do
  check "spims: table '$t' created" 0 "$(mktbl $t)"
done

echo
echo "=============================================================================="
echo " G-19  No realtime transport in SPIMS"
echo "=============================================================================="
check "spims: reverb/pusher/ably in composer" 0 "$(rc 'reverb|pusher|ably' $SPIMS/composer.json)"
check "spims: channels defined"               1 "$(fc 'Broadcast::channel' $SPIMS/routes/channels.php)"
check "spims: BROADCAST_DRIVER default"    null \
  "$(grep -oE "BROADCAST_DRIVER', '[a-z]+" $SPIMS/config/broadcasting.php | sed "s/.*'//")"
check "ava:   reverb config"            present "$(exists $AVA/config/reverb.php)"

echo
echo "=============================================================================="
echo " SPIMS-ahead areas (must NOT be reported as gaps)"
echo "=============================================================================="
for t in programs semesters course_offerings enrollments academic_records \
         program_requirement_fulfillments question_banks assessment_attempts \
         invoices wallet_accounts credentials discussion_threads applications; do
  check "spims: table '$t' created" 1 "$(mktbl $t)"
done
check "spims: OfferingService::cloneFromCourse" 1 "$(fc 'function cloneFromCourse' $SPIMS/app/Services/Offerings/OfferingService.php)"
check "spims: public credential verify route"   1 "$(fc 'credentials.verify' $SPIMS/routes/web.php)"

echo
echo "=============================================================================="
printf ' RESULT: %d passed, %d failed\n' "$pass" "$fail"
echo "=============================================================================="
[ "$fail" -eq 0 ]
