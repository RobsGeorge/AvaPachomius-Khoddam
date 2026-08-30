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

    protected $table = 'projects';

    protected $primaryKey = 'project_id';

    protected $fillable = [
        'project_assessment_id',
        'title',
        'requirements',
        'status',
        'sort_order',
        'is_locked',
        'below_minimum',
        'cancelled_at',
        'workspace_provider',
        'team_workspace_url',
        'team_announcement',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_locked' => 'boolean',
        'below_minimum' => 'boolean',
        'cancelled_at' => 'datetime',
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
