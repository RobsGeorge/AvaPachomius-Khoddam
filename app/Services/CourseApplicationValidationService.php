<?php

namespace App\Services;

use App\Models\CourseApplicationForm;
use App\Models\CourseApplicationFormField;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourseApplicationValidationService
{
    /** @return array<string, mixed> */
    public function rulesForForm(CourseApplicationForm $form, bool $includeFiles = true): array
    {
        $rules = [];
        $form->loadMissing('steps.fields');

        foreach ($form->steps as $step) {
            foreach ($step->fields as $field) {
                if (! $field->isInput()) {
                    continue;
                }

                $rules['fields.'.$field->field_key] = $this->rulesForField($field, $includeFiles);
            }
        }

        return $rules;
    }

    /** @return list<mixed> */
    private function rulesForField(CourseApplicationFormField $field, bool $includeFiles): array
    {
        $config = $field->config ?? [];
        $rules = [];

        if ($field->required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        return match ($field->type) {
            CourseApplicationFormField::TYPE_SHORT_TEXT,
            CourseApplicationFormField::TYPE_LONG_TEXT => array_merge($rules, [
                'string',
                isset($config['max_length']) ? 'max:'.(int) $config['max_length'] : 'max:5000',
            ]),
            CourseApplicationFormField::TYPE_EMAIL => array_merge($rules, ['email', 'max:255']),
            CourseApplicationFormField::TYPE_PHONE => array_merge($rules, ['string', 'max:30']),
            CourseApplicationFormField::TYPE_URL => array_merge($rules, ['url', 'max:500']),
            CourseApplicationFormField::TYPE_NUMBER => array_merge($rules, array_filter([
                'numeric',
                isset($config['min']) ? 'min:'.$config['min'] : null,
                isset($config['max']) ? 'max:'.$config['max'] : null,
            ])),
            CourseApplicationFormField::TYPE_DATE => array_merge($rules, ['date']),
            CourseApplicationFormField::TYPE_SINGLE_CHOICE,
            CourseApplicationFormField::TYPE_DROPDOWN => array_merge($rules, [
                Rule::in($this->optionValues($config)),
            ]),
            CourseApplicationFormField::TYPE_MULTISELECT,
            CourseApplicationFormField::TYPE_CHECKBOX_GROUP => array_merge($rules, [
                'array',
                isset($config['min_selections']) ? 'min:'.(int) $config['min_selections'] : null,
                isset($config['max_selections']) ? 'max:'.(int) $config['max_selections'] : null,
            ]),
            CourseApplicationFormField::TYPE_CHECKBOX => array_merge($rules, ['boolean']),
            CourseApplicationFormField::TYPE_FILE,
            CourseApplicationFormField::TYPE_IMAGE => $includeFiles
                ? array_merge($rules, $this->fileRules($field, $config))
                : ['nullable'],
            default => $rules,
        };
    }

    /** @param array<string, mixed> $config */
    private function fileRules(CourseApplicationFormField $field, array $config): array
    {
        $maxKb = (int) ($config['max_size_kb'] ?? 5120);
        $mimes = $config['allowed_mimes'] ?? ($field->type === CourseApplicationFormField::TYPE_IMAGE
            ? ['jpg', 'jpeg', 'png', 'webp']
            : ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

        return [
            'file',
            'max:'.$maxKb,
            'mimes:'.implode(',', $mimes),
        ];
    }

    /** @param array<string, mixed> $config @return list<string> */
    private function optionValues(array $config): array
    {
        $options = $config['options'] ?? [];

        return collect($options)
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function buildSnapshot(
        CourseApplicationForm $form,
        Request $request,
        array $validated,
        int $courseId,
        int $userId,
        ?array $existingSnapshot = null,
    ): array {
        $snapshot = [];
        $form->loadMissing('steps.fields');
        $fieldsInput = $validated['fields'] ?? [];

        foreach ($form->steps as $step) {
            foreach ($step->fields as $field) {
                if ($field->isLayout()) {
                    continue;
                }

                if (! $field->isInput()) {
                    continue;
                }

                $key = $field->field_key;

                if (in_array($field->type, [CourseApplicationFormField::TYPE_FILE, CourseApplicationFormField::TYPE_IMAGE], true)) {
                    $uploaded = $request->file("fields.{$key}");
                    if ($uploaded instanceof UploadedFile) {
                        $snapshot[$key] = $this->storeUpload($uploaded, $courseId, $userId, $key);
                    } elseif (isset($existingSnapshot[$key])) {
                        $snapshot[$key] = $existingSnapshot[$key];
                    } else {
                        $snapshot[$key] = null;
                    }
                    continue;
                }

                $value = $fieldsInput[$key] ?? null;

                if (in_array($field->type, [
                    CourseApplicationFormField::TYPE_MULTISELECT,
                    CourseApplicationFormField::TYPE_CHECKBOX_GROUP,
                ], true)) {
                    $snapshot[$key] = is_array($value) ? array_values($value) : [];
                    continue;
                }

                if ($field->type === CourseApplicationFormField::TYPE_CHECKBOX) {
                    $snapshot[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }

                $snapshot[$key] = $value;
            }
        }

        return $snapshot;
    }

    private function storeUpload(UploadedFile $file, int $courseId, int $userId, string $fieldKey): string
    {
        $directory = "course-applications/{$courseId}/{$userId}";
        $filename = $fieldKey.'_'.time().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'public');
    }
}
