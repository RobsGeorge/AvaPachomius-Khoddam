<?php

namespace App\Http\Requests;

use App\Support\ChurchPlace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChurchApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'requested_name' => ['required', 'string', 'max:'.ChurchPlace::NAME_MAX],
            'requested_short_name' => ['nullable', 'string', 'max:'.ChurchPlace::SHORT_NAME_MAX],
            'place_district' => ['nullable', 'string', 'max:120'],
            'place_governorate' => ['nullable', 'string', 'max:120'],
            'place_country_code' => ['nullable', 'string', 'size:2', Rule::in(config('countries'))],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:191'],
            'contact_mobile' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:191'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'requested_name' => __('church_applications.requested_name'),
            'requested_short_name' => __('church_applications.requested_short_name'),
            'place_district' => __('church_applications.place_district'),
            'place_governorate' => __('church_applications.place_governorate'),
            'place_country_code' => __('church_applications.place_country'),
            'contact_name' => __('church_applications.contact_name'),
            'contact_email' => __('church_applications.contact_email'),
            'contact_mobile' => __('church_applications.contact_mobile'),
            'message' => __('church_applications.message'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => __('church_applications.validation_required'),
            'email' => __('church_applications.validation_email'),
            'max' => __('church_applications.validation_max'),
            'in' => __('church_applications.validation_country'),
            'size' => __('church_applications.validation_country'),
        ];
    }
}
