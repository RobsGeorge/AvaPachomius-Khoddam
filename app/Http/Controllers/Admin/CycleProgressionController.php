<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\Course;
use App\Services\Structure\CycleProgressionWizardService;
use App\Services\Structure\StructureAnchorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CycleProgressionController extends Controller
{
    public function __construct(
        private CycleProgressionWizardService $wizard,
        private StructureAnchorResolver $resolver,
    ) {}

    public function show(ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);
        $this->authorizeWizard($service);

        try {
            $proposal = $this->wizard->propose($service);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.services.edit', $service)
                ->withErrors($e->errors());
        }

        $courses = Course::query()
            ->where('service_id', $service->service_id)
            ->orderByDesc('year')
            ->orderBy('title')
            ->get();

        return view('admin.services.cycle', [
            'service' => $service,
            'proposal' => $proposal,
            'courses' => $courses,
            'resolver' => $this->resolver,
        ]);
    }

    public function saveEdges(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);
        $this->authorizeWizard($service);

        $validated = $request->validate([
            'edges' => ['nullable', 'array'],
            'edges.*.from_course_id' => ['nullable', 'integer'],
            'edges.*.to_course_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->wizard->saveLadderEdges($service, $validated['edges'] ?? []);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.services.cycle.show', $service)
            ->with('success', __('service.cycle_edges_saved'));
    }

    public function confirm(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);
        $this->authorizeWizard($service);

        $validated = $request->validate([
            'decisions' => ['required', 'array'],
            'decisions.*.enrollment_id' => ['nullable', 'required_without:decisions.*.placement_id', 'integer'],
            'decisions.*.placement_id' => ['nullable', 'required_without:decisions.*.enrollment_id', 'integer'],
            'decisions.*.action' => [
                'required',
                Rule::in([
                    CycleProgressionWizardService::ACTION_PROMOTE,
                    CycleProgressionWizardService::ACTION_SKIP,
                    CycleProgressionWizardService::ACTION_INACTIVE,
                ]),
            ],
            'decisions.*.to_course_id' => ['nullable', 'integer'],
            'decisions.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = Auth::user();
        abort_unless($actor, 403);

        try {
            $result = $this->wizard->apply($service, $actor, array_values($validated['decisions']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.services.cycle.show', $service)
            ->with('success', __('service.cycle_applied_success', [
                'moved' => $result['moved'],
                'skipped' => $result['skipped'],
                'inactivated' => $result['inactivated'],
            ]));
    }

    private function authorizeWizard(ChurchService $service): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (\App\Services\RolePreviewService::superadminBypassesPermissions($user)) {
            return;
        }

        $ok = app(\App\Services\CoursePermissionResolver::class)
            ->canInService($user, 'service.progression.run', $service)
            || $user->canInSystem('platform.service_crud')
            || $user->canInSystem('service.progression.run');

        abort_unless($ok, 403);
    }
}
