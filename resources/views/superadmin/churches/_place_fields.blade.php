@php
    $c = $church ?? null;
@endphp
<fieldset class="border rounded p-3">
    <legend class="float-none w-auto px-2 form-label mb-0">{{ __('tenancy.place_section') }}</legend>
    <p class="text-muted-theme small mb-3">{{ __('tenancy.place_section_hint') }}</p>

    <div class="mb-3">
        <label class="form-label" for="place-lookup-q">{{ __('tenancy.place_lookup') }}</label>
        <div class="input-group">
            <input id="place-lookup-q" type="text" class="form-control" maxlength="200" placeholder="{{ __('tenancy.place_lookup_placeholder') }}">
            <button type="button" class="btn btn-outline-secondary" id="place-lookup-btn">{{ __('tenancy.place_lookup_btn') }}</button>
        </div>
        <div class="list-group mt-2" id="place-lookup-results"></div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="place_country_code">{{ __('tenancy.place_country') }}</label>
            <select id="place_country_code" name="place_country_code" class="form-select @error('place_country_code') is-invalid @enderror" required>
                <option value="">{{ __('tenancy.place_country_placeholder') }}</option>
                @foreach($countries as $code)
                    <option value="{{ $code }}" @selected(old('place_country_code', $c?->place_country_code ?? request('place_country_code')) === $code)>
                        {{ __('countries.'.$code) !== 'countries.'.$code ? __('countries.'.$code) : $code }}
                    </option>
                @endforeach
            </select>
            @error('place_country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="place_governorate">{{ __('tenancy.place_governorate') }}</label>
            <input id="place_governorate" name="place_governorate" type="text"
                   class="form-control @error('place_governorate') is-invalid @enderror"
                   value="{{ old('place_governorate', $c?->place_governorate ?? request('place_governorate')) }}" maxlength="120">
            <div class="form-text">{{ __('tenancy.place_governorate_hint') }}</div>
            @error('place_governorate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="place_region">{{ __('tenancy.place_region') }}</label>
            <input id="place_region" name="place_region" type="text"
                   class="form-control @error('place_region') is-invalid @enderror"
                   value="{{ old('place_region', $c?->place_region ?? request('place_region')) }}" maxlength="120">
            @error('place_region')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="place_district">{{ __('tenancy.place_district') }}</label>
            <input id="place_district" name="place_district" type="text"
                   class="form-control @error('place_district') is-invalid @enderror"
                   value="{{ old('place_district', $c?->place_district ?? request('place_district')) }}" maxlength="120">
            @error('place_district')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="place_street">{{ __('tenancy.place_street') }}</label>
            <input id="place_street" name="place_street" type="text"
                   class="form-control @error('place_street') is-invalid @enderror"
                   value="{{ old('place_street', $c?->place_street ?? request('place_street')) }}" maxlength="191">
            @error('place_street')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</fieldset>
