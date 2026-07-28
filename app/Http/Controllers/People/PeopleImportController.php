<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\PeopleImportBatch;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Services\People\PeopleImportService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeopleImportController extends Controller
{
    public function template(PeopleImportService $imports)
    {
        $this->authorizePeople('people.import');

        $csv = $imports->downloadTemplateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="people-import-template-v1.csv"',
        ]);
    }

    public function create()
    {
        $this->authorizePeople('people.import');

        return view('people_onboarding.import');
    }

    public function store(Request $request, PeopleImportService $imports)
    {
        $this->authorizePeople('people.import');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'service_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
        ]);

        $churchId = TenantContext::id() ?? Church::main()?->church_id;
        $batch = $imports->preview(
            $request->file('file'),
            (int) $churchId,
            $request->user(),
            $validated['service_id'] ?? null,
            $validated['course_id'] ?? null,
        );

        return redirect()->route('people.import.show', $batch);
    }

    public function show(PeopleImportBatch $batch)
    {
        $this->authorizePeople('people.import');
        $batch->load('rows');

        return view('people_onboarding.import-batch', compact('batch'));
    }

    public function commit(PeopleImportBatch $batch, PeopleImportService $imports)
    {
        $this->authorizePeople('people.import');
        $imports->commit($batch, true);

        return redirect()
            ->route('people.import.show', $batch)
            ->with('success', __('people_onboarding.import_committed'));
    }

    public function bulkInvite(Request $request, PeopleImportBatch $batch, PeopleImportService $imports)
    {
        $this->authorizePeople('people.invite_bulk');

        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['integer'],
            'send_email' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
        ]);

        $result = $imports->bulkInvite(
            $batch,
            $validated['row_ids'],
            (bool) ($validated['send_email'] ?? true),
            (bool) ($validated['send_whatsapp'] ?? false),
            $request->user(),
        );

        return back()->with('success', __('people_onboarding.bulk_invite_result', $result));
    }

    public function exportRoster(Request $request): StreamedResponse
    {
        $this->authorizePeople('people.view');

        $serviceId = $request->integer('service_id') ?: null;

        return response()->streamDownload(function () use ($serviceId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, PeopleImportService::templateHeaders());

            $query = PersonPlacement::query()
                ->with(['person', 'service', 'course', 'intendedRole'])
                ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
                ->orderBy('person_placement_id');

            foreach ($query->cursor() as $placement) {
                /** @var PersonPlacement $placement */
                $person = $placement->person;
                if (! $person) {
                    continue;
                }
                fputcsv($out, [
                    $person->first_name,
                    $person->second_name,
                    $person->third_name,
                    optional($person->date_of_birth)?->format('Y-m-d'),
                    $person->gender,
                    $person->email,
                    $person->mobile_number,
                    $person->national_id,
                    $placement->service?->slug,
                    $placement->course_id,
                    '',
                    $placement->intendedRole?->slug,
                    $placement->placement_mode === 'info_only' ? 'info_only' : 'invite_later',
                ]);
            }
            fclose($out);
        }, 'people-roster.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function authorizePeople(string $permission): void
    {
        $user = request()->user();
        abort_unless($user && (
            $user->is_superadmin
            || $user->canInSystem($permission)
            || $user->canInSystem('church.members.manage')
            || ($permission === 'people.invite_bulk' && $user->canInSystem('people.invite'))
        ), 403);
    }
}
