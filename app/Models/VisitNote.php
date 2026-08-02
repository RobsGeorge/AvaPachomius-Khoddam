<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pastoral افتقاد note — append-only. Corrections are new rows via corrects_visit_note_id.
 * Visibility is NEVER via raw queries; use VisitNoteVisibility.
 */
class VisitNote extends Model
{
    use BelongsToChurch;

    public $timestamps = false;

    protected $table = 'visit_notes';

    protected $primaryKey = 'visit_note_id';

    protected $fillable = [
        'church_id',
        'home_visit_id',
        'author_user_id',
        'body',
        'corrects_visit_note_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('visit_notes are append-only; corrections must insert a new row.');
        });

        static::deleting(function () {
            throw new LogicException('visit_notes are append-only; never delete pastoral notes.');
        });
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(HomeVisit::class, 'home_visit_id', 'home_visit_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id', 'user_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_visit_note_id', 'visit_note_id');
    }
}
