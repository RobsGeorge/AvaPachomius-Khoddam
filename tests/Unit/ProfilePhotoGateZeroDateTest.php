<?php

namespace Tests\Unit;

use App\Models\PortalSettings;
use App\Services\ProfilePhotoGateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class ProfilePhotoGateZeroDateTest extends EventModuleTestCase
{
    public function test_safe_date_treats_mysql_zero_dates_as_null(): void
    {
        $gate = app(ProfilePhotoGateService::class);
        $student = $this->createUser(['email' => 'zero-date-unit@example.com', 'profile_photo' => '']);

        $student->setRawAttributes(array_merge($student->getAttributes(), [
            'profile_photo_deadline_at' => '0000-00-00 00:00:00',
            'profile_photo_grace_started_at' => '0000-00-00 00:00:00',
        ]));
        $student->syncOriginal();

        $this->assertNull($gate->safeDate($student, 'profile_photo_deadline_at'));
        $this->assertNull($gate->safeDate($student, 'profile_photo_grace_started_at'));
    }

    public function test_ensure_grace_started_heals_zero_dates_without_throwing(): void
    {
        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
            'profile_photo_gate_enabled_at' => now()->subDays(2),
        ])->save();

        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'zero-date-heal@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Zero Date Heal']);
        $this->assignCourseRole($student, $course, $studentRole);

        DB::table('user')->where('user_id', $student->user_id)->update([
            'profile_photo_grace_started_at' => '0000-00-00 00:00:00',
            'profile_photo_deadline_at' => '0000-00-00 00:00:00',
        ]);

        $gate = app(ProfilePhotoGateService::class);
        $fresh = $student->fresh();

        $gate->ensureGraceStarted($fresh);

        $fresh->refresh();
        $rawGrace = $fresh->getAttributes()['profile_photo_grace_started_at'] ?? null;
        $this->assertNotNull($rawGrace);
        $this->assertFalse(is_string($rawGrace) && str_starts_with($rawGrace, '0000-00-00'));
        $this->assertNotNull($gate->safeDate($fresh, 'profile_photo_grace_started_at'));
    }

    public function test_portal_settings_current_heals_zero_enabled_at(): void
    {
        if (! Schema::hasColumn('portal_settings', 'profile_photo_gate_enabled_at')) {
            $this->markTestSkipped('profile_photo_gate_enabled_at column missing');
        }

        PortalSettings::current()->forceFill([
            'profile_photo_gate_enabled' => true,
            'profile_photo_grace_days' => 3,
        ])->save();

        DB::table('portal_settings')->where('id', 1)->update([
            'profile_photo_gate_enabled_at' => '0000-00-00 00:00:00',
        ]);

        $healed = PortalSettings::current();
        $raw = $healed->getAttributes()['profile_photo_gate_enabled_at'] ?? null;
        $this->assertTrue($raw === null || $raw === '');
        $this->assertNull(app(ProfilePhotoGateService::class)->safeSettingsDate('profile_photo_gate_enabled_at'));
    }
}
