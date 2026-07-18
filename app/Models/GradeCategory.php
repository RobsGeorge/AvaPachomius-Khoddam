<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeCategory extends Model
{
    protected $primaryKey = 'category_id';

    protected $fillable = ['course_id', 'type', 'name', 'weight_percentage', 'ordering'];

    protected $casts = ['weight_percentage' => 'float'];

    public static array $types = [
        'exam'         => 'امتحانات',
        'quiz'         => 'اختبارات قصيرة',
        'presentation' => 'عروض تقديمية',
        'project'      => 'مشاريع',
        'attendance'   => 'حضور',
        'other'        => 'أخرى',
    ];

    public static array $typeColors = [
        'exam'         => 'danger',
        'quiz'         => 'warning',
        'presentation' => 'info',
        'project'      => 'success',
        'attendance'   => 'primary',
        'other'        => 'secondary',
    ];

    public static array $typeIcons = [
        'exam'         => 'bi-journal-check',
        'quiz'         => 'bi-pencil-square',
        'presentation' => 'bi-easel2',
        'project'      => 'bi-kanban',
        'attendance'   => 'bi-person-check',
        'other'        => 'bi-three-dots',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function items()
    {
        return $this->hasMany(GradeItem::class, 'category_id', 'category_id')
                    ->orderBy('ordering')
                    ->orderBy('item_date');
    }

    // ── Calculation helpers (call after eager-loading items.grades) ──────────

    public function maxRawScore(): float
    {
        return (float) $this->items->sum('max_score');
    }

    public function studentRawScore(int $userId): float
    {
        return $this->items->sum(function (GradeItem $item) use ($userId) {
            $grade = $item->grades->firstWhere('user_id', $userId);
            return $grade ? (float) ($grade->score ?? 0) : 0.0;
        });
    }

    /** Percentage the student achieved within this category (0–100) */
    public function studentCategoryPercentage(int $userId): float
    {
        $max = $this->maxRawScore();
        if ($max == 0) return 0;
        return round(($this->studentRawScore($userId) / $max) * 100, 2);
    }

    /** Weighted contribution towards the total course grade */
    public function studentContribution(int $userId): float
    {
        $max = $this->maxRawScore();
        if ($max == 0) return 0;
        return round(($this->studentRawScore($userId) / $max) * $this->weight_percentage, 2);
    }

    public function gradedCount(int $userId = null): int
    {
        return $this->items->sum(function (GradeItem $item) use ($userId) {
            if ($userId !== null) {
                return $item->grades->where('user_id', $userId)->whereNotNull('score')->count();
            }
            return $item->grades->whereNotNull('score')->count();
        });
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function letterGrade(float $total): string
    {
        return match (true) {
            $total >= 95 => 'A+',
            $total >= 90 => 'A',
            $total >= 85 => 'B+',
            $total >= 80 => 'B',
            $total >= 75 => 'C+',
            $total >= 70 => 'C',
            $total >= 60 => 'D',
            default      => 'F',
        };
    }

    public static function letterGradeAr(float $total): string
    {
        return match (true) {
            $total >= 95 => 'ممتاز+',
            $total >= 90 => 'ممتاز',
            $total >= 85 => 'جيد جداً+',
            $total >= 80 => 'جيد جداً',
            $total >= 75 => 'جيد+',
            $total >= 70 => 'جيد',
            $total >= 60 => 'مقبول',
            default      => 'راسب',
        };
    }

    public static function gradeColor(float $total): string
    {
        return match (true) {
            $total >= 85 => 'success',
            $total >= 70 => 'primary',
            $total >= 60 => 'warning',
            default      => 'danger',
        };
    }
}
