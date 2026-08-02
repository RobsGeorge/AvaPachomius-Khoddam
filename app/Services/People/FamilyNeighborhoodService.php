<?php

namespace App\Services\People;

use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Support\Collection;

/**
 * Family as a derived connected neighborhood over dated relationship edges.
 * No household container — traverse edges, respecting end_date by default.
 */
final class FamilyNeighborhoodService
{
    /**
     * Undirected BFS over active (or all) relationship edges.
     * Batches edge loads by frontier person IDs — no per-edge Person::find.
     *
     * @return Collection<int, Person> keyed by person_id (includes seed)
     */
    public function forPerson(Person $person, bool $includeEnded = false): Collection
    {
        $seedId = (int) $person->person_id;
        $visited = [$seedId => true];
        $frontier = [$seedId];

        while ($frontier !== []) {
            $query = Relationship::withoutTenancy()
                ->where(function ($q) use ($frontier) {
                    $q->whereIn('person_id', $frontier)
                        ->orWhereIn('related_person_id', $frontier);
                });

            if (! $includeEnded) {
                $query->active();
            }

            $edges = $query->get(['person_id', 'related_person_id']);
            $next = [];

            foreach ($edges as $edge) {
                foreach ([(int) $edge->person_id, (int) $edge->related_person_id] as $id) {
                    if (! isset($visited[$id])) {
                        $visited[$id] = true;
                        $next[] = $id;
                    }
                }
            }

            $frontier = $next;
        }

        $ids = array_keys($visited);

        return Person::withoutTenancy()
            ->whereIn('person_id', $ids)
            ->get()
            ->keyBy('person_id');
    }
}
