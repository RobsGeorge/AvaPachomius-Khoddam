<?php

namespace App\Models;

use App\Support\Structure\SchoolYearStatus;
use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChurchSchoolYear extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'church_school_year';

    protected $primaryKey = 'church_school_year_id';

    public function getRouteKeyName(): string
    {
        return 'church_school_year_id';
    }

    protected $fillable = [
        'label',
        'starts_on',
        'ends_on',
        'status',
        'promotion_started_at',
        'closed_at',
        'completed_service_ids',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'promotion_started_at' => 'datetime',
        'closed_at' => 'datetime',
        'completed_service_ids' => 'array',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(ChurchService::class, 'church_school_year_id', 'church_school_year_id');
    }

    public function isClosing(): bool
    {
        return $this->status === SchoolYearStatus::CLOSING;
    }

    public function isClosed(): bool
    {
        return $this->status === SchoolYearStatus::CLOSED;
    }

    /** @return list<int> */
    public function completedServiceIds(): array
    {
        $ids = $this->completed_service_ids;

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function hasCompletedService(int $serviceId): bool
    {
        return in_array($serviceId, $this->completedServiceIds(), true);
    }

    public function markServiceCompleted(int $serviceId): void
    {
        $ids = $this->completedServiceIds();
        if (! in_array($serviceId, $ids, true)) {
            $ids[] = $serviceId;
            $this->completed_service_ids = $ids;
            $this->save();
        }
    }
}
