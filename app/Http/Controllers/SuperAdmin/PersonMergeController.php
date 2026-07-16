<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Services\People\PersonMergeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Minimal superadmin UI for person merge (P2.1). Not linked in student nav.
 */
class PersonMergeController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $people = collect();
        $duplicateGroups = [];

        if ($q !== '') {
            $normalized = \App\Support\ArabicNameNormalizer::normalize($q);
            $people = Person::withoutTenancy()
                ->active()
                ->where(function ($query) use ($q, $normalized) {
                    $query->where('normalized_name', 'like', '%'.$normalized.'%')
                        ->orWhere('display_name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                })
                ->orderBy('person_id')
                ->limit(40)
                ->get();
        } else {
            // Surface name collisions for quick merge review.
            $duplicateGroups = Person::withoutTenancy()
                ->active()
                ->selectRaw('normalized_name, COUNT(*) as c')
                ->groupBy('normalized_name')
                ->having('c', '>', 1)
                ->orderByDesc('c')
                ->limit(30)
                ->get()
                ->map(function ($row) {
                    return [
                        'normalized_name' => $row->normalized_name,
                        'count' => (int) $row->c,
                        'people' => Person::withoutTenancy()
                            ->active()
                            ->where('normalized_name', $row->normalized_name)
                            ->orderBy('person_id')
                            ->get(),
                    ];
                })
                ->all();
        }

        return view('superadmin.people.merge', [
            'q' => $q,
            'people' => $people,
            'duplicateGroups' => $duplicateGroups,
        ]);
    }

    public function merge(Request $request, PersonMergeService $merger)
    {
        $data = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:duplicate_id'],
            'duplicate_id' => ['required', 'integer'],
        ]);

        $survivor = Person::withoutTenancy()->findOrFail($data['survivor_id']);
        $duplicate = Person::withoutTenancy()->findOrFail($data['duplicate_id']);

        $summary = $merger->merge($survivor, $duplicate, $request->user());

        return redirect()
            ->route('superadmin.people.merge.index')
            ->with('success', __('people.merge_success', [
                'duplicate' => $summary['duplicate_id'],
                'survivor' => $summary['survivor_id'],
            ]));
    }
}
