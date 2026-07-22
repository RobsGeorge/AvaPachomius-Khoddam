<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive heal: MySQL zero-dates on hot-path timestamp columns become NULL
 * so Eloquent datetime casts cannot 500 every authenticated request.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnsByTable = [
            'user' => [
                'student_onboarding_completed_at',
                'profile_photo_grace_started_at',
                'profile_photo_uploaded_at',
                'profile_photo_deadline_at',
                'profile_photo_reviewed_at',
                'otp_expires_at',
            ],
            'user_course_role' => [
                'staff_archived_at',
            ],
            'announcement_deliveries' => [
                'read_at',
                'opened_at',
                'dismissed_at',
                'email_sent_at',
                'whatsapp_sent_at',
            ],
            'portal_settings' => [
                'profile_photo_gate_enabled_at',
            ],
            'feedback_surveys' => [
                'due_at',
                'opened_at',
                'closed_at',
            ],
        ];

        foreach ($columnsByTable as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, 'like', '0000-00-00%')
                    ->update([$column => null]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible heal — zero-dates must not be restored.
    }
};
