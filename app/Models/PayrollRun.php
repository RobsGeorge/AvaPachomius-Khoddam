<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    protected $table = 'payroll_run';

    protected $primaryKey = 'payroll_run_id';

    public function getRouteKeyName(): string
    {
        return 'payroll_run_id';
    }

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'currency',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
