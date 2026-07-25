<?php

namespace Tests\Feature\Structure;

use App\Models\ChurchService;
use App\Models\Enrollment;
use App\Models\StructureTemplate;
use App\Services\CourseRoleAssignmentService;
use App\Services\Structure\CycleProgressionEligibility;
use App\Services\Structure\StructureAnchorResolver;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\RosterStatus;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ProgressionPolicyTest extends EventModuleTestCase
{
    public function test_template_progression_defaults_are_seeded(): void
    {
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $meeting = StructureTemplate::byKey(StructureTemplate::KEY_MEETING_FLAT);
        $care = StructureTemplate::byKey(StructureTemplate::KEY_CARE_SECTOR);

        $this->assertNotNull($edu);
        $this->assertSame(
            ProgressionPolicy::SCHOOL_YEAR_LADDER,
            $edu->anchors['progression']['policy'] ?? null
        );
        $this->assertSame(
            ProgressionPolicy::CONTINUOUS_OPEN,
            $meeting->anchors['progression']['policy'] ?? null
        );
        $this->assertSame(
            ProgressionPolicy::CONTINUOUS_OPEN,
            $care->anchors['progression']['policy'] ?? null
        );
    }

    public function test_resolver_inherits_template_policy_and_honors_service_override(): void
    {
        $resolver = app(StructureAnchorResolver::class);
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $meeting = StructureTemplate::byKey(StructureTemplate::KEY_MEETING_FLAT);
        $this->assertNotNull($edu);
        $this->assertNotNull($meeting);

        $ladder = ChurchService::create([
            'title' => 'Sunday School Ladder',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'sunday-school-ladder-test',
            'structure_template_id' => $edu->structure_template_id,
            'permissions_version' => 0,
        ]);

        $this->assertSame(ProgressionPolicy::SCHOOL_YEAR_LADDER, $resolver->progressionPolicy($ladder));
        $this->assertTrue($resolver->supportsEndOfCycleWizard($ladder));

        $ladder->update(['progression_policy' => ProgressionPolicy::CONTINUOUS_OPEN]);
        $this->assertSame(ProgressionPolicy::CONTINUOUS_OPEN, $resolver->progressionPolicy($ladder->fresh()));
        $this->assertFalse($resolver->supportsEndOfCycleWizard($ladder->fresh()));

        $flat = ChurchService::create([
            'title' => 'Choir Continuous',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'choir-continuous-test',
            'structure_template_id' => $meeting->structure_template_id,
            'permissions_version' => 0,
        ]);

        $this->assertSame(ProgressionPolicy::CONTINUOUS_OPEN, $resolver->progressionPolicy($flat));
        $this->assertFalse($resolver->supportsEndOfCycleWizard($flat));
    }

    public function test_servants_prep_is_pinned_to_course_close_only(): void
    {
        $this->assertTrue(Schema::hasColumn('service', 'progression_policy'));

        $service = ChurchService::ensureDefault()->fresh();
        $this->assertSame('servants-prep', $service->slug);

        $resolver = app(StructureAnchorResolver::class);
        $this->assertSame(ProgressionPolicy::COURSE_CLOSE_ONLY, $resolver->progressionPolicy($service));
        $this->assertFalse($resolver->supportsEndOfCycleWizard($service));
    }

    public function test_inactive_and_hold_enrollments_excluded_from_propose(): void
    {
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $this->assertNotNull($edu);

        $service = ChurchService::create([
            'title' => 'Ladder Propose Test',
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => 'ladder-propose-test',
            'structure_template_id' => $edu->structure_template_id,
            'progression_policy' => ProgressionPolicy::SCHOOL_YEAR_LADDER,
            'permissions_version' => 0,
        ]);

        $course = $this->createCourse([
            'title' => 'Grade 5',
            'service_id' => $service->service_id,
        ]);
        $role = $this->createRole('student');
        $activeUser = $this->createUser();
        $inactiveUser = $this->createUser();
        $holdUser = $this->createUser();

        foreach ([$activeUser, $inactiveUser, $holdUser] as $user) {
            $this->ensureServiceMembership($user, $course);
            app(CourseRoleAssignmentService::class)->assign($user, $course->course_id, $role->role_id, notify: false);
        }

        $inactiveEnrollment = Enrollment::query()->where('user_id', $inactiveUser->user_id)->firstOrFail();
        $inactiveEnrollment->update([
            'status' => RosterStatus::INACTIVE,
            'status_note' => 'Not attending',
            'status_changed_at' => now(),
        ]);

        $holdEnrollment = Enrollment::query()->where('user_id', $holdUser->user_id)->firstOrFail();
        $holdEnrollment->update([
            'status' => RosterStatus::PASTORAL_HOLD,
            'status_note' => 'Needs review',
            'status_changed_at' => now(),
        ]);

        $eligibility = app(CycleProgressionEligibility::class);
        $this->assertTrue($eligibility->serviceSupportsWizard($service));
        $this->assertTrue($eligibility->enrollmentEligibleForPropose(
            Enrollment::query()->where('user_id', $activeUser->user_id)->firstOrFail()
        ));
        $this->assertFalse($eligibility->enrollmentEligibleForPropose($inactiveEnrollment->fresh()));
        $this->assertFalse($eligibility->enrollmentEligibleForPropose($holdEnrollment->fresh()));

        $proposed = $eligibility->proposeEligibleEnrollments($service);
        $this->assertCount(1, $proposed);
        $this->assertSame((int) $activeUser->user_id, (int) $proposed->first()->user_id);
    }

    public function test_create_service_persists_template_and_progression_policy(): void
    {
        $admin = $this->createUser();
        $admin->forceFill(['is_superadmin' => true])->save();
        $this->actingAs($admin);

        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $this->assertNotNull($edu);

        $response = $this->post(route('admin.services.store'), [
            'title' => 'New Ladder Service',
            'title_en' => 'New Ladder Service',
            'structure_template_id' => $edu->structure_template_id,
            'progression_policy' => ProgressionPolicy::SEMESTER_COHORT,
            'clone_templates' => 0,
        ]);

        $response->assertRedirect(route('admin.services.index'));

        $created = ChurchService::query()->where('title', 'New Ladder Service')->first();
        $this->assertNotNull($created);
        $this->assertSame((int) $edu->structure_template_id, (int) $created->structure_template_id);
        $this->assertSame(ProgressionPolicy::SEMESTER_COHORT, $created->progression_policy);
        $this->assertTrue(app(StructureAnchorResolver::class)->supportsEndOfCycleWizard($created));
    }
}
