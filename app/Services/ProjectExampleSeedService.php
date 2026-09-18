<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Course;
use App\Models\Module;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Idempotent sample project for the portal: one published assessment with three
 * teams, each a unique subproject title and its own requirements. Used by
 * `projects:seed-example` and the production deploy migration. Never overwrites
 * an existing assessment — a second run returns the same row.
 */
class ProjectExampleSeedService
{
    public const ASSESSMENT_TITLE = 'مثال: مشروع الخدمة الميدانية';

    public const MODULE_TITLE = 'مثال — مشروع الخدمة الميدانية';

    public const MARKER = 'example-field-ministry';

    public function __construct(private ProjectAdminService $admin) {}

    /**
     * @return array{assessment: ProjectAssessment, created: bool}|null
     */
    public function seed(?int $courseId = null, ?User $actor = null, bool $publish = true): ?array
    {
        app(ProjectAccessRepairService::class)->repair();

        $course = $this->resolveCourse($courseId);
        if ($course === null) {
            return null;
        }

        $this->bindCourseTenant($course);

        $existing = $this->existingAssessment($course);
        if ($existing) {
            return ['assessment' => $existing, 'created' => false];
        }

        $module = $this->resolveModule($course);
        $actor ??= $this->resolveActor($course);
        if ($actor === null) {
            return null;
        }

        $now = Carbon::now();
        $assessment = $this->admin->createAssessment([
            'course_id' => (int) $course->course_id,
            'module_id' => (int) $module->module_id,
            'title' => self::ASSESSMENT_TITLE,
            'description' => $this->assessmentDescription(),
            'min_team_size' => 2,
            'max_team_size' => 4,
            'max_points' => 100,
            'passing_percent' => 50,
            'join_closes_at' => $now->copy()->addWeeks(4)->toDateTimeString(),
            'submission_due_at' => $now->copy()->addWeeks(8)->toDateTimeString(),
            'seed_pool_size' => 3,
            'requirements' => $this->sharedRequirements(),
            'subprojects' => $this->subprojects(),
            'phases' => $this->phases($now),
            'deliverables' => $this->deliverables($now),
            'criteria' => $this->criteria(),
        ], $actor);

        if ($publish) {
            $this->admin->publish($assessment, true);
        }

        return [
            'assessment' => $assessment->fresh(['projects.phases', 'projects.deliverables', 'criteria']),
            'created' => true,
        ];
    }

    public function existingAssessment(Course $course): ?ProjectAssessment
    {
        // withoutTenancy: console/deploy must find the example even if context is unbound.
        return ProjectAssessment::withoutTenancy()
            ->where('course_id', $course->course_id)
            ->where(function ($query) {
                $query->where('title', self::ASSESSMENT_TITLE)
                    ->orWhere('description', 'like', '%['.self::MARKER.']%');
            })
            ->first();
    }

    public function resolveCourse(?int $courseId = null): ?Course
    {
        if ($courseId !== null) {
            // withoutTenancy: artisan --course= must resolve any church's course.
            return Course::withoutTenancy()->find($courseId);
        }

        $query = Course::withoutTenancy()
            ->orderByDesc('year')
            ->orderByDesc('course_id');

        $churchId = $this->targetChurchId();
        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        }

        $active = (clone $query)->where('status', Course::STATUS_ACTIVE)->first();
        if ($active) {
            return $active;
        }

