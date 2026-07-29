<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Church;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateChurchRequest extends StoreChurchRequest
{
    protected function prepareForValidation(): void
    {
        /** @var Church|null $church */
        $church = $this->route('church');
        if (! $church instanceof Church) {
            return;
        }

        $this->merge([
            'short_name' => $this->input('short_name', $church->short_name ?: $church->preferredShortName()),
            'place_street' => $this->input('place_street', $church->place_street),
            'place_district' => $this->input('place_district', $church->place_district),
            'place_region' => $this->input('place_region', $church->place_region),
            'place_governorate' => $this->input('place_governorate', $church->place_governorate),
            'place_country_code' => $this->input(
                'place_country_code',
                $church->place_country_code ?: 'EG'
            ),
        ]);

        // Legacy churches without admin place: allow update by seeding a disambiguator.
        if (! trim((string) $this->input('place_governorate'))
            && ! trim((string) $this->input('place_district'))) {
            $this->merge(['place_governorate' => 'Unspecified']);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->identityRules(), [
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'settings' => ['nullable', 'array'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertPlaceDisambiguator($validator);
            /** @var Church $church */
            $church = $this->route('church');
            $this->assertPlaceKeyUnique($validator, $church?->church_id);
        });
    }
}
