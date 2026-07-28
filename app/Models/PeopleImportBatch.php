<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeopleImportBatch extends Model
{
    use BelongsToChurch;

    public const STATUS_PREVIEW = 'preview';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'people_import_batches';

    protected $primaryKey = 'people_import_batch_id';

    protected $fillable = [
        'church_id',
        'service_id',
        'course_id',
        'uploaded_by_user_id',
        'original_filename',
        'template_version',
        'status',
        'row_count',
        'created_count',
        'linked_count',
        'error_count',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'created_count' => 'integer',
        'linked_count' => 'integer',
        'error_count' => 'integer',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(PeopleImportRow::class, 'people_import_batch_id', 'people_import_batch_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id', 'user_id');
    }
}
