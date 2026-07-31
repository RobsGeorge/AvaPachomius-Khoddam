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
}
