<?php

namespace App\Models;

use App\Support\People\PlacementMode;
use App\Support\Structure\RosterStatus;
use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonPlacement extends Model
{
    use BelongsToChurch;

    protected $table = 'person_placements';

    protected $primaryKey = 'person_placement_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'service_id',
        'service_unit_id',
        'course_id',
        'roster_status',
        'intended_role_id',
        'placement_mode',
        'status_note',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function intendedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'intended_role_id', 'role_id');
    }

    public function isInfoOnly(): bool
    {
        return $this->placement_mode === PlacementMode::INFO_ONLY;
    }

    public function markPortalPending(): void
    {
        $this->forceFill(['placement_mode' => PlacementMode::PORTAL_PENDING])->save();
    }

    public function markPortalActive(): void
    {
        $this->forceFill(['placement_mode' => PlacementMode::PORTAL_ACTIVE])->save();
    }

    public static function defaultRosterStatus(): string
    {
        return RosterStatus::ACTIVE;
    }
}
