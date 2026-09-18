<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Services\ChurchFounderProvisioner;
use App\Support\ChurchPlace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChurchApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
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

        if (ChurchFounderProvisioner::enabled()) {
            $rules['account_kind'] = ['required', Rule::in([
                Church::ACCOUNT_KIND_PARISH,
                Church::ACCOUNT_KIND_ONE_SERVICE,
            ])];
            $rules['terms_accepted'] = ['accepted'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        if (! ChurchFounderProvisioner::enabled()) {
            return;
        }

        $validator->after(function (Validator $validator) {
            $email = strtolower(trim((string) $this->input('contact_email')));
            if ($email === '') {
                return;
            }

            if (app(ChurchFounderProvisioner::class)->emailHasActiveSignup($email)) {
                $validator->errors()->add('contact_email', __('church_applications.one_trial_per_email'));
            }
        });
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
            'account_kind' => __('church_applications.account_kind'),
            'terms_accepted' => __('church_applications.terms_accepted'),
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
            'account_kind.in' => __('church_applications.validation_account_kind'),
            'account_kind.required' => __('church_applications.validation_account_kind'),
            'terms_accepted.accepted' => __('church_applications.validation_terms'),
        ];
    }
}
