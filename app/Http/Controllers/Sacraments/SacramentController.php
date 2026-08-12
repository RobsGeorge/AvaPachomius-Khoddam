<?php

namespace App\Http\Controllers\Sacraments;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Person;
use App\Models\Sacrament;
use App\Services\Sacraments\SacramentRepository;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SacramentController extends Controller
{
    public function index(Person $person)
    {
        $this->authorize('viewAny', Sacrament::class);

        $sacraments = Sacrament::query()
            ->where('person_id', $person->person_id)
            ->orderByDesc('date')
            ->orderByDesc('sacrament_id')
            ->get();

        return view('sacraments.index', compact('person', 'sacraments'));
    }

    public function create(Person $person)
    {
        $this->authorize('create', Sacrament::class);

        $types = Sacrament::UI_TYPES;

        return view('sacraments.create', compact('person', 'types'));
    }

    public function store(Request $request, Person $person, SacramentRepository $repository)
    {
        $this->authorize('create', Sacrament::class);

        $validated = $this->validatePayload($request, uiOnly: true);
        $churchId = TenantContext::id() ?? $person->church_id ?? Church::main()?->church_id;

        $sacrament = $repository->record([
            'church_id' => (int) $churchId,
            'person_id' => (int) $person->person_id,
            'type' => $validated['type'],
            'date' => $validated['date'],
            'date_precision' => $validated['date_precision'],
            'location_church_id' => $validated['location_church_id'] ?? null,
            'location_text' => $validated['location_text'] ?? null,
            'officiant_person_id' => $validated['officiant_person_id'] ?? null,
            'second_person_id' => $validated['second_person_id'] ?? null,
            'recorded_by' => (int) $request->user()->user_id,
        ]);

        return redirect()
            ->route('sacraments.show', $sacrament)
            ->with('success', __('sacraments.recorded'));
    }

    public function show(Sacrament $sacrament)
    {
        $this->authorize('view', $sacrament);

        $sacrament->load(['person', 'officiant', 'secondPerson', 'locationChurch', 'corrects']);

        return view('sacraments.show', compact('sacrament'));
    }

    public function correctForm(Sacrament $sacrament)
    {
        $this->authorize('correct', $sacrament);

        $types = Sacrament::UI_TYPES;

        return view('sacraments.correct', compact('sacrament', 'types'));
    }

    public function correct(Request $request, Sacrament $sacrament, SacramentRepository $repository)
    {
        $this->authorize('correct', $sacrament);

        $validated = $this->validatePayload($request, uiOnly: true);

        $correction = $repository->correct((int) $sacrament->sacrament_id, [
            'type' => $validated['type'],
            'date' => $validated['date'],
            'date_precision' => $validated['date_precision'],
            'location_church_id' => $validated['location_church_id'] ?? null,
            'location_text' => $validated['location_text'] ?? null,
            'officiant_person_id' => $validated['officiant_person_id'] ?? null,
            'second_person_id' => $validated['second_person_id'] ?? null,
            'recorded_by' => (int) $request->user()->user_id,
        ]);

        return redirect()
            ->route('sacraments.show', $correction)
            ->with('success', __('sacraments.corrected'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $uiOnly): array
    {
        $allowedTypes = $uiOnly ? Sacrament::UI_TYPES : Sacrament::TYPES;

        return $request->validate([
            'type' => ['required', 'string', Rule::in($allowedTypes)],
            'date' => ['required', 'date'],
            'date_precision' => ['required', 'string', Rule::in(Sacrament::PRECISIONS)],
            'location_church_id' => ['nullable', 'integer', 'exists:church,church_id'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'officiant_person_id' => ['nullable', 'integer', 'exists:people,person_id'],
            'second_person_id' => ['nullable', 'integer', 'exists:people,person_id'],
        ]);
    }
}