        return $query->whereNotIn('status', [Course::STATUS_ARCHIVED])->first();
    }

    private function resolveModule(Course $course): Module
    {
        $existing = $course->modules()->first();
        if ($existing) {
            return $existing;
        }

        $module = Module::create([
            'title' => self::MODULE_TITLE,
            'description' => 'وحدة مثال لمشروع الخدمة الميدانية بأثلاث فرق ذات عناوين ومتطلبات مختلفة.',
        ]);
        $course->modules()->attach($module->module_id);

        return $module;
    }

    private function resolveActor(Course $course): ?User
    {
        $superadmin = User::query()
            ->where('is_superadmin', true)
            ->where('application_status', User::APPLICATION_STATUS_APPROVED)
            ->orderBy('user_id')
            ->first();
        if ($superadmin) {
            return $superadmin;
        }

        $staffUserId = UserCourseRole::withoutTenancy()
            ->where('course_id', $course->course_id)
            ->orderBy('user_course_role_id')
            ->value('user_id');
        if ($staffUserId) {
            $staff = User::query()->find($staffUserId);
            if ($staff) {
                return $staff;
            }
        }

        return User::query()
            ->where('application_status', User::APPLICATION_STATUS_APPROVED)
            ->orderBy('user_id')
            ->first();
    }

    private function bindCourseTenant(Course $course): void
    {
        $churchId = $course->church_id ? (int) $course->church_id : $this->targetChurchId();
        if ($churchId === null) {
            return;
        }

        $church = Church::query()->find($churchId);
        if ($church) {
            TenantContext::set($church);
        }
    }

    private function targetChurchId(): ?int
    {
        $fromContext = TenantContext::id();
        if ($fromContext !== null) {
            return (int) $fromContext;
        }

        if (! Church::query()->exists()) {
            return null;
        }

        return (int) Church::main()->church_id;
    }

    private function assessmentDescription(): string
    {
        return implode("\n\n", [
            'مشروع مثال منفصل لتوضيح الفرق بين الفرق: كل فريق يعمل عنواناً مختلفاً وله متطلبات خاصة، مع مراحل وتسليمات مشتركة.',
            'Separate example project: three teams, each with a distinct title and its own requirements. Shared phases and deliverables apply to every team.',
            '['.self::MARKER.']',
        ]);
    }

    private function sharedRequirements(): string
    {
        return 'خططوا للخدمة ونفّذوها وقدّموا تقريراً موثقاً. التزموا بمواعيد المراحل والتسليمات المشتركة، ونسّقوا مع خدمة الكنيسة قبل أي زيارة ميدانية.';
    }

    /**
     * @return list<array{title: string, brief_main_title: string, brief_audience: string, brief_environment: string, brief_purpose: string}>
     */
    private function subprojects(): array
    {
        return [
            [
                'title' => 'زيارة المرضى',
                'brief_main_title' => 'زيارة المرضى في المستشفى ودار الرعاية',
                'brief_audience' => 'المرضى في المستشفى أو دار الرعاية',
                'brief_environment' => 'مستشفى أو دار رعاية، بعد التنسيق مع أحد الآباء الكهنة',
                'brief_purpose' => 'زيارتان على الأقل وتأمل روحي وجدول زيارات موثّق',
            ],
            [
                'title' => 'خدمة المسنين',
                'brief_main_title' => 'رعاية المسنين في الدار والبيوت',
                'brief_audience' => 'كبار السن في دار مسنين أو أسرتين من الرعية',
                'brief_environment' => 'دار مسنين أو منزلان في الرعية',
                'brief_purpose' => 'مقابلات قصيرة وخطة رعاية شهرية بزيارة ومتابعة واتصال',
            ],
            [
                'title' => 'خدمة الأيتام',
                'brief_main_title' => 'يوم نشاط مع الأيتام وأطفال الرعية',
                'brief_audience' => 'أطفال دار أيتام أو أطفال الرعية',
                'brief_environment' => 'دار أيتام أو قاعة الرعية بعد موافقة الإدارة',
                'brief_purpose' => 'برنامج وميزانية بالجنيه وكشف متطوعين ليوم النشاط',
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, deadline: string}>
     */
    private function phases(Carbon $now): array
    {
        return ProjectTeamWorkflowService::canonicalPhasePayload(
            $now->copy()->addWeeks(8)->toDateTimeString()
        );
    }

    /**
     * @return list<array{title: string, description: string, instructions: string, submission_type: string, is_required: bool, allow_late: bool, slot_key: string, due_at: ?string}>
     */
    private function deliverables(Carbon $now): array
    {
        return ProjectTeamWorkflowService::canonicalDeliverablePayload(
            $now->copy()->addWeeks(8)->toDateTimeString()
        );
    }

    /**
     * @return list<array{title: string, max_points: int}>
     */
    private function criteria(): array
    {
        return [
            ['title' => 'الالتزام بالخطة', 'max_points' => 30],
            ['title' => 'جودة التنفيذ', 'max_points' => 40],
            ['title' => 'التقرير', 'max_points' => 30],
        ];
    }
}
