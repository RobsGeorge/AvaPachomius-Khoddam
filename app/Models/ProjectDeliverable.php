<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDeliverable extends Model
{
    use BelongsToChurch;

    public const TYPE_PDF = 'pdf';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_IMAGE = 'image';

    public const TYPE_ZIP = 'zip';

    public const TYPE_LINK = 'link';

    public const TYPE_TEXT = 'text';

    public const FILE_MODE_SINGLE = 'single';

    public const FILE_MODE_MULTI = 'multi';

    /** Mirrors the assignment upload ceiling (10 MB, public disk). */
    public const MAX_UPLOAD_KB = 10240;

    public const MAX_FILES = 10;

    protected $table = 'project_deliverables';

    protected $primaryKey = 'project_deliverable_id';

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'instructions',
        'due_at',
        'sort_order',
        'submission_type',
        'file_mode',
        'is_required',
        'allow_late',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'allow_late' => 'boolean',
    ];

    /**
     * @return list<string>
     */
    public static function submissionTypes(): array
    {
        return [
            self::TYPE_PDF,
            self::TYPE_DOCUMENT,
            self::TYPE_IMAGE,
            self::TYPE_ZIP,
            self::TYPE_LINK,
            self::TYPE_TEXT,
        ];
    }

    /**
     * @return list<string>
     */
    public static function fileModes(): array
    {
        return [self::FILE_MODE_SINGLE, self::FILE_MODE_MULTI];
    }

    /**
     * Accepted extensions per submission type, in the `mimes:` rule format.
     *
     * @return list<string>
     */
    public static function extensionsFor(string $submissionType): array
    {
        return match ($submissionType) {
            self::TYPE_DOCUMENT => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'ppt', 'pptx', 'xls', 'xlsx'],
            self::TYPE_IMAGE => ['jpg', 'jpeg', 'png', 'webp', 'heic'],
            self::TYPE_ZIP => ['zip'],
            default => ['pdf'],
        };
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(
            ProjectDeliverableSubmission::class,
            'project_deliverable_id',
            'project_deliverable_id'
        );
    }

    public function type(): string
    {
        return $this->submission_type ?: self::TYPE_PDF;
    }

    public function expectsFiles(): bool
    {
        return ! in_array($this->type(), [self::TYPE_LINK, self::TYPE_TEXT], true);
    }

    public function expectsLink(): bool
    {
        return $this->type() === self::TYPE_LINK;
    }

    public function expectsText(): bool
    {
        return $this->type() === self::TYPE_TEXT;
    }

    public function allowsMultipleFiles(): bool
    {
        return $this->expectsFiles() && ($this->file_mode ?? self::FILE_MODE_SINGLE) === self::FILE_MODE_MULTI;
    }

    public function maxFiles(): int
    {
        return $this->allowsMultipleFiles() ? self::MAX_FILES : 1;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Late submissions are accepted unless the instructor turned `allow_late` off.
     */
    public function acceptsSubmissionNow(): bool
    {
        return ! $this->isOverdue() || (bool) ($this->allow_late ?? true);
    }

    public function submissionForTeam(int $projectId): ?ProjectDeliverableSubmission
    {
        return $this->submissions()
            ->where('project_id', $projectId)
            ->first();
    }
}
