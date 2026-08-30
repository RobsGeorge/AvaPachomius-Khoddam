<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\HomeVisit;
use App\Models\Person;
use App\Models\Priest;
use App\Models\Relationship;
use App\Models\Residence;
use App\Models\ResidenceMember;
use App\Models\Sacrament;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Services\Maturity\GuardianVisibilityGate;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Custodial / pastoral / sacramental document wall (ADR §27 / §30).
 * Mirrors Slice 9 visit_notes: family never reads pastoral or sensitive docs.
 */
final class DocumentVisibility
{
    public function __construct(
        private CoursePermissionResolver $resolver,
        private GuardianVisibilityGate $guardianGate,
    ) {}

    public function canView(User $viewer, Document $document): bool
    {
        if ($this->isPriest($viewer, $document) || $this->isChurchAdmin($viewer, $document)) {
            return true;
        }

        // Family never sees pastoral layer or sensitive/safeguarding docs.
        if ($this->isFamilyOfDocumentSubject($viewer, $document)) {
            if ($document->is_sensitive || $document->visibility_layer === Document::LAYER_PASTORAL) {
                return false;
            }

            if ($this->subjectIsSafeguardingRestricted($document)) {
                return false;
            }

            return in_array($document->visibility_layer, [
                Document::LAYER_CUSTODIAL,
                Document::LAYER_SACRAMENTAL,
            ], true);
        }

        if ((int) $document->uploaded_by === (int) $viewer->user_id) {
            return true;
        }

        // Safeguarding override: only priest/church-admin (already returned above) may see
        // a restricted subject's documents — no generic-permission fallback past this point.
        if ($this->subjectIsSafeguardingRestricted($document)) {
            return false;
        }

        // Sensitive or pastoral-layer documents are never granted by the generic
        // documents.view permission alone — only priest/church-admin/uploader (checked
        // above) may see them. Mirrors VisitNoteVisibility::canViewNote's "no permission
        // fallback for pastoral content" rule.
        if ($document->is_sensitive || $document->visibility_layer === Document::LAYER_PASTORAL) {
            return false;
        }

        return $this->resolver->canInChurch(
            $viewer,
            'documents.view',
            $document->church ?? TenantContext::current()
        );
    }

    /**
     * @return Collection<int, Document>
     */
    public function visibleFor(User $viewer, string $documentableType, int $documentableId): Collection
    {
        return Document::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->orderBy('uploaded_at')
            ->get()
            ->filter(fn (Document $doc) => $this->canView($viewer, $doc))
            ->values();
    }

    /**
     * Resolve and validate documentable exists in-app (no DB morph FK).
     *
     * @throws ValidationException
     */
    public function assertDocumentableExists(string $type, int $id): Model
    {
        if (! in_array($type, Document::DOCUMENTABLE_TYPES, true)) {
            throw ValidationException::withMessages([
                'documentable_type' => [__('documents.errors.invalid_type')],
            ]);
        }

        $model = match ($type) {
            Document::DOCUMENTABLE_PERSON => Person::withoutTenancy()->find($id),
            Document::DOCUMENTABLE_RESIDENCE => Residence::withoutTenancy()->find($id),
            Document::DOCUMENTABLE_SACRAMENT => Sacrament::withoutTenancy()->find($id),
            Document::DOCUMENTABLE_VISIT => HomeVisit::withoutTenancy()->find($id),
        };

        if (! $model) {
            throw ValidationException::withMessages([
                'documentable_id' => [__('documents.errors.subject_missing')],
            ]);
        }

        return $model;
    }

    /**
     * @return list<int>
     */
    public function subjectPersonIds(Document $document): array
    {
        return match ($document->documentable_type) {
            Document::DOCUMENTABLE_PERSON => [(int) $document->documentable_id],
            Document::DOCUMENTABLE_RESIDENCE => ResidenceMember::query()
                ->where('residence_id', $document->documentable_id)
                ->active()
                ->pluck('person_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            Document::DOCUMENTABLE_SACRAMENT => $this->sacramentPersonIds((int) $document->documentable_id),
            Document::DOCUMENTABLE_VISIT => $this->visitPersonIds((int) $document->documentable_id),
            default => [],
        };
    }

    public function subjectIsSafeguardingRestricted(Document $document): bool
    {
        foreach ($this->subjectPersonIds($document) as $personId) {
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

    private function isFamilyOfDocumentSubject(User $viewer, Document $document): bool
    {
        if (! $viewer->person_id) {
            return false;
        }

        $viewerPersonId = (int) $viewer->person_id;

        foreach ($this->subjectPersonIds($document) as $subjectPersonId) {
            if ($viewerPersonId === $subjectPersonId) {
                return true;
            }

            if ($this->hasActiveFamilyEdge($viewerPersonId, $subjectPersonId)) {
                return true;
            }

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

    /**
     * @return list<int>
     */
    private function sacramentPersonIds(int $sacramentId): array
    {
        $sacrament = Sacrament::withoutTenancy()->find($sacramentId);
        if (! $sacrament) {
            return [];
        }

        $ids = [(int) $sacrament->person_id];
        if ($sacrament->second_person_id) {
            $ids[] = (int) $sacrament->second_person_id;
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function visitPersonIds(int $homeVisitId): array
    {
        $visit = HomeVisit::withoutTenancy()->find($homeVisitId);
        if (! $visit) {
            return [];
        }

        if ($visit->subject_type === Document::DOCUMENTABLE_PERSON && $visit->subject_id) {
            return [(int) $visit->subject_id];
        }

        if ($visit->subject_type === Document::DOCUMENTABLE_RESIDENCE && $visit->subject_id) {
            return ResidenceMember::query()
                ->where('residence_id', $visit->subject_id)
                ->active()
                ->pluck('person_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    private function isPriest(User $user, Document $document): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $church = $document->church ?? TenantContext::current();
        if (! $church) {
            return false;
        }

        if ($this->resolver->canInChurch($user, 'priest.manage', $church)) {
            return true;
        }

        return Priest::query()
            ->where('church_id', $church->church_id)
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->exists();
    }

    private function isChurchAdmin(User $user, Document $document): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $church = $document->church ?? TenantContext::current();

        return $church && $this->resolver->canInChurch($user, 'church.configure', $church);
    }
}
