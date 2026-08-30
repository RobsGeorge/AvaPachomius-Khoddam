<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationFormStep;
use App\Models\RegistrationApplication;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseApplicationFormService
{
    public function getOrCreateForCourse(Course $course, ?User $creator = null): CourseApplicationForm
    {
        $studentRole = Role::studentRoleForCourse($course->course_id)
            ?? Role::query()
                ->whereNull('course_id')
                ->where('slug', 'student')
                ->where('is_template', true)
                ->first();

        return CourseApplicationForm::query()->firstOrCreate(
            ['course_id' => $course->course_id],
            [
                'is_enabled' => false,
                'title' => $course->title,
                'default_role_id' => $studentRole?->role_id,
                'created_by_user_id' => $creator?->user_id,
            ]
        );
    }

    /**
     * Public signup (and course create) must be able to submit a course application
     * even when admins never opened the form builder. Closed/archived courses are
     * excluded by the caller via {@see Course::isActive()}.
     */
    public function ensureReadyForPublicSignup(Course $course): CourseApplicationForm
    {
        $this->ensureStudentRoleForCourse($course);

        $form = $this->getOrCreateForCourse($course);
        $studentRole = Role::studentRoleForCourse($course->course_id);

        $dirty = false;
        if (! $form->is_enabled) {
            $form->is_enabled = true;
            $dirty = true;
        }
        if (! $form->default_role_id && $studentRole) {
            $form->default_role_id = $studentRole->role_id;
            $dirty = true;
        }
        if (! filled($form->title)) {
            $form->title = $course->title;
            $dirty = true;
        }
        if ($dirty) {
            $form->save();
        }

        $this->ensureDefaultSignupFields($form);

        return $form->fresh(['steps.fields']);
    }

    /**
     * Backfill every currently-active course so existing rows become enrollable.
     * Cross-church on purpose: migrate/console has no request tenant.
     */
    public function provisionActiveCoursesForPublicSignup(): int
    {
        $count = 0;

        // withoutTenancy: artisan/migrate backfill must cover every church, not
        // only a bound Tenant Zero (CLAUDE.md rule 3).
        Course::query()
            ->withoutTenancy()
            ->orderBy('course_id')
            ->get()
            ->filter(fn (Course $course) => $course->isActive())
            ->each(function (Course $course) use (&$count) {
                $this->ensureReadyForPublicSignup($course);
                $count++;
            });

        return $count;
    }

    public function ensureDefaultSignupFields(CourseApplicationForm $form): void
    {
        $form->loadMissing('steps.fields');
        $existingKeys = $form->steps
            ->flatMap(fn (CourseApplicationFormStep $step) => $step->fields)
            ->pluck('field_key')
            ->filter()
            ->all();

        if ($existingKeys !== []) {
            return;
        }

        $step = $form->steps->first();
        if (! $step) {
            $step = $this->createStep($form, [
                'title' => __('course_applications.signup_default_step'),
            ]);
        }

        foreach ($this->defaultSignupFieldDefs() as $def) {
            $this->createField($step, [
                'field_key' => $def['field_key'],
                'type' => $def['type'],
                'label' => __('registration_review.fields.'.$def['field_key']),
                'required' => $def['required'],
            ]);
        }
    }

    /** @return list<array{field_key: string, type: string, required: bool}> */
    private function defaultSignupFieldDefs(): array
    {
        $types = [
            'first_name' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'second_name' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'third_name' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'national_id' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'mobile_number' => CourseApplicationFormField::TYPE_PHONE,
            'email' => CourseApplicationFormField::TYPE_EMAIL,
            'job' => CourseApplicationFormField::TYPE_SHORT_TEXT,
            'date_of_birth' => CourseApplicationFormField::TYPE_DATE,
            'profile_photo' => CourseApplicationFormField::TYPE_IMAGE,
        ];

        $defs = [];
        foreach (RegistrationApplication::REVIEWABLE_FIELDS as $key) {
            $defs[] = [
                'field_key' => $key,
                'type' => $types[$key] ?? CourseApplicationFormField::TYPE_SHORT_TEXT,
                'required' => $key !== 'profile_photo' && $key !== 'job',
            ];
        }

        return $defs;
    }

    private function ensureStudentRoleForCourse(Course $course): void
    {
        if (Role::studentRoleForCourse($course->course_id)) {
            return;
        }

        $templates = app(RoleTemplateService::class);
        $templates->ensureSystemTemplates();

        if (Role::studentRoleForCourse($course->course_id)) {
            return;
        }

        $templates->cloneTemplatesIntoCourse($course);
    }

    public function updateForm(CourseApplicationForm $form, array $data): CourseApplicationForm
    {
        $form->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'title' => $data['title'] ?? $form->title,
            'description' => $data['description'] ?? null,
            'default_role_id' => $data['default_role_id'] ?? $form->default_role_id,
            'settings' => $data['settings'] ?? $form->settings,
        ]);

        return $form->fresh(['steps.fields']);
    }

    public function createStep(CourseApplicationForm $form, array $data): CourseApplicationFormStep
    {
        $maxOrder = $form->steps()->max('order_index') ?? -1;

        return $form->steps()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'order_index' => $maxOrder + 1,
        ]);
    }

    public function updateStep(CourseApplicationFormStep $step, array $data): CourseApplicationFormStep
    {
        $step->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        return $step->fresh('fields');
    }

    public function deleteStep(CourseApplicationFormStep $step): void
    {
        $step->delete();
    }

    public function reorderSteps(CourseApplicationForm $form, array $stepIds): void
    {
        DB::transaction(function () use ($form, $stepIds) {
            foreach ($stepIds as $index => $stepId) {
                CourseApplicationFormStep::query()
                    ->where('form_id', $form->id)
                    ->whereKey($stepId)
                    ->update(['order_index' => $index]);
            }
        });
    }

    public function createField(CourseApplicationFormStep $step, array $data): CourseApplicationFormField
    {
        $form = $step->form ?? $step->form()->firstOrFail();
        $fieldKey = $this->uniqueFieldKey($form, $data['field_key'] ?? $data['label'] ?? 'field');
        $maxOrder = $step->fields()->max('order_index') ?? -1;

        return $step->fields()->create([
            'field_key' => $fieldKey,
            'type' => $data['type'],
            'label' => $data['label'],
            'help_text' => $data['help_text'] ?? null,
            'required' => (bool) ($data['required'] ?? false),
            'order_index' => $maxOrder + 1,
            'config' => $data['config'] ?? [],
        ]);
    }

    public function updateField(CourseApplicationFormField $field, array $data): CourseApplicationFormField
    {
        $field->update([
            'label' => $data['label'],
            'help_text' => $data['help_text'] ?? null,
            'required' => (bool) ($data['required'] ?? false),
            'config' => $data['config'] ?? $field->config,
        ]);

        return $field->fresh();
    }

    public function deleteField(CourseApplicationFormField $field): void
    {
        $field->delete();
    }

    public function reorderFields(CourseApplicationFormStep $step, array $fieldIds): void
    {
        DB::transaction(function () use ($step, $fieldIds) {
            foreach ($fieldIds as $index => $fieldId) {
                CourseApplicationFormField::query()
                    ->where('step_id', $step->id)
                    ->whereKey($fieldId)
                    ->update(['order_index' => $index]);
            }
        });
    }

    private function uniqueFieldKey(CourseApplicationForm $form, string $base): string
    {
        $slug = Str::slug($base, '_');
        if ($slug === '') {
            $slug = 'field';
        }

        $candidate = $slug;
        $suffix = 1;

        while ($this->fieldKeyExists($form, $candidate)) {
            $candidate = $slug.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function fieldKeyExists(CourseApplicationForm $form, string $key): bool
    {
        return CourseApplicationFormField::query()
            ->whereHas('step', fn ($q) => $q->where('form_id', $form->id))
            ->where('field_key', $key)
            ->exists();
    }

    public function assertFieldKeyUnique(CourseApplicationForm $form, string $key, ?int $exceptFieldId = null): void
    {
        $query = CourseApplicationFormField::query()
            ->whereHas('step', fn ($q) => $q->where('form_id', $form->id))
            ->where('field_key', $key);

        if ($exceptFieldId) {
            $query->where('id', '!=', $exceptFieldId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'field_key' => __('course_applications.field_key_taken'),
            ]);
        }
    }
}
