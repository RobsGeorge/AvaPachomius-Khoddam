<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic pastoral/custodial document (ADR §27 / Slice 10).
 * Sensitive rows store ciphertext only; never expose key material via API/JSON.
 */
class Document extends Model
{
    use BelongsToChurch;
    use BelongsToTenant;

    public const LAYER_CUSTODIAL = 'custodial';

    public const LAYER_PASTORAL = 'pastoral';

    public const LAYER_SACRAMENTAL = 'sacramental';

    /** @var list<string> */
    public const LAYERS = [
        self::LAYER_CUSTODIAL,
        self::LAYER_PASTORAL,
        self::LAYER_SACRAMENTAL,
    ];

    public const DOCUMENTABLE_PERSON = 'person';

    public const DOCUMENTABLE_RESIDENCE = 'residence';

    public const DOCUMENTABLE_SACRAMENT = 'sacrament';

    public const DOCUMENTABLE_VISIT = 'visit';

    /** @var list<string> */
    public const DOCUMENTABLE_TYPES = [
        self::DOCUMENTABLE_PERSON,
        self::DOCUMENTABLE_RESIDENCE,
        self::DOCUMENTABLE_SACRAMENT,
        self::DOCUMENTABLE_VISIT,
    ];

    public $timestamps = false;

    protected $table = 'documents';

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'church_id',
        'documentable_type',
        'documentable_id',
        'kind',
        'storage_ref',
        'is_sensitive',
        'encryption_key_ref',
        'visibility_layer',
        'uploaded_by',
        'uploaded_at',
        'created_at',
    ];

    protected $hidden = [
        'encryption_key_ref',
        'storage_ref',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'church_id' => 'integer',
        'documentable_id' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    /**
     * ADR §8 — encryption breaks the AI wedge; sensitive docs stay invisible to AI.
     */
    public function isReadableByAi(): bool
    {
        return ! $this->is_sensitive;
    }
}
