<?php

namespace App\Services\Structure;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\ServiceUnit;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-write course rows into service_units (T8a). Course remains source of truth.
 */
class ServiceUnitDualWrite
{
    public function __construct(
        private StructureAnchorResolver $anchors,
    ) {}

    public function syncFromCourse(Course $course): ?ServiceUnit
    {
        if (! Schema::hasTable('service_units') || ! $course->service_id) {
            return null;
        }

        $service = ChurchService::query()->find($course->service_id);
        if (! $service) {
            return null;
        }

        $levelKey = $this->anchors->enrollmentLevel($service) ?? 'cohort';

        $unit = ServiceUnit::query()->firstOrNew([
            'service_id' => $service->service_id,
            'level_key' => $levelKey,
            'course_id' => $course->course_id,
        ]);

        $unit->fill([
            'church_id' => $course->church_id ?? $service->church_id,
            'title' => mb_substr((string) ($course->title ?? 'Course'), 0, 191),
            'title_ar' => $course->title_ar ? mb_substr((string) $course->title_ar, 0, 191) : null,
            'title_en' => $course->title_en ? mb_substr((string) $course->title_en, 0, 191) : null,
            'sort_order' => (int) ($unit->sort_order ?: $course->course_id),
        ]);
        $unit->save();

        return $unit->fresh();
    }

    public function removeForCourse(Course $course): void
    {
        if (! Schema::hasTable('service_units') || ! $course->course_id) {
            return;
        }

        ServiceUnit::query()->where('course_id', $course->course_id)->delete();
    }
}
