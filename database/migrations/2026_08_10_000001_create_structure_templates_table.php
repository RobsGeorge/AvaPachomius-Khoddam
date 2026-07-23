<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T8a expand — structure template registry (master-plan §15 anchors).
 * Seeds educational_standard, meeting_flat, care_sector. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('structure_templates', function (Blueprint $table) {
            $table->id('structure_template_id');
            $table->string('key', 64)->unique();
            $table->string('name_ar', 191);
            $table->string('name_en', 191);
            $table->json('levels');
            $table->json('anchors');
            $table->json('custom_field_defs')->nullable();
            $table->timestamps();
        });

        $now = now();
        $templates = [
            [
                'key' => 'educational_standard',
                'name_ar' => 'المسار التعليمي القياسي',
                'name_en' => 'Educational standard',
                'levels' => [
                    ['key' => 'cohort', 'label_ar' => 'دفعة', 'label_en' => 'Cohort'],
                    ['key' => 'unit', 'label_ar' => 'وحدة', 'label_en' => 'Unit'],
                    ['key' => 'session', 'label_ar' => 'جلسة', 'label_en' => 'Session'],
                ],
                'anchors' => [
                    'enrollment_level' => 'cohort',
                    'attendance_level' => 'session',
                    'assignment_levels' => ['unit'],
                    'report_rollup' => 'cohort',
                ],
            ],
            [
                'key' => 'meeting_flat',
                'name_ar' => 'اجتماع مسطح',
                'name_en' => 'Flat meeting',
                'levels' => [
                    ['key' => 'meeting', 'label_ar' => 'اجتماع', 'label_en' => 'Meeting'],
                ],
                'anchors' => [
                    'enrollment_level' => 'meeting',
                    'attendance_level' => 'meeting',
                    'assignment_levels' => [],
                    'report_rollup' => 'meeting',
                ],
            ],
            [
                'key' => 'care_sector',
                'name_ar' => 'قطاع رعاية',
                'name_en' => 'Care sector',
                'levels' => [
                    ['key' => 'sector', 'label_ar' => 'قطاع', 'label_en' => 'Sector'],
                    ['key' => 'household', 'label_ar' => 'أسرة', 'label_en' => 'Household'],
                ],
                'anchors' => [
                    'enrollment_level' => 'sector',
                    'attendance_level' => 'household',
                    'assignment_levels' => [],
                    'report_rollup' => 'sector',
                ],
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('structure_templates')->where('key', $template['key'])->exists();
            if ($exists) {
                DB::table('structure_templates')->where('key', $template['key'])->update([
                    'name_ar' => $template['name_ar'],
                    'name_en' => $template['name_en'],
                    'levels' => json_encode($template['levels'], JSON_UNESCAPED_UNICODE),
                    'anchors' => json_encode($template['anchors'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('structure_templates')->insert([
                'key' => $template['key'],
                'name_ar' => $template['name_ar'],
                'name_en' => $template['name_en'],
                'levels' => json_encode($template['levels'], JSON_UNESCAPED_UNICODE),
                'anchors' => json_encode($template['anchors'], JSON_UNESCAPED_UNICODE),
                'custom_field_defs' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Expand-only: leave seeded registry.
    }
};
