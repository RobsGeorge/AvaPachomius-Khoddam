<?php

namespace App\Services\Visits;

use App\Models\HomeVisit;
use App\Models\Person;
use App\Models\Priest;
use App\Models\Relationship;
use App\Models\ResidenceMember;
use App\Models\User;
use App\Models\VisitNote;
use App\Services\AuditLogService;
use App\Services\CoursePermissionResolver;
use App\Services\Maturity\GuardianVisibilityGate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Custodial occurrence vs pastoral افتقاد notes visibility wall (ADR §22 / §26).
 *
 * Single sanctioned read path for visit_notes — never eager-load notes for family UI.
 */
final class VisitNoteVisibility
{
    public const SUBJECT_PERSON = 'person';

    public const SUBJECT_RESIDENCE = 'residence';

    public function __construct(
        private CoursePermissionResolver $resolver,
        private GuardianVisibilityGate $guardianGate,
    ) {}

    public function canViewOccurrence(User $viewer, HomeVisit $visit): bool
    {
        if ($this->isPriest($viewer, $visit) || $this->isChurchAdmin($viewer, $visit)) {
            return true;
        }

        if ((int) $visit->assigned_user_id === (int) $viewer->user_id) {
            return true;
        }

        return $this->isFamilyWithCustodialAccess($viewer, $visit);
    }

    public function canViewNote(User $viewer, VisitNote $note): bool
    {
        $visit = $note->relationLoaded('visit')
            ? $note->visit
            : HomeVisit::query()->find($note->home_visit_id);

        if (! $visit) {
            return false;
        }

        // Family NEVER reads pastoral notes — even when guardian_visibility=full.
        if ($this->isFamilyMemberOfSubject($viewer, $visit)) {
            return false;
        }

        // Safeguarding override: priest.manage only (assigned servant may be the risk).
        if ($this->subjectIsSafeguardingRestricted($visit)) {
            return $this->isPriest($viewer, $visit);
        }

        if ($this->isPriest($viewer, $visit)) {
            return true;
        }

        if ((int) $note->author_user_id === (int) $viewer->user_id) {
            return true;
        }

        return (int) $visit->assigned_user_id === (int) $viewer->user_id;
    }

    public function canCreateNote(User $actor, HomeVisit $visit): bool
    {
        if ($this->isFamilyMemberOfSubject($actor, $visit)) {
            return false;
        }

        if ($this->subjectIsSafeguardingRestricted($visit)) {
            return $this->isPriest($actor, $visit);
        }

        if ($this->isPriest($actor, $visit)) {
            return true;
        }

        return (int) $visit->assigned_user_id === (int) $actor->user_id;
    }

    /**
     * Only notes the viewer may read — never return an unfiltered VisitNote collection.
     *
     * @return Collection<int, VisitNote>
     */
    public function visibleNotesFor(User $viewer, HomeVisit $visit): Collection
    {
        return VisitNote::query()
            ->where('home_visit_id', $visit->home_visit_id)
            ->orderBy('created_at')
            ->get()
            ->filter(fn (VisitNote $note) => $this->canViewNote($viewer, $note->setRelation('visit', $visit)))
            ->values();
    }

