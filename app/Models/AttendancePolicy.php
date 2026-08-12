<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AttendancePolicy extends Model
{
    use BelongsToChurch;

    protected $table = 'attendance_policy';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'late_threshold_minutes',
        'late_grade_percentage',
        'is_enabled',
    ];

    protected $casts = [
        'late_threshold_minutes' => 'integer',
        'late_grade_percentage' => 'float',
        'is_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        $defaults = [
            'late_threshold_minutes' => (int) config('attendance.late_threshold_minutes', 15),
            'late_grade_percentage' => (float) config('attendance.late_grade_percentage', 50),
            'is_enabled' => true,
        ];

        // Singleton row (id=1). Bypass the church scope so a legacy NULL church_id
        // seed remains visible when Tenant Zero is bound — otherwise firstOrCreate
        // misses the row and dies on duplicate primary key during close-attendance.
        $policy = static::withoutTenancy()->firstOrCreate(['id' => 1], $defaults);

        if (Schema::hasColumn($policy->getTable(), 'church_id') && $policy->church_id === null) {
            $churchId = app(TenantContext::class)->churchId()
                ?? TenantContext::id()
                ?? Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');

            if ($churchId !== null) {
                $policy->forceFill(['church_id' => $churchId])->saveQuietly();
            }
        }

        return $policy;
    }

    /**
     * Weighted attendance credit for a Late status (0–1).
     * Used by graduation eligibility and the attendance report rate.
     */
    public function lateAttendanceFactor(): float
    {
        if (! $this->is_enabled) {
            return 0.0;
        }

        return max(0.0, min(1.0, $this->late_grade_percentage / 100));
    }
}
