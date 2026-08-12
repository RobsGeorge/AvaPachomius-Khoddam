<?php

namespace Tests\Unit\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\Session;
use App\Services\AttendanceLatePolicyService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pure scoring / factor math for the late attendance policy (e.g. 50% credit).
 */
class LatePolicyTest extends TestCase
{
    #[DataProvider('scoreCases')]
    public function test_score_for_status_applies_late_percentage(
        string $status,
        float $maxScore,
        float $latePercent,
        float $expected,
    ): void {
        $policy = new AttendancePolicy([
            'late_grade_percentage' => $latePercent,
            'is_enabled' => true,
        ]);

        $score = app(AttendanceLatePolicyService::class)
            ->scoreForStatus($status, $maxScore, $policy);

        $this->assertSame($expected, $score);
    }

    /** @return array<string, array{0: string, 1: float, 2: float, 3: float}> */
    public static function scoreCases(): array
    {
        return [
            'present_full' => ['Present', 100.0, 50.0, 100.0],
            'permission_full' => ['Permission', 100.0, 50.0, 100.0],
            'late_half_of_100' => ['Late', 100.0, 50.0, 50.0],
            'late_half_of_80' => ['Late', 80.0, 50.0, 40.0],
            'late_25_percent' => ['Late', 100.0, 25.0, 25.0],
            'late_zero_policy' => ['Late', 100.0, 0.0, 0.0],
            'absent_zero' => ['Absent', 100.0, 50.0, 0.0],
        ];
    }

    public function test_late_attendance_factor_is_half_when_policy_is_50_percent(): void
    {
        $policy = new AttendancePolicy([
            'late_grade_percentage' => 50.0,
            'is_enabled' => true,
        ]);

        $this->assertSame(0.5, $policy->lateAttendanceFactor());
    }

    public function test_late_attendance_factor_is_zero_when_policy_disabled(): void
    {
        $policy = new AttendancePolicy([
            'late_grade_percentage' => 50.0,
            'is_enabled' => false,
        ]);

        $this->assertSame(0.0, $policy->lateAttendanceFactor());
    }

    public function test_exact_deadline_is_not_late_but_one_second_after_is(): void
    {
        $tz = config('attendance.timezone', 'Africa/Cairo');
        $policy = new AttendancePolicy([
            'late_threshold_minutes' => 15,
            'late_grade_percentage' => 50.0,
            'is_enabled' => true,
        ]);

        $session = new Session([
            'session_date' => '2026-03-10',
            'session_start_time' => '09:00:00',
        ]);

        $service = app(AttendanceLatePolicyService::class);

        // Store UTC instants (app timezone) corresponding to Cairo wall-clock times.
        $onTime = new Attendance;
        $onTime->setRawAttributes([
            'attendance_time' => Carbon::parse('2026-03-10 09:15:00', $tz)->utc()->format('Y-m-d H:i:s'),
        ]);
        $onTime->syncOriginal();

        $late = new Attendance;
        $late->setRawAttributes([
            'attendance_time' => Carbon::parse('2026-03-10 09:15:01', $tz)->utc()->format('Y-m-d H:i:s'),
        ]);
        $late->syncOriginal();

        $this->assertFalse($service->isLateAttendance($onTime, $session, $policy));
        $this->assertTrue($service->isLateAttendance($late, $session, $policy));
    }
}
