<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\Course;
use App\Services\RoleTemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceManagementController extends Controller
{
    public function index()
    {
        abort_unless(ChurchService::tableReady(), 404);

        $services = ChurchService::query()
            ->withCount(['courses', 'userServiceRoles'])
            ->orderBy('title')
            ->get();

        return view('admin.services.index', compact('services'));
    }

    public function store(Request $request)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'clone_templates' => 'boolean',
        ]);

        $service = ChurchService::create([
            'title' => $validated['title'],
            'title_ar' => $validated['title_ar'] ?? null,
            'title_en' => $validated['title_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => ChurchService::STATUS_ACTIVE,
            'permissions_version' => 0,
        ]);

        if ($request->boolean('clone_templates', true)) {
            app(RoleTemplateService::class)->cloneTemplatesIntoService($service);
        }

        return redirect()
            ->route('admin.services.index')
            ->with('success', __('service.created'));
    }

    public function edit(ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $courses = Course::query()->orderByDesc('year')->orderBy('title')->get();

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
        $course->service_id = $service->service_id;
        $course->save();

        return redirect()
            ->route('admin.services.edit', $service)
            ->with('success', __('service.course_linked'));
    }

    public function archive(ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        if ($service->courses()->where('status', Course::STATUS_ACTIVE)->exists()) {
            return back()->withErrors([
                'service' => __('service.archive_has_active_courses'),
            ]);
        }

        $service->status = ChurchService::STATUS_ARCHIVED;
        $service->save();

        return redirect()
            ->route('admin.services.index')
            ->with('success', __('service.archived'));
    }
}
