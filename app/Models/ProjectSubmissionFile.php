<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectSubmissionFile extends Model
{
    use BelongsToChurch;

    protected $table = 'project_submission_files';

    protected $primaryKey = 'project_submission_file_id';

    protected $fillable = [
        'project_deliverable_submission_id',
        'uploaded_by_user_id',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(
            ProjectDeliverableSubmission::class,
            'project_deliverable_submission_id',
            'project_deliverable_submission_id'
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id', 'user_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function displayName(): string
    {
        return $this->original_name ?: basename((string) $this->file_path);
    }
}