    public function appendNote(
        HomeVisit $visit,
        User $author,
        string $body,
        ?VisitNote $corrects = null,
    ): VisitNote {
        if (! $this->canCreateNote($author, $visit)) {
            throw ValidationException::withMessages([
                'body' => [__('church_mgmt.visit_note_forbidden')],
            ]);
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'body' => [__('church_mgmt.visit_note_required')],
            ]);
        }

        if ($corrects !== null && (int) $corrects->home_visit_id !== (int) $visit->home_visit_id) {
            throw ValidationException::withMessages([
                'corrects_visit_note_id' => [__('church_mgmt.visit_note_correction_mismatch')],
            ]);
        }

        $note = new VisitNote([
            'home_visit_id' => $visit->home_visit_id,
            'author_user_id' => $author->user_id,
            'body' => $trimmed,
            'corrects_visit_note_id' => $corrects?->visit_note_id,
            'created_at' => now(),
        ]);
        $note->church_id = $visit->church_id;
        $note->save();

        AuditLogService::recordEvent('home_visit.note_appended', [
            'home_visit_id' => $visit->home_visit_id,
            'visit_note_id' => $note->visit_note_id,
            'corrects_visit_note_id' => $corrects?->visit_note_id,
        ]);

        return $note;
    }

    public function subjectIsSafeguardingRestricted(HomeVisit $visit): bool
    {
        foreach ($this->subjectPersonIds($visit) as $personId) {
            $restricted = Relationship::withoutTenancy()
                ->guardianOf()
                ->active()
                ->where('related_person_id', $personId)
                ->where('guardian_visibility', Relationship::VISIBILITY_RESTRICTED)
                ->exists();

            if ($restricted) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function subjectPersonIds(HomeVisit $visit): array
    {
        if ($visit->subject_type === self::SUBJECT_PERSON && $visit->subject_id) {
            return [(int) $visit->subject_id];
        }

        if ($visit->subject_type === self::SUBJECT_RESIDENCE && $visit->subject_id) {
            return ResidenceMember::query()
                ->where('residence_id', $visit->subject_id)
                ->active()
                ->pluck('person_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    private function isFamilyWithCustodialAccess(User $viewer, HomeVisit $visit): bool
    {
        if (! $viewer->person_id) {
            return false;
        }

        foreach ($this->subjectPersonIds($visit) as $wardId) {
            $ward = Person::withoutTenancy()->find($wardId);
            if (! $ward) {
                continue;
            }

            if ($this->guardianGate->guardianMaySee(
                $viewer,
                $ward,
                GuardianVisibilityGate::CATEGORY_CUSTODIAL
            )) {
                return true;
            }

            // Non-guardian family edges — occurrence only, never notes.
            if ($this->hasActiveFamilyEdge((int) $viewer->person_id, $wardId)) {
                return true;
            }
        }

        return false;
    }

    private function isFamilyMemberOfSubject(User $viewer, HomeVisit $visit): bool
    {
        if (! $viewer->person_id) {
            return false;
        }

        $viewerPersonId = (int) $viewer->person_id;

        foreach ($this->subjectPersonIds($visit) as $subjectPersonId) {
            if ($viewerPersonId === $subjectPersonId) {
                return true;
            }

            if ($this->hasActiveFamilyEdge($viewerPersonId, $subjectPersonId)) {
                return true;
            }

            // Guardian of subject (any visibility) counts as family for the notes wall.
            if (Relationship::withoutTenancy()
                ->guardianOf()
                ->active()
                ->where('person_id', $viewerPersonId)
                ->where('related_person_id', $subjectPersonId)
                ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasActiveFamilyEdge(int $a, int $b): bool
    {
        return Relationship::withoutTenancy()
            ->active()
            ->where(function ($q) use ($a, $b) {
                $q->where(function ($inner) use ($a, $b) {
                    $inner->where('person_id', $a)->where('related_person_id', $b);
                })->orWhere(function ($inner) use ($a, $b) {
                    $inner->where('person_id', $b)->where('related_person_id', $a);
                });
            })
            ->exists();
    }

    private function isPriest(User $user, HomeVisit $visit): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $church = $visit->church ?? TenantContext::current();
        if (! $church) {
            return false;
        }

        // Church admins who hold priest.manage, or an active priest row for this church.
        // (The "priest" role template does not include priest.manage — the Priest link does.)
        if ($this->resolver->canInChurch($user, 'priest.manage', $church)) {
            return true;
        }

        return Priest::query()
            ->where('church_id', $church->church_id)
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->exists();
    }

    private function isChurchAdmin(User $user, HomeVisit $visit): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $church = $visit->church ?? TenantContext::current();

        return $church && $this->resolver->canInChurch($user, 'church.configure', $church);
    }
}
