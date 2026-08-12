<?php

namespace App\Services;

use App\Models\AssessmentCriterion;
use App\Models\Church;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleStudentAssessment;
use App\Models\ModuleStudentAssessmentScore;
use App\Models\StudentInstructorNote;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleStudentAssessmentService
{
    /** @return list<array{key:string,label_en:string,label_ar:string,weight:int,order_index:int}> */
    public static function defaultCriteria(): array
    {
        return [
            ['key' => 'interaction', 'label_en' => 'Classroom / session interaction', 'label_ar' => 'التفاعل في الصف / الجلسة', 'weight' => 20, 'order_index' => 1],
            ['key' => 'willingness_to_help', 'label_en' => 'Willingness to help others', 'label_ar' => 'الاستعداد لمساعدة الآخرين', 'weight' => 15, 'order_index' => 2],
            ['key' => 'eagerness_to_learn', 'label_en' => 'Eagerness to learn', 'label_ar' => 'الحماس للتعلّم', 'weight' => 20, 'order_index' => 3],
            ['key' => 'collaboration', 'label_en' => 'Collaboration with colleagues', 'label_ar' => 'التعاون مع الزملاء', 'weight' => 15, 'order_index' => 4],
            ['key' => 'responsibility', 'label_en' => 'Responsibility / follow-through', 'label_ar' => 'المسؤولية والمتابعة', 'weight' => 15, 'order_index' => 5],
            ['key' => 'respect_conduct', 'label_en' => 'Respect & conduct', 'label_ar' => 'الاحترام والسلوك', 'weight' => 15, 'order_index' => 6],
        ];
    }

    public function ensureCriteriaForChurch(int $churchId): Collection
    {
        $existing = AssessmentCriterion::query()
            ->where('church_id', $churchId)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        foreach (self::defaultCriteria() as $row) {
            AssessmentCriterion::query()->firstOrCreate(
                ['church_id' => $churchId, 'key' => $row['key']],
                [
                    'label_en' => $row['label_en'],
                    'label_ar' => $row['label_ar'],
                    'weight' => $row['weight'],
                    'order_index' => $row['order_index'],
                    'is_active' => true,
                ]
            );
        }

        return AssessmentCriterion::query()
            ->where('church_id', $churchId)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get();
    }

    public function churchIdForCourse(Course $course): int
    {
        $churchId = $course->church_id
            ?? $course->service?->church_id
            ?? Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');

        if (! $churchId) {
            throw ValidationException::withMessages([
                'course' => [__('pages.assessment_church_missing')],
            ]);
        }

        return (int) $churchId;
    }

    public function moduleIsEnded(Course $course, Module $module): bool
    {
        $course->loadMissing('modules');
        $attached = $course->modules->firstWhere('module_id', $module->module_id);

        return $attached && ($attached->pivot->status ?? null) === 'ended';
    }

    /**
     * @param  array<int|string,int|string>  $scores  criterion_id => score 0-10
     */
    public function saveAssessment(
        Course $course,
        Module $module,
        User $student,
        User $actor,
        array $scores,
        string $status = ModuleStudentAssessment::STATUS_DRAFT,
    ): ModuleStudentAssessment {
        if (! in_array($status, [ModuleStudentAssessment::STATUS_DRAFT, ModuleStudentAssessment::STATUS_FINAL], true)) {
            throw ValidationException::withMessages([
                'status' => [__('pages.assessment_invalid_status')],
            ]);
        }

        if (! $this->moduleIsEnded($course, $module)) {
            throw ValidationException::withMessages([
                'module' => [__('pages.assessment_module_not_ended')],
            ]);
        }

        $churchId = $this->churchIdForCourse($course);
        $criteria = $this->ensureCriteriaForChurch($churchId);
        $criteriaById = $criteria->keyBy('criterion_id');

        $normalized = [];
        foreach ($scores as $criterionId => $score) {
            $criterionId = (int) $criterionId;
            if (! $criteriaById->has($criterionId)) {
                throw ValidationException::withMessages([
                    'scores' => [__('pages.assessment_unknown_criterion')],
                ]);
            }
            $score = (int) $score;
            if ($score < 0 || $score > 10) {
                throw ValidationException::withMessages([
                    'scores.'.$criterionId => [__('pages.assessment_score_range')],
                ]);
            }
            $normalized[$criterionId] = $score;
        }

        if ($status === ModuleStudentAssessment::STATUS_FINAL) {
            foreach ($criteria as $criterion) {
                if (! array_key_exists($criterion->criterion_id, $normalized)) {
                    throw ValidationException::withMessages([
                        'scores.'.$criterion->criterion_id => [__('pages.assessment_score_required')],
                    ]);
                }
            }
        }

        $total = $this->computeWeightedTotal($criteria, $normalized);

        return DB::transaction(function () use ($churchId, $course, $module, $student, $actor, $normalized, $status, $total) {
            $assessment = ModuleStudentAssessment::query()->updateOrCreate(
                [
                    'church_id' => $churchId,
                    'course_id' => $course->course_id,
                    'module_id' => $module->module_id,
                    'user_id' => $student->user_id,
                ],
                [
                    'total_score' => $total,
                    'status' => $status,
                    'assessed_by_user_id' => $actor->user_id,
                ]
            );

            foreach ($normalized as $criterionId => $score) {
                ModuleStudentAssessmentScore::query()->updateOrCreate(
                    [
                        'assessment_id' => $assessment->assessment_id,
                        'criterion_id' => $criterionId,
                    ],
                    ['score' => $score]
                );
            }

            AuditLogService::recordEvent('module_assessment.saved', [
                'assessment_id' => (int) $assessment->assessment_id,
                'course_id' => (int) $course->course_id,
                'module_id' => (int) $module->module_id,
                'subject_user_id' => (int) $student->user_id,
                'status' => $status,
                'total_score' => $total,
                'church_id' => $churchId,
            ]);

            return $assessment->load(['scores.criterion', 'student']);
        });
    }

    /**
     * @param  Collection<int,AssessmentCriterion>  $criteria
     * @param  array<int,int>  $scores
     */
    public function computeWeightedTotal(Collection $criteria, array $scores): ?int
    {
        if ($scores === []) {
            return null;
        }

        $weightSum = 0;
        $weighted = 0;

        foreach ($criteria as $criterion) {
            if (! array_key_exists($criterion->criterion_id, $scores)) {
                continue;
            }
            $weightSum += (int) $criterion->weight;
            $weighted += (int) $scores[$criterion->criterion_id] * (int) $criterion->weight;
        }

        if ($weightSum <= 0) {
            return null;
        }

        return (int) round(($weighted / $weightSum) * 10);
    }

    public function appendNote(
        User $subject,
        User $actor,
        string $body,
        ?Course $course = null,
        ?Module $module = null,
    ): StudentInstructorNote {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => [__('pages.instructor_note_body_required')],
            ]);
        }

        $churchId = $course
            ? $this->churchIdForCourse($course)
            : (int) (Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id') ?? 0);

        if ($churchId <= 0) {
            throw ValidationException::withMessages([
                'course' => [__('pages.assessment_church_missing')],
            ]);
        }

        $note = StudentInstructorNote::create([
            'church_id' => $churchId,
            'subject_user_id' => $subject->user_id,
            'course_id' => $course?->course_id,
            'module_id' => $module?->module_id,
            'body' => $body,
            'created_by_user_id' => $actor->user_id,
        ]);

        AuditLogService::recordEvent('instructor_note.appended', [
            'note_id' => (int) $note->note_id,
            'subject_user_id' => (int) $subject->user_id,
            'course_id' => $course?->course_id,
            'module_id' => $module?->module_id,
            'church_id' => $churchId,
        ]);

        return $note;
    }

    /**
     * Notes for staff UI — never eager-load author into the view model.
     *
     * @return Collection<int,StudentInstructorNote>
     */
    public function notesForSubject(int $churchId, User $subject, string $filter = 'all', ?Course $course = null, ?Module $module = null): Collection
    {
        $query = StudentInstructorNote::query()
            ->with(['course', 'module'])
            ->where('church_id', $churchId)
            ->where('subject_user_id', $subject->user_id)
            ->orderByDesc('created_at');

        if ($filter === 'course' && $course) {
            $query->where('course_id', $course->course_id);
        } elseif ($filter === 'module' && $module) {
            $query->where('module_id', $module->module_id);
        }

        return $query->get();
    }
}
