<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Church;
use App\Support\ChurchPlace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChurchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_superadmin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->identityRules(), [
            'slug' => ['required', 'string', 'max:'.ChurchPlace::SLUG_MAX, 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
            'admin_user_ids' => ['nullable', 'array'],
            'admin_user_ids.*' => ['integer', 'exists:user,user_id'],
        ]);
    }

    /** @return array<string, mixed> */
    protected function identityRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.ChurchPlace::NAME_MAX],
            'short_name' => ['required', 'string', 'max:'.ChurchPlace::SHORT_NAME_MAX],
            'domain' => ['nullable', 'string', 'max:191'],
            'place_street' => ['nullable', 'string', 'max:191'],
            'place_district' => ['nullable', 'string', 'max:120'],
            'place_region' => ['nullable', 'string', 'max:120'],
            'place_governorate' => ['nullable', 'string', 'max:120'],
            'place_country_code' => ['required', 'string', 'size:2', Rule::in(config('countries'))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertPlaceDisambiguator($validator);
            $this->assertPlaceKeyUnique($validator);
            if ($this->filled('slug')) {
                $this->assertSlugAvailable($validator, (string) $this->input('slug'));
            }
        });
    }

    protected function assertPlaceDisambiguator(Validator $validator): void
    {
        $gov = trim((string) $this->input('place_governorate', ''));
        $district = trim((string) $this->input('place_district', ''));
        if ($gov === '' && $district === '') {
            $validator->errors()->add('place_governorate', __('tenancy.place_admin_required'));
        }
    }

    protected function assertPlaceKeyUnique(Validator $validator, ?int $ignoreChurchId = null): void
    {
        $key = ChurchPlace::placeKey([
            'name' => $this->input('name'),
            'place_country_code' => $this->input('place_country_code'),
            'place_governorate' => $this->input('place_governorate'),
            'place_district' => $this->input('place_district'),
        ]);

        if ($key === null) {
            return;
        }

        $query = Church::query()->where('place_key', $key);
        if ($ignoreChurchId) {
            $query->where('church_id', '!=', $ignoreChurchId);
        }
        if ($query->exists()) {
            $validator->errors()->add('name', __('tenancy.name_place_taken'));
        }
    }

    protected function assertSlugAvailable(Validator $validator, string $slug): void
    {
        $suggester = app(\App\Services\ChurchSlugSuggester::class);
        if (! $suggester->isAvailable(strtolower(trim($slug)))) {
            $validator->errors()->add('slug', __('tenancy.slug_taken'));
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => __('tenancy.invalid_slug'),
            'place_country_code.in' => __('tenancy.invalid_country'),
        ];
    }
}
