<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use App\Models\CourseApplicationFormStep;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseApplicationFormService
{
    public function getOrCreateForCourse(Course $course, ?User $creator = null): CourseApplicationForm
    {
        $studentRole = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->first();

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
