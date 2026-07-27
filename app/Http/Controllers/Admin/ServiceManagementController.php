<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\Course;
use App\Services\RoleTemplateService;
use App\Support\SuperadminWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceManagementController extends Controller
{
    public function index()
    {
        abort_unless(ChurchService::tableReady(), 404);

        $requiresChurch = SuperadminWorkspace::requiresExplicitChurchScope();

        $services = ChurchService::query()
            ->with('church')
            ->withCount(['courses', 'userServiceRoles'])
            ->when($requiresChurch, fn ($q) => $q->withoutTenancy())
            ->orderBy('church_id')
            ->orderBy('title')
            ->get();

        $groupedServices = $services->groupBy(fn (ChurchService $service) => (int) ($service->church_id ?? 0));

        $churches = $requiresChurch
            ? Church::query()->orderBy('name')->get(['church_id', 'name', 'slug'])
            : collect();

        return view('admin.services.index', compact('services', 'groupedServices', 'churches', 'requiresChurch'));
    }

    public function store(Request $request)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $requiresChurch = SuperadminWorkspace::requiresExplicitChurchScope();

        $rules = [
            'title' => 'required|string|max:120',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'clone_templates' => 'boolean',
        ];

        if ($requiresChurch) {
            $rules['church_id'] = 'required|integer|exists:church,church_id';
        }

        $validated = $request->validate($rules);

        $payload = [
            'title' => $validated['title'],
            'title_ar' => $validated['title_ar'] ?? null,
            'title_en' => $validated['title_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => ChurchService::STATUS_ACTIVE,
            'permissions_version' => 0,
        ];

        $service = new ChurchService([
            'title' => $payload['title'],
            'title_ar' => $payload['title_ar'],
            'title_en' => $payload['title_en'],
            'description' => $payload['description'],
            'status' => $payload['status'],
            'permissions_version' => $payload['permissions_version'],
        ]);

        if ($requiresChurch) {
            $service->church_id = (int) $validated['church_id'];
        }

        $service->save();

        if ($request->boolean('clone_templates', true)) {
            app(RoleTemplateService::class)->cloneTemplatesIntoService($service);
        }

        return $this->redirectAfterMutation($request)
            ->with('success', __('service.created'));
    }

    /**
     * Superadmins reach service management embedded in the combined
     * "Manage services & courses" page; keep them there after a mutation.
     * The target is safelisted to avoid open redirects.
     */
    protected function redirectAfterMutation(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->input('from') === 'courses') {
            return redirect()->route('superadmin.courses');
        }

        return redirect()->route('admin.services.index');
    }

    public function edit(ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $courses = Course::query()
            ->when($service->church_id, fn ($q) => $q->where('church_id', $service->church_id))
            ->orderByDesc('year')
            ->orderBy('title')
            ->get();

        return view('admin.services.edit', compact('service', 'courses'));
    }

    public function update(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'status' => ['required', Rule::in([ChurchService::STATUS_ACTIVE, ChurchService::STATUS_ARCHIVED])],
        ]);

        $service->update($validated);

        return redirect()
            ->route('admin.services.edit', $service)
            ->with('success', __('service.updated'));
    }

    public function linkCourse(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $validated = $request->validate([
            'course_id' => 'required|exists:course,course_id',
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if ($service->church_id && $course->church_id
            && (int) $service->church_id !== (int) $course->church_id) {
            throw ValidationException::withMessages([
                'course_id' => [__('service.course_church_mismatch')],
            ]);
        }

        $course->service_id = $service->service_id;
        $course->save();

        return redirect()
            ->route('admin.services.edit', $service)
            ->with('success', __('service.course_linked'));
    }

    public function archive(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        if ($service->courses()->where('status', Course::STATUS_ACTIVE)->exists()) {
            return back()->withErrors([
                'service' => __('service.archive_has_active_courses'),
            ]);
        }

        $service->status = ChurchService::STATUS_ARCHIVED;
        $service->save();

        return $this->redirectAfterMutation($request)
            ->with('success', __('service.archived'));
    }
}
