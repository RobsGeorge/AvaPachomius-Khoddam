<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Models\Enrollment;
use App\Models\StructureTemplate;
use App\Models\UserCourseRole;
use App\Services\CourseRoleAssignmentService;
use App\Services\Structure\CycleProgressionWizardService;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\RosterStatus;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class CycleProgressionWizardTest extends EventModuleTestCase
{
    public function test_course_close_only_refuses_wizard(): void
    {
        $service = ChurchService::ensureDefault()->fresh();
        $this->assertSame(ProgressionPolicy::COURSE_CLOSE_ONLY, $service->progression_policy
            ?: app(\App\Services\Structure\StructureAnchorResolver::class)->progressionPolicy($service));

        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $this->get(route('admin.services.cycle.show', $service))
            ->assertRedirect(route('admin.services.edit', $service));
    }

    public function test_propose_maps_edges_and_blocks_missing(): void
    {
        [$service, $from, $to, $activeUser, $blockedUser] = $this->seedLadderService();

        $wizard = app(CycleProgressionWizardService::class);
        $proposal = $wizard->propose($service);

        $this->assertSame(2, $proposal['counts']['eligible']);
        $this->assertSame(1, $proposal['counts']['ready']);
        $this->assertSame(1, $proposal['counts']['blocked']);

        $byUser = collect($proposal['rows'])->keyBy('user_id');
        $this->assertSame(CycleProgressionWizardService::ACTION_PROMOTE, $byUser[$activeUser->user_id]['suggested_action']);
        $this->assertSame((int) $to->course_id, (int) $byUser[$activeUser->user_id]['to_course_id']);
        $this->assertSame('missing_edge', $byUser[$blockedUser->user_id]['block_reason']);
    }

    public function test_inactive_excluded_from_propose(): void
    {
        [$service, $from] = $this->seedLadderService(withBlocked: false);
        $inactive = $this->createUser();
        $role = $this->createRole('student');
        $this->ensureServiceMembership($inactive, $from);
        app(CourseRoleAssignmentService::class)->assign($inactive, $from->course_id, $role->role_id, notify: false);

        Enrollment::query()->where('user_id', $inactive->user_id)->update([
            'status' => RosterStatus::INACTIVE,
            'status_changed_at' => now(),
        ]);

        $proposal = app(CycleProgressionWizardService::class)->propose($service);
        $userIds = collect($proposal['rows'])->pluck('user_id')->all();
        $this->assertNotContains((int) $inactive->user_id, $userIds);
    }

    public function test_apply_promotes_skips_and_marks_inactive(): void
    {
        [$service, $from, $to, $promoteUser, $skipUser] = $this->seedLadderService();
        $orphan = \App\Models\Course::query()
            ->where('service_id', $service->service_id)
            ->where('title', 'Grade 7 terminal')
            ->firstOrFail();

        $inactiveUser = $this->createUser();
        $role = $this->createRole('student');
        $this->ensureServiceMembership($inactiveUser, $from);
        app(CourseRoleAssignmentService::class)->assign($inactiveUser, $from->course_id, $role->role_id, notify: false);

        $proposal = app(CycleProgressionWizardService::class)->propose($service);
        $byUser = collect($proposal['rows'])->keyBy('user_id');

        $actor = $this->createUser(['is_superadmin' => true]);
        $result = app(CycleProgressionWizardService::class)->apply($service, $actor, [
            [
                'enrollment_id' => $byUser[$promoteUser->user_id]['enrollment_id'],
                'action' => CycleProgressionWizardService::ACTION_PROMOTE,
                'to_course_id' => $to->course_id,
            ],
            [
                'enrollment_id' => $byUser[$skipUser->user_id]['enrollment_id'],
                'action' => CycleProgressionWizardService::ACTION_SKIP,
            ],
            [
                'enrollment_id' => $byUser[$inactiveUser->user_id]['enrollment_id'],
                'action' => CycleProgressionWizardService::ACTION_INACTIVE,
                'note' => 'Left mid-year',
            ],
        ]);

        $this->assertSame(1, $result['moved']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['inactivated']);
        $this->assertSame((int) $promoteUser->user_id, (int) $result['audit']['moved'][0]['user_id']);
        $this->assertNotEmpty($result['audit']['inactivated']);

        $this->assertTrue(
            UserCourseRole::query()
                ->where('user_id', $promoteUser->user_id)
                ->where('course_id', $to->course_id)
                ->exists()
        );
        $this->assertFalse(
            UserCourseRole::query()
                ->where('user_id', $promoteUser->user_id)
                ->where('course_id', $from->course_id)
                ->exists()
        );

        $this->assertTrue(
            UserCourseRole::query()
                ->where('user_id', $skipUser->user_id)
                ->where('course_id', $orphan->course_id)
                ->exists()
        );

        $inactiveEnrollment = Enrollment::query()->where('user_id', $inactiveUser->user_id)->first();
        $this->assertSame(RosterStatus::INACTIVE, $inactiveEnrollment->status);
        $this->assertSame('Left mid-year', $inactiveEnrollment->status_note);
    }

    public function test_promote_without_target_fails_validation(): void
    {
        [$service, $from, $to, $user, $blockedUser] = $this->seedLadderService(withBlocked: true);
        $proposal = app(CycleProgressionWizardService::class)->propose($service);
        $row = collect($proposal['rows'])->firstWhere('user_id', $blockedUser->user_id);
        $this->assertNotNull($row);
        $this->assertNull($row['to_course_id']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(CycleProgressionWizardService::class)->apply(
            $service,
            $this->createUser(['is_superadmin' => true]),
            [[
                'enrollment_id' => $row['enrollment_id'],
                'action' => CycleProgressionWizardService::ACTION_PROMOTE,
            ]]
        );
    }

    public function test_http_confirm_applies_for_superadmin(): void
    {
        [$service, $from, $to, $user] = $this->seedLadderService(withBlocked: false);
        $proposal = app(CycleProgressionWizardService::class)->propose($service);
        $row = $proposal['rows'][0];

        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $this->post(route('admin.services.cycle.confirm', $service), [
            'decisions' => [[
                'enrollment_id' => $row['enrollment_id'],
                'action' => 'promote',
                'to_course_id' => $to->course_id,
            ]],
        ])->assertRedirect(route('admin.services.cycle.show', $service));

        $this->assertTrue(
            UserCourseRole::query()
                ->where('user_id', $user->user_id)
                ->where('course_id', $to->course_id)
                ->exists()
        );
    }

    public function test_save_ladder_edges_persists_config(): void
    {
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $service = ChurchService::create([
            'title' => 'Edges Service',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'edges-service-test',
            'structure_template_id' => $edu->structure_template_id,
            'progression_policy' => ProgressionPolicy::SCHOOL_YEAR_LADDER,
            'permissions_version' => 0,
        ]);
        $a = $this->createCourse(['title' => 'A', 'service_id' => $service->service_id, 'year' => 2025]);
        $b = $this->createCourse(['title' => 'B', 'service_id' => $service->service_id, 'year' => 2026]);

        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $this->post(route('admin.services.cycle.edges', $service), [
            'edges' => [
                ['from_course_id' => $a->course_id, 'to_course_id' => $b->course_id],
            ],
        ])->assertRedirect(route('admin.services.cycle.show', $service));

        $service->refresh();
        $this->assertSame(
            [['from_course_id' => (int) $a->course_id, 'to_course_id' => (int) $b->course_id]],
            $service->progression_config['ladder']['edges']
        );
    }

    public function test_dual_write_preserves_inactive_status(): void
    {
        $this->assertTrue(Schema::hasTable('enrollments'));
        $course = $this->createCourse();
        $role = $this->createRole('student');
        $user = $this->createUser();
        $this->ensureServiceMembership($user, $course);
        $ucr = app(CourseRoleAssignmentService::class)->assign($user, $course->course_id, $role->role_id, notify: false);

        $enrollment = Enrollment::query()->where('user_course_role_id', $ucr->user_course_role_id)->firstOrFail();
        $enrollment->update(['status' => RosterStatus::INACTIVE, 'status_note' => 'hold']);

        $ucr->touch();
        app(\App\Services\Structure\EnrollmentDualWrite::class)->syncFromUserCourseRole($ucr->fresh());

        $this->assertSame(RosterStatus::INACTIVE, $enrollment->fresh()->status);
    }

    /**
     * @return array{0: ChurchService, 1: \App\Models\Course, 2: \App\Models\Course, 3: \App\Models\User, 4?: \App\Models\User}
     */
    private function seedLadderService(bool $withBlocked = true): array
    {
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $this->assertNotNull($edu);

        $service = ChurchService::create([
            'title' => 'Sunday Ladder',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'sunday-ladder-'.uniqid(),
            'structure_template_id' => $edu->structure_template_id,
            'progression_policy' => ProgressionPolicy::SCHOOL_YEAR_LADDER,
            'permissions_version' => 0,
        ]);

        $from = $this->createCourse(['title' => 'Grade 5', 'service_id' => $service->service_id, 'year' => 2025]);
        $to = $this->createCourse(['title' => 'Grade 6', 'service_id' => $service->service_id, 'year' => 2026]);
        $orphan = $this->createCourse(['title' => 'Grade 7 terminal', 'service_id' => $service->service_id, 'year' => 2027]);

        $service->update([
            'progression_config' => [
                'ladder' => [
                    'edges' => [
                        ['from_course_id' => (int) $from->course_id, 'to_course_id' => (int) $to->course_id],
                    ],
                ],
            ],
        ]);

        $role = $this->createRole('student');
        $activeUser = $this->createUser();
        $this->ensureServiceMembership($activeUser, $from);
        app(CourseRoleAssignmentService::class)->assign($activeUser, $from->course_id, $role->role_id, notify: false);

        if (! $withBlocked) {
            return [$service->fresh(), $from, $to, $activeUser];
        }

        $blockedUser = $this->createUser();
        $this->ensureServiceMembership($blockedUser, $orphan);
        app(CourseRoleAssignmentService::class)->assign($blockedUser, $orphan->course_id, $role->role_id, notify: false);

        return [$service->fresh(), $from, $to, $activeUser, $blockedUser];
    }
}
