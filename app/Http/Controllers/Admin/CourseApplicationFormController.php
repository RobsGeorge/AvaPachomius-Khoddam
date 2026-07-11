<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationFormStep;
use App\Models\Role;
use App\Models\User;
use App\Services\CourseApplicationFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CourseApplicationFormController extends Controller
{
    public function __construct(
        private CourseApplicationFormService $forms,
    ) {}

    public function index()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $courses = Course::orderBy('title')->get();
        $forms = CourseApplicationForm::query()
            ->get()
            ->keyBy('course_id');

        return view('admin.course-application-forms.index', compact('courses', 'forms'));
    }

    public function edit(string $course)
    {
        $courseModel = Course::findOrFail($course);
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $form = $this->forms->getOrCreateForCourse($courseModel, $user);
        $form->load(['steps.fields']);
        $courses = Course::orderBy('title')->get();
        $roles = Role::orderBy('role_name')->get();
        $fieldTypes = CourseApplicationFormField::allTypes();

        return view('admin.course-application-forms.edit', compact('courseModel', 'form', 'courses', 'roles', 'fieldTypes'));
    }

    public function preview(Request $request, string $course)
    {
        $courseModel = Course::findOrFail($course);
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $form = $this->forms->getOrCreateForCourse($courseModel, $user);
        $form->load(['steps.fields']);
        $steps = $form->steps;

        if ($steps->isEmpty()) {
            return redirect()
                ->route('admin.courses.application-form.edit', $courseModel->course_id)
                ->with('warning', __('course_applications.preview_no_steps'));
        }

        $stepIndex = max(0, min((int) $request->query('step', 0), $steps->count() - 1));
        $currentStep = $steps->get($stepIndex);

        return view('admin.course-application-forms.preview', compact(
            'courseModel',
            'form',
            'steps',
            'stepIndex',
            'currentStep',
        ));
    }

    public function update(Request $request, string $course)
    {
        $courseModel = Course::findOrFail($course);
        $form = $this->forms->getOrCreateForCourse($courseModel);

        $validated = $request->validate([
            'course_id' => ['required', 'exists:course,course_id'],
            'is_enabled' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_role_id' => ['nullable', 'exists:roles,role_id'],
        ]);

        if ((int) $validated['course_id'] !== (int) $courseModel->course_id) {
            $targetCourse = Course::findOrFail($validated['course_id']);
            $targetForm = $this->forms->getOrCreateForCourse($targetCourse);
            $validated['is_enabled'] = $request->boolean('is_enabled');
            $validated['title'] = $validated['title'] ?: $targetCourse->title;
            $this->forms->updateForm($targetForm, $validated);

            return redirect()
                ->route('admin.courses.application-form.edit', $targetCourse->course_id)
                ->with('success', __('course_applications.form_saved'));
        }

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['title'] = $validated['title'] ?: $courseModel->title;

        $this->forms->updateForm($form, $validated);

        return back()->with('success', __('course_applications.form_saved'));
    }

    public function storeStep(Request $request, string $course)
    {
        $courseModel = Course::findOrFail($course);
        $form = $this->forms->getOrCreateForCourse($courseModel);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->forms->createStep($form, $validated);

        return back()->with('success', __('course_applications.step_created'));
    }

    public function updateStep(Request $request, string $course, CourseApplicationFormStep $step)
    {
        Course::findOrFail($course);
        $this->assertStepBelongsToCourse($step, $course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->forms->updateStep($step, $validated);

        return back()->with('success', __('course_applications.step_updated'));
    }

    public function destroyStep(string $course, CourseApplicationFormStep $step)
    {
        Course::findOrFail($course);
        $this->assertStepBelongsToCourse($step, $course);
        $this->forms->deleteStep($step);

        return back()->with('success', __('course_applications.step_deleted'));
    }

    public function reorderSteps(Request $request, string $course)
    {
        $courseModel = Course::findOrFail($course);
        $form = $this->forms->getOrCreateForCourse($courseModel);

        $validated = $request->validate([
            'step_ids' => ['required', 'array'],
            'step_ids.*' => ['integer'],
        ]);

        $this->forms->reorderSteps($form, $validated['step_ids']);

        return back()->with('success', __('course_applications.steps_reordered'));
    }

    public function storeField(Request $request, string $course, CourseApplicationFormStep $step)
    {
        Course::findOrFail($course);
        $this->assertStepBelongsToCourse($step, $course);

        $validated = $request->validate([
            'type' => ['required', Rule::in(CourseApplicationFormField::allTypes())],
            'label' => ['required', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
        ]);

        $validated['required'] = $request->boolean('required');
        $validated['config'] = $this->parseFieldConfig($request, $validated['type']);

        $this->forms->createField($step, $validated);

        return back()->with('success', __('course_applications.field_created'));
    }

    public function updateField(Request $request, string $course, CourseApplicationFormField $field)
    {
        Course::findOrFail($course);
        $this->assertFieldBelongsToCourse($field, $course);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
        ]);

        $validated['required'] = $request->boolean('required');
        $validated['config'] = $this->parseFieldConfig($request, $field->type);

        $this->forms->updateField($field, $validated);

        return back()->with('success', __('course_applications.field_updated'));
    }

    public function destroyField(string $course, CourseApplicationFormField $field)
    {
        Course::findOrFail($course);
        $this->assertFieldBelongsToCourse($field, $course);
        $this->forms->deleteField($field);

        return back()->with('success', __('course_applications.field_deleted'));
    }

    public function reorderFields(Request $request, string $course, CourseApplicationFormStep $step)
    {
        Course::findOrFail($course);
        $this->assertStepBelongsToCourse($step, $course);

        $validated = $request->validate([
            'field_ids' => ['required', 'array'],
            'field_ids.*' => ['integer'],
        ]);

        $this->forms->reorderFields($step, $validated['field_ids']);

        return back()->with('success', __('course_applications.fields_reordered'));
    }

    private function assertStepBelongsToCourse(CourseApplicationFormStep $step, string $courseId): void
    {
        abort_unless(
            (string) $step->form?->course_id === (string) $courseId,
            404
        );
    }

    private function assertFieldBelongsToCourse(CourseApplicationFormField $field, string $courseId): void
    {
        abort_unless(
            (string) $field->step?->form?->course_id === (string) $courseId,
            404
        );
    }

  /** @return array<string, mixed> */
    private function parseFieldConfig(Request $request, string $type): array
    {
        $config = $request->input('config', []);

        if (in_array($type, [
            CourseApplicationFormField::TYPE_SINGLE_CHOICE,
            CourseApplicationFormField::TYPE_DROPDOWN,
            CourseApplicationFormField::TYPE_MULTISELECT,
            CourseApplicationFormField::TYPE_CHECKBOX_GROUP,
        ], true)) {
            $options = [];
            $rawOptions = $config['options'] ?? [];
            foreach ($rawOptions as $option) {
                if (! empty($option['value']) && ! empty($option['label'])) {
                    $options[] = [
                        'value' => $option['value'],
                        'label' => $option['label'],
                    ];
                }
            }
            $config['options'] = $options;
        }

        return $config;
    }
}
