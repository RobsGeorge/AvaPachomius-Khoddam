<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T8a expand — service_units dual-write target + Tenant Zero servants-prep backfill.
 * Courses remain source of truth; units link via nullable course_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('service_units', function (Blueprint $table) {
            $table->id('service_unit_id');
            $table->unsignedBigInteger('service_id')->index();
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('level_key', 64);
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('title', 191);
            $table->string('title_ar', 191)->nullable();
            $table->string('title_en', 191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('course_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['service_id', 'level_key', 'course_id'], 'service_units_service_level_course_unique');
        });

        if (! Schema::hasTable('service') || ! Schema::hasTable('structure_templates')) {
            return;
        }

        $templateId = DB::table('structure_templates')
            ->where('key', 'educational_standard')
            ->value('structure_template_id');

        if (! $templateId) {
            return;
        }

        $anchors = DB::table('structure_templates')
            ->where('structure_template_id', $templateId)
            ->value('anchors');
        $anchors = is_string($anchors) ? json_decode($anchors, true) : (array) $anchors;
        $enrollmentLevel = (string) ($anchors['enrollment_level'] ?? 'cohort');

        $mainChurchId = null;
        if (Schema::hasTable('church')) {
            $mainChurchId = DB::table('church')
                ->where('slug', config('tenancy.main_slug', 'avapakhomios'))
                ->value('church_id')
                ?? DB::table('church')->orderBy('church_id')->value('church_id');
        }

        $serviceQuery = DB::table('service')->orderBy('service_id');
        if ($mainChurchId && Schema::hasColumn('service', 'church_id')) {
            $service = (clone $serviceQuery)->where('church_id', $mainChurchId)->first()
                ?? $serviceQuery->first();
        } else {
            $service = $serviceQuery->first();
        }

        if (! $service) {
            return;
        }

        $updates = [
            'slug' => 'servants-prep',
            'structure_template_id' => $templateId,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('service', 'church_id') && $mainChurchId && empty($service->church_id)) {
            $updates['church_id'] = $mainChurchId;
        }

        DB::table('service')->where('service_id', $service->service_id)->update($updates);

        if (! Schema::hasTable('course') || ! Schema::hasColumn('course', 'service_id')) {
            return;
        }

        $courses = DB::table('course')
            ->where('service_id', $service->service_id)
            ->orderBy('course_id')
            ->get();

        $sort = 0;
        foreach ($courses as $course) {
            $exists = DB::table('service_units')
                ->where('service_id', $service->service_id)
                ->where('level_key', $enrollmentLevel)
                ->where('course_id', $course->course_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $title = (string) ($course->title ?? ('Course '.$course->course_id));
            DB::table('service_units')->insert([
                'service_id' => $service->service_id,
                'church_id' => $mainChurchId ?? ($service->church_id ?? null),
                'level_key' => $enrollmentLevel,
                'parent_id' => null,
                'title' => mb_substr($title, 0, 191),
                'title_ar' => isset($course->title_ar) ? mb_substr((string) $course->title_ar, 0, 191) : null,
                'title_en' => isset($course->title_en) ? mb_substr((string) $course->title_en, 0, 191) : null,
                'sort_order' => $sort++,
                'course_id' => $course->course_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Expand-only: leave units and servants-prep stamp.
    }
};
