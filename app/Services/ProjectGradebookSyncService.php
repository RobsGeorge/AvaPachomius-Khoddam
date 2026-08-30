<?php

namespace App\Services;

use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\ProjectAssessment;
use App\Models\ProjectMemberGrade;
use App\Models\StudentGrade;
use App\Models\User;

/**
 * Mirrors announced project grades into the course gradebook, following the
 * attendance sync pattern: find the category by type, keep one grade item per
 * assessment, then upsert one student grade per member.
 */
class ProjectGradebookSyncService
{
    public const CATEGORY_TYPE = 'project';

    /**
     * @return array{synced:int, skipped:?string}
     */
    public function sync(ProjectAssessment $assessment, User $actor): array
    {
        if (! $assessment->sync_to_gradebook) {
            return ['synced' => 0, 'skipped' => 'disabled'];
        }

        if (! $assessment->course_id) {
            return ['synced' => 0, 'skipped' => 'no_course'];
        }

        $category = GradeCategory::query()
            ->where('course_id', $assessment->course_id)
            ->where('type', self::CATEGORY_TYPE)
            ->orderBy('ordering')
            ->orderBy('category_id')
            ->first();

        if (! $category) {
            return ['synced' => 0, 'skipped' => 'no_category'];
        }

        $maxScore = round((float) app(ProjectGradingService::class)->maxPoints($assessment), 2);
        if ($maxScore <= 0) {
            return ['synced' => 0, 'skipped' => 'no_max_points'];
        }

        $item = $this->gradeItemFor($assessment, $category, $maxScore);

        $grades = ProjectMemberGrade::query()
            ->where('project_assessment_id', $assessment->project_assessment_id)
            ->get();

        $now = now();
        $synced = 0;
        foreach ($grades as $grade) {
            if (! $grade->user_id) {
                continue;
            }

            StudentGrade::updateOrCreate(
                [
                    'item_id' => $item->item_id,
                    'user_id' => $grade->user_id,
                ],
                [
                    'score' => round((float) $grade->points, 2),
                    'graded_by_id' => $actor->user_id,
                    'graded_at' => $now,
                ]
            );
            $synced++;
        }

        $assessment->update([
            'gradebook_item_id' => $item->item_id,
            'gradebook_synced_at' => $now,
        ]);

        AuditLogService::recordEvent('project.gradebook_synced', [
            'project_assessment_id' => $assessment->project_assessment_id,
            'course_id' => $assessment->course_id,
            'item_id' => $item->item_id,
            'student_count' => $synced,
            'actor_user_id' => $actor->user_id,
        ]);

        return ['synced' => $synced, 'skipped' => null];
    }

    private function gradeItemFor(ProjectAssessment $assessment, GradeCategory $category, float $maxScore): GradeItem
    {
        $existing = $assessment->gradebook_item_id
            ? GradeItem::query()->whereKey($assessment->gradebook_item_id)->first()
            : null;

        if ($existing) {
            $existing->update([
                'title' => $assessment->title,
                'max_score' => $maxScore,
            ]);

            return $existing;
        }

        return GradeItem::create([
            'category_id' => $category->category_id,
            'title' => $assessment->title,
            'max_score' => $maxScore,
            'item_date' => ($assessment->results_announced_at ?? now())->toDateString(),
            'ordering' => (int) GradeItem::query()->where('category_id', $category->category_id)->max('ordering') + 1,
        ]);
    }
}
