<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Project extends Model
{
    use BelongsToChurch;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const WORKSPACE_CUSTOM = 'custom';

    public const WORKSPACE_DRIVE = 'drive';

    public const WORKSPACE_WHATSAPP = 'whatsapp';

    public const WORKSPACE_TELEGRAM = 'telegram';

    public const BRIEF_MAIN_TITLE = 'brief_main_title';

    public const BRIEF_AUDIENCE = 'brief_audience';

    public const BRIEF_ENVIRONMENT = 'brief_environment';

    public const BRIEF_PURPOSE = 'brief_purpose';

    public const BRIEF_MAX_LENGTH = 255;

    /**
     * @var list<string>
     */
    public const BRIEF_KEYS = [
        self::BRIEF_MAIN_TITLE,
        self::BRIEF_AUDIENCE,
        self::BRIEF_ENVIRONMENT,
        self::BRIEF_PURPOSE,
    ];

    protected $table = 'projects';

    protected $primaryKey = 'project_id';

    protected $fillable = [
        'project_assessment_id',
        'title',
        'brief_main_title',
        'brief_audience',
        'brief_environment',
        'brief_purpose',
        'requirements',
        'status',
        'sort_order',
        'is_locked',
        'below_minimum',
        'cancelled_at',
        'workspace_provider',
        'team_workspace_url',
        'team_announcement',
        'final_submitted_at',
        'final_submitted_by_user_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_locked' => 'boolean',
        'below_minimum' => 'boolean',
        'cancelled_at' => 'datetime',
        'final_submitted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'project_id';
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ProjectAssessment::class, 'project_assessment_id', 'project_assessment_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class, 'project_id', 'project_id')
            ->orderBy('sort_order')
            ->orderBy('project_phase_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectDeliverable::class, 'project_id', 'project_id')
            ->orderBy('sort_order')
            ->orderBy('project_deliverable_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class, 'project_id', 'project_id');
    }

    public function deliverableSubmissions(): HasMany
    {
        return $this->hasMany(ProjectDeliverableSubmission::class, 'project_id', 'project_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', ProjectMembership::STATUS_ACTIVE);
    }

    public function activeMembers(): Collection
    {
        return $this->activeMemberships()
            ->with('user')
            ->get()
            ->map(fn (ProjectMembership $membership) => $membership->user)
            ->filter()
            ->values();
    }

    public function activeMemberCount(): int
    {
        return $this->activeMemberships()->count();
    }

    public function remainingSeats(?ProjectAssessment $assessment = null): int
    {
        $assessment ??= $this->assessment;
        $max = (int) ($assessment?->max_team_size ?? 0);

        return max($max - $this->activeMemberCount(), 0);
    }

    public function isFull(?ProjectAssessment $assessment = null): bool
    {
        return $this->remainingSeats($assessment) === 0;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    /**
     * Seatable = pack-fill may still place a student here.
     */
    public function acceptsNewMembers(?ProjectAssessment $assessment = null): bool
    {
        return ! $this->isCancelled()
            && ! $this->isLocked()
            && $this->remainingSeats($assessment) > 0;
    }

    public function isBelowMinimum(?ProjectAssessment $assessment = null): bool
    {
        $assessment ??= $this->assessment;
        $min = (int) ($assessment?->min_team_size ?? 0);
        $count = $this->activeMemberCount();

        return $min > 0 && $count > 0 && $count < $min;
    }

    public function teamGrade(): HasOne
    {
        return $this->hasOne(ProjectTeamGrade::class, 'project_id', 'project_id');
    }

    public function memberGrades(): HasMany
    {
        return $this->hasMany(ProjectMemberGrade::class, 'project_id', 'project_id');
    }

    public function membershipEvents(): HasMany
    {
        return $this->hasMany(ProjectMembershipEvent::class, 'project_id', 'project_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('project_membership_event_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ProjectMemberVerification::class, 'project_id', 'project_id');
    }

    public function finalSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_submitted_by_user_id', 'user_id');
    }

    public function isFinalSubmitted(): bool
    {
        return $this->final_submitted_at !== null;
    }

    public function isLateFinal(): bool
    {
        $due = $this->assessment?->submission_due_at;
        if ($due === null || $this->final_submitted_at === null) {
            return false;
        }

        return $this->final_submitted_at->gt($due);
    }

    /**
     * @return array<string, ?string>
     */
    public function briefAttributes(): array
    {
        $out = [];
        foreach (self::BRIEF_KEYS as $key) {
            $value = trim((string) $this->{$key});
            $out[$key] = $value === '' ? null : $value;
        }

        return $out;
    }

    public function hasStructuredBrief(): bool
    {
        foreach ($this->briefAttributes() as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plain-text blob of the four brief fields (locale-independent values only).
     */
    public function composedRequirements(): ?string
    {
        return self::composeRequirements($this->briefAttributes());
    }

    /**
     * @param  array<string, ?string>  $brief
     */
    public static function composeRequirements(array $brief): ?string
    {
        $lines = [];
        foreach (self::BRIEF_KEYS as $key) {
            $value = trim((string) ($brief[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, ?string>
     */
    public static function briefFromRow(array $row): array
    {
        $out = [];
        foreach (self::BRIEF_KEYS as $key) {
            if (! array_key_exists($key, $row)) {
                $out[$key] = null;

                continue;
            }
            $value = trim((string) $row[$key]);
            $out[$key] = $value === '' ? null : mb_substr($value, 0, self::BRIEF_MAX_LENGTH);
        }

        return $out;
    }

    /**
     * Map a legacy free-text requirements blob onto the four brief columns.
     *
     * Two to four non-empty newline-separated lines are mapped in order onto
     * the four keys. Anything else (a single paragraph, one line, or more
     * than four lines) is placed in brief_purpose only.
     *
     * @return array<string, string>
     */
    public static function briefFromLegacyRequirements(?string $requirements): array
    {
        $text = trim((string) $requirements);
        if ($text === '') {
            return [];
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text) ?: []),
            fn (string $line): bool => $line !== ''
        ));

        $out = [];
        $lineCount = count($lines);
        if ($lineCount >= 2 && $lineCount <= count(self::BRIEF_KEYS)) {
            foreach (self::BRIEF_KEYS as $i => $key) {
                if (! isset($lines[$i])) {
                    break;
                }
                $out[$key] = mb_substr($lines[$i], 0, self::BRIEF_MAX_LENGTH);
            }

            return $out;
        }

        $out[self::BRIEF_PURPOSE] = mb_substr($text, 0, self::BRIEF_MAX_LENGTH);

        return $out;
    }

    /**
     * Fill empty brief columns from requirements. Does not change requirements.
     */
    public function applyLegacyBriefPrefill(): self
    {
        if ($this->hasStructuredBrief()) {
            return $this;
        }

        foreach (self::briefFromLegacyRequirements($this->requirements) as $key => $value) {
            if (! filled($this->{$key})) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * Brief values for the admin edit form: existing columns, or a legacy prefill
     * so the four boxes are not blank while requirements still holds the old text.
     *
     * @return array<string, ?string>
     */
    public function briefAttributesForEdit(): array
    {
        $attrs = $this->briefAttributes();
        if ($this->hasStructuredBrief()) {
            return $attrs;
        }

        foreach (self::briefFromLegacyRequirements($this->requirements) as $key => $value) {
            if (($attrs[$key] ?? null) === null) {
                $attrs[$key] = $value;
            }
        }

        return $attrs;
    }

    /**
     * Original requirements text that is not fully represented by the four fields
     * (still unmapped, or longer than 255 characters after truncation).
     */
    public function leftoverLegacyRequirements(): ?string
    {
        $original = trim((string) $this->requirements);
        if ($original === '') {
            return null;
        }

        if (! $this->hasStructuredBrief()) {
            return $original;
        }

        $composed = $this->composedRequirements();
        if ($composed !== null && $original === $composed) {
            return null;
        }

        return $original;
    }

    /**
     * Copy requirements into empty brief columns for every church.
     * withoutTenancy: deploy-time backfill must reach every tenant.
     */
    public static function backfillLegacyBriefFields(): int
    {
        $updated = 0;

        static::withoutTenancy()
            ->orderBy('project_id')
            ->each(function (Project $project) use (&$updated) {
                $project->applyLegacyBriefPrefill();
                if ($project->isDirty(self::BRIEF_KEYS)) {
                    $project->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @return list<string>
     */
    public static function workspaceProviders(): array
    {
        return [
            self::WORKSPACE_CUSTOM,
            self::WORKSPACE_DRIVE,
            self::WORKSPACE_WHATSAPP,
            self::WORKSPACE_TELEGRAM,
        ];
    }

    public function workspaceProvider(): string
    {
        $provider = $this->workspace_provider ?: self::WORKSPACE_CUSTOM;

        return in_array($provider, self::workspaceProviders(), true)
            ? $provider
            : self::WORKSPACE_CUSTOM;
    }

    /**
     * Light host allow-lists for known providers. Custom accepts any valid URL.
     *
     * @return list<string>
     */
    public static function workspaceHostsFor(string $provider): array
    {
        return match ($provider) {
            self::WORKSPACE_DRIVE => ['drive.google.com', 'docs.google.com'],
            self::WORKSPACE_WHATSAPP => ['chat.whatsapp.com', 'wa.me', 'api.whatsapp.com', 'web.whatsapp.com'],
            self::WORKSPACE_TELEGRAM => ['t.me', 'telegram.me', 'telegram.org'],
            default => [],
        };
    }

    public static function workspaceUrlMatchesProvider(?string $url, string $provider): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        if ($provider === self::WORKSPACE_CUSTOM || $provider === '') {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach (self::workspaceHostsFor($provider) as $allowed) {
            $allowed = strtolower($allowed);
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
