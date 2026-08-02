<?php

namespace App\Models;

use App\Support\Sacraments\SacramentDateFormatter;
use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registrar-grade sacrament row (الأسرار). Append-only — never update or delete.
 * Corrections are new rows via corrects_sacrament_id.
 */
class Sacrament extends Model
{
    use BelongsToChurch;

    public const TYPE_BAPTISM = 'baptism';

    public const TYPE_CHRISMATION = 'chrismation';

    public const TYPE_EUCHARIST_FIRST = 'eucharist_first';

    public const TYPE_MARRIAGE = 'marriage';

    public const TYPE_REPOSE = 'repose';

    public const TYPE_ORDINATION = 'ordination';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_BAPTISM,
        self::TYPE_CHRISMATION,
        self::TYPE_EUCHARIST_FIRST,
        self::TYPE_MARRIAGE,
        self::TYPE_REPOSE,
        self::TYPE_ORDINATION,
    ];

    /** Types with UI in this slice (schema still accepts the full set). */
    public const UI_TYPES = [
        self::TYPE_BAPTISM,
        self::TYPE_MARRIAGE,
        self::TYPE_REPOSE,
    ];

    public const PRECISION_DAY = 'day';

    public const PRECISION_MONTH = 'month';

    public const PRECISION_YEAR = 'year';

    /** @var list<string> */
    public const PRECISIONS = [
        self::PRECISION_DAY,
        self::PRECISION_MONTH,
        self::PRECISION_YEAR,
    ];

    public $timestamps = false;

    protected $table = 'sacraments';

    protected $primaryKey = 'sacrament_id';

    protected $fillable = [
        'church_id',
        'person_id',
        'type',
        'date',
        'date_precision',
        'location_church_id',
        'location_text',
        'officiant_person_id',
        'second_person_id',
        'certificate_document_id',
        'recorded_by',
        'recorded_at',
        'corrects_sacrament_id',
        'created_at',
    ];

    protected $casts = [
        'date' => 'date',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'church_id' => 'integer',
        'person_id' => 'integer',
        'location_church_id' => 'integer',
        'officiant_person_id' => 'integer',
        'second_person_id' => 'integer',
        'certificate_document_id' => 'integer',
        'recorded_by' => 'integer',
        'corrects_sacrament_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('sacraments is append-only; updates are forbidden. Use SacramentRepository::correct().');
        });

        static::deleting(function () {
            throw new LogicException('sacraments is append-only; deletes are forbidden.');
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function officiant(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'officiant_person_id', 'person_id');
    }

    public function secondPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'second_person_id', 'person_id');
    }

    public function locationChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'location_church_id', 'church_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_sacrament_id', 'sacrament_id');
    }

    public function formattedDate(?string $locale = null): string
    {
        return SacramentDateFormatter::format($this, $locale);
    }

    public function typeLabel(): string
    {
        return __('sacraments.types.'.$this->type);
    }
}
