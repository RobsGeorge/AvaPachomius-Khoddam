<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T8b expand — enrollments dual-write target beside user_course_role (reads stay on UCR).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('enrollments', function (Blueprint $table) {
            $table->id('enrollment_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('role_id')->index();
            $table->unsignedBigInteger('service_unit_id')->nullable()->index();
            $table->unsignedBigInteger('user_course_role_id')->nullable()->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->unique(['user_id', 'course_id', 'role_id'], 'enrollments_user_course_role_unique');
        });

        if (! Schema::hasTable('user_course_role') || ! Schema::hasTable('enrollments')) {
            return;
        }

        $hasChurch = Schema::hasColumn('user_course_role', 'church_id');
        $hasStaffArchived = Schema::hasColumn('user_course_role', 'staff_archived_at');
        $hasUnits = Schema::hasTable('service_units');

        DB::table('user_course_role')->orderBy('user_course_role_id')->chunkById(200, function ($rows) use ($hasChurch, $hasStaffArchived, $hasUnits) {
            $now = now();
            $payload = [];

            foreach ($rows as $row) {
                $serviceUnitId = null;
                if ($hasUnits) {
                    $serviceUnitId = DB::table('service_units')
                        ->where('course_id', $row->course_id)
                        ->value('service_unit_id');
                }

                $status = 'active';
                if ($hasStaffArchived && ! empty($row->staff_archived_at)) {
                    $status = 'archived';
                }

                $payload[] = [
                    'church_id' => $hasChurch ? ($row->church_id ?? null) : null,
                    'user_id' => $row->user_id,
                    'course_id' => $row->course_id,
                    'role_id' => $row->role_id,
                    'service_unit_id' => $serviceUnitId,
                    'user_course_role_id' => $row->user_course_role_id,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($payload === []) {
                return;
            }

            foreach ($payload as $row) {
                $exists = DB::table('enrollments')
                    ->where('user_course_role_id', $row['user_course_role_id'])
                    ->exists();
                if ($exists) {
                    continue;
                }

                try {
                    DB::table('enrollments')->insert($row);
                } catch (\Throwable) {
                    // Unique collision on replay — ignore.
                }
            }
        }, 'user_course_role_id');
    }

    public function down(): void
    {
        // Expand-only.
    }
};
