<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChurchService;
use App\Models\Course;
use App\Models\StructureTemplate;
use App\Services\RoleTemplateService;
use App\Services\Structure\StructureAnchorResolver;
use App\Support\Structure\ProgressionPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ServiceManagementController extends Controller
{
    public function index()
    {
        abort_unless(ChurchService::tableReady(), 404);

        $services = ChurchService::query()
            ->with(['structureTemplate'])
            ->withCount(['courses', 'userServiceRoles'])
            ->orderBy('title')
            ->get();

        $structureTemplates = StructureTemplate::query()
            ->orderBy('name_en')
            ->get();

        $progressionPolicies = ProgressionPolicy::all();
        $resolver = app(StructureAnchorResolver::class);

        return view('admin.services.index', compact(
            'services',
            'structureTemplates',
            'progressionPolicies',
            'resolver',
        ));
    }

    public function store(Request $request)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $rules = [
            'title' => 'required|string|max:120',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'clone_templates' => 'boolean',
        ];

        if (Schema::hasColumn('service', 'structure_template_id')) {
            $rules['structure_template_id'] = [
                'required',
                'integer',
                Rule::exists('structure_templates', 'structure_template_id'),
            ];
        }

        if (Schema::hasColumn('service', 'progression_policy')) {
            $rules['progression_policy'] = ['nullable', 'string', Rule::in(ProgressionPolicy::all())];
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

        if (Schema::hasColumn('service', 'structure_template_id')) {
            $payload['structure_template_id'] = $validated['structure_template_id'];
        }

        if (Schema::hasColumn('service', 'progression_policy')) {
            $policy = $validated['progression_policy'] ?? null;
            $payload['progression_policy'] = ProgressionPolicy::isValid($policy) ? $policy : null;
        }

        $service = ChurchService::create($payload);

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
        $structureTemplates = StructureTemplate::query()->orderBy('name_en')->get();
        $progressionPolicies = ProgressionPolicy::all();
        $resolver = app(StructureAnchorResolver::class);

        return view('admin.services.edit', compact(
            'service',
            'courses',
            'structureTemplates',
            'progressionPolicies',
            'resolver',
        ));
    }

    public function update(Request $request, ChurchService $service)
    {
        abort_unless(ChurchService::tableReady(), 404);

        $rules = [
            'title' => 'required|string|max:120',
            'title_ar' => 'nullable|string|max:120',
            'title_en' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'status' => ['required', Rule::in([ChurchService::STATUS_ACTIVE, ChurchService::STATUS_ARCHIVED])],
        ];

        if (Schema::hasColumn('service', 'structure_template_id')) {
            $rules['structure_template_id'] = [
                'required',
                'integer',
                Rule::exists('structure_templates', 'structure_template_id'),
            ];
        }

        if (Schema::hasColumn('service', 'progression_policy')) {
            $request->merge([
                'progression_policy' => $request->filled('progression_policy')
                    ? $request->input('progression_policy')
                    : null,
            ]);
            $rules['progression_policy'] = ['nullable', 'string', Rule::in(ProgressionPolicy::all())];
        }

        $validated = $request->validate($rules);

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
