<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'payroll_line';

    protected $primaryKey = 'payroll_line_id';

    public function getRouteKeyName(): string
    {
        return 'payroll_line_id';
    }

    protected $fillable = [
        'payroll_run_id',
        'user_id',
        'gross_minor',
        'deductions_minor',
        'net_minor',
        'currency',
        'fx_rate',
        'notes',
    ];

    protected $casts = [
        'gross_minor' => 'integer',
        'deductions_minor' => 'integer',
        'net_minor' => 'integer',
        // Decimal kept as string — never cast to float (money rule 7).
        'fx_rate' => 'string',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
