<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeopleImportRow extends Model
{
    public const ACTION_PENDING = 'pending';

    public const ACTION_CREATED = 'created';

    public const ACTION_LINKED = 'linked';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_ERROR = 'error';

    protected $table = 'people_import_rows';

    protected $primaryKey = 'people_import_row_id';

    protected $fillable = [
        'people_import_batch_id',
        'row_number',
        'raw',
        'person_id',
        'person_placement_id',
        'match_action',
        'role_slug',
        'intended_role_id',
        'portal_intent',
        'invite_eligible',
        'invite_selected',
        'error_message',
    ];

    protected $casts = [
        'raw' => 'array',
        'invite_eligible' => 'boolean',
        'invite_selected' => 'boolean',
        'row_number' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PeopleImportBatch::class, 'people_import_batch_id', 'people_import_batch_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(PersonPlacement::class, 'person_placement_id', 'person_placement_id');
    }
}
