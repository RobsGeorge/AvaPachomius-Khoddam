<?php

namespace Tests\Feature\Structure;

use App\Models\Church;
use App\Models\ChurchSchoolYear;
use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Services\CourseRoleAssignmentService;
use App\Services\Structure\ChurchCycleSeasonService;
use App\Services\Structure\CycleProgressionWizardService;
use App\Support\Structure\CycleDashboardStatus;
use App\Support\Structure\ProgressionPolicy;
use App\Support\Structure\SchoolYearStatus;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ChurchCycleDashboardTest extends EventModuleTestCase
{
    public function test_migration_creates_church_school_year_table(): void
    {
        $this->assertTrue(Schema::hasTable('church_school_year'));
        $this->assertTrue(Schema::hasColumn('service', 'church_school_year_id'));
    }

    public function test_dashboard_classifies_ready_blocked_skipped_and_course_close(): void
    {
        [$ladderReady] = $this->seedLadderService(withBlocked: false);
        [$ladderBlocked] = $this->seedLadderService(withBlocked: true, slug: 'ladder-blocked');

        $meeting = StructureTemplate::byKey(StructureTemplate::KEY_MEETING_FLAT)
            ?? StructureTemplate::query()->where('key', 'meeting_flat')->first();
        if ($meeting) {
            ChurchService::create([
                'title' => 'Choir Continuous',
                'status' => ChurchService::STATUS_ACTIVE,
                'slug' => 'choir-continuous-t9c',
                'structure_template_id' => $meeting->structure_template_id,
                'progression_policy' => ProgressionPolicy::CONTINUOUS_OPEN,
                'permissions_version' => 0,
            ]);
        }

        $prep = ChurchService::ensureDefault()->fresh();

        $dash = app(ChurchCycleSeasonService::class)->dashboard(Church::main());
        $byId = collect($dash['rows'])->keyBy('service_id');

        $this->assertSame(CycleDashboardStatus::READY, $byId[$ladderReady->service_id]['status']);
        $this->assertSame(CycleDashboardStatus::BLOCKED, $byId[$ladderBlocked->service_id]['status']);
        $this->assertSame(CycleDashboardStatus::COURSE_CLOSE, $byId[$prep->service_id]['status']);

        if ($meeting) {
            $choir = ChurchService::query()->where('slug', 'choir-continuous-t9c')->first();
            $this->assertSame(CycleDashboardStatus::SKIPPED, $byId[$choir->service_id]['status']);
        }
    }

    public function test_create_year_and_start_promotion_links_and_closes_gate(): void
    {
        [$service] = $this->seedLadderService(withBlocked: false);
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $church = Church::main();

        $year = $seasons->createYear($church, [
            'label' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);

        $this->assertSame(SchoolYearStatus::ACTIVE, $year->status);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $seasons->createYear($church, [
            'label' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);
    }

    public function test_start_promotion_sets_closing_and_links_wizard_services(): void
    {
        [$service] = $this->seedLadderService(withBlocked: false);
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $church = Church::main();

        $year = $seasons->createYear($church, [
            'label' => '2025/2026-promo',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);

        $year = $seasons->startPromotionSeason($year, $actor);

        $this->assertSame(SchoolYearStatus::CLOSING, $year->status);
        $this->assertNotNull($year->promotion_started_at);
        $this->assertSame(
            (int) $year->church_school_year_id,
            (int) $service->fresh()->church_school_year_id
        );
    }

    public function test_close_year_blocked_when_ready_services_remain(): void
    {
        $this->seedLadderService(withBlocked: false);
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $year = $seasons->createYear(Church::main(), [
            'label' => '2025/2026-close',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);
        $seasons->startPromotionSeason($year, $actor);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $seasons->closeYear($year->fresh(), $actor, force: false);
    }

    public function test_force_close_and_mark_done(): void
    {
        [$service] = $this->seedLadderService(withBlocked: false);
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $year = $seasons->createYear(Church::main(), [
            'label' => '2025/2026-force',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);
        $year = $seasons->startPromotionSeason($year, $actor);

        $year = $seasons->markServiceDone($year, $service, $actor);
        $this->assertTrue($year->hasCompletedService((int) $service->service_id));

        $dash = $seasons->dashboard(Church::main());
        $row = collect($dash['rows'])->firstWhere('service_id', (int) $service->service_id);
        $this->assertSame(CycleDashboardStatus::DONE, $row['status']);

        $year = $seasons->closeYear($year->fresh(), $actor, force: true);
        $this->assertSame(SchoolYearStatus::CLOSED, $year->status);
        $this->assertNotNull($year->closed_at);
    }

    public function test_apply_during_closing_auto_marks_done_when_none_eligible(): void
    {
        [$service, $from, $to, $user] = $this->seedLadderService(withBlocked: false);
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $year = $seasons->createYear(Church::main(), [
            'label' => '2025/2026-auto',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);
        $year = $seasons->startPromotionSeason($year, $actor);

        $proposal = app(CycleProgressionWizardService::class)->propose($service);
        $row = $proposal['rows'][0];
        app(CycleProgressionWizardService::class)->apply($service, $actor, [[
            'enrollment_id' => $row['enrollment_id'],
            'action' => CycleProgressionWizardService::ACTION_PROMOTE,
            'to_course_id' => $to->course_id,
        ]]);

        $this->assertTrue($year->fresh()->hasCompletedService((int) $service->service_id));
    }

    public function test_http_dashboard_requires_auth_and_renders_for_superadmin(): void
    {
        $this->get(route('church.cycle.index'))->assertRedirect();

        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $this->get(route('church.cycle.index'))
            ->assertOk()
            ->assertSee(__('church_cycle.title'), false);
    }

    public function test_http_store_year_and_start_promotion(): void
    {
        $this->seedLadderService(withBlocked: false);
        $admin = $this->createUser(['is_superadmin' => true]);
        $this->actingAs($admin);

        $this->post(route('church.cycle.years.store'), [
            'label' => '2025/2026-http',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ])->assertRedirect(route('church.cycle.index'));

        $year = ChurchSchoolYear::query()->where('label', '2025/2026-http')->firstOrFail();

        $this->post(route('church.cycle.years.start-promotion', $year))
            ->assertRedirect(route('church.cycle.index'));

        $this->assertSame(SchoolYearStatus::CLOSING, $year->fresh()->status);
    }

    public function test_school_year_is_tenant_scoped(): void
    {
        $actor = $this->createUser(['is_superadmin' => true]);
        $seasons = app(ChurchCycleSeasonService::class);
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'cycle-isol-b', 'name' => 'Cycle B', 'status' => 'active']);

        \App\Tenancy\TenantContext::set($churchA);
        $yearA = $seasons->createYear($churchA, [
            'label' => 'A-year',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);

        \App\Tenancy\TenantContext::set($churchB);
        $this->assertNull(ChurchSchoolYear::find($yearA->church_school_year_id));

        $yearB = $seasons->createYear($churchB, [
            'label' => 'B-year',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'status' => SchoolYearStatus::ACTIVE,
        ], $actor);
        $this->assertSame((int) $churchB->church_id, (int) $yearB->church_id);

        \App\Tenancy\TenantContext::set($churchA);
        $this->assertNull(ChurchSchoolYear::find($yearB->church_school_year_id));
        $this->assertNotNull(ChurchSchoolYear::find($yearA->church_school_year_id));
    }

    /**
     * @return array{0: ChurchService, 1: \App\Models\Course, 2: \App\Models\Course, 3: \App\Models\User, 4?: \App\Models\User}
     */
    private function seedLadderService(bool $withBlocked = true, string $slug = 'ladder-ready'): array
    {
        $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
        $service = ChurchService::create([
            'title' => 'Sunday School '.$slug,
            'status' => ChurchService::STATUS_ACTIVE,
            'slug' => $slug.'-'.uniqid(),
            'structure_template_id' => $edu->structure_template_id,
            'progression_policy' => ProgressionPolicy::SCHOOL_YEAR_LADDER,
            'permissions_version' => 0,
        ]);

        $from = $this->createCourse(['title' => 'Grade 5', 'service_id' => $service->service_id, 'year' => 2025]);
        $to = $this->createCourse(['title' => 'Grade 6', 'service_id' => $service->service_id, 'year' => 2026]);
        $orphan = $this->createCourse(['title' => 'Grade 7 terminal', 'service_id' => $service->service_id, 'year' => 2027]);

        $service->progression_config = [
            'ladder' => [
                'edges' => [
                    ['from_course_id' => (int) $from->course_id, 'to_course_id' => (int) $to->course_id],
                ],
            ],
        ];
        $service->save();

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
