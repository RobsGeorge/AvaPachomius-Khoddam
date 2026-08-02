<?php

namespace App\Policies;

use App\Models\HomeVisit;
use App\Models\User;
use App\Models\VisitNote;
use App\Services\Visits\VisitNoteVisibility;

class VisitNotePolicy
{
    public function __construct(
        private VisitNoteVisibility $visibility,
    ) {}

    public function view(User $user, VisitNote $note): bool
    {
        return $this->visibility->canViewNote($user, $note);
    }

    public function create(User $user, HomeVisit $visit): bool
    {
        return $this->visibility->canCreateNote($user, $visit);
    }

    public function viewOccurrence(User $user, HomeVisit $visit): bool
    {
        return $this->visibility->canViewOccurrence($user, $visit);
    }
}
