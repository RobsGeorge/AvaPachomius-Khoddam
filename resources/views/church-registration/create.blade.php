@extends('layouts.app')

@section('title', __('church_applications.public_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:720px;">
    <div class="text-center mb-4">
        <h1 class="page-title h3 mb-1">{{ __('church_applications.public_title') }}</h1>
        <p class="text-muted-theme">{{ __('church_applications.public_intro') }}</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('church-registration.store') }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            {{-- Honeypot: leave empty. Hidden from real users. --}}
            <div class="d-none" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label class="form-label" for="requested_name">
                    {{ __('church_applications.requested_name') }} <span class="text-danger">*</span>
                </label>
                <input id="requested_name" name="requested_name" type="text"
                       class="form-control @error('requested_name') is-invalid @enderror"
                       value="{{ old('requested_name') }}" required maxlength="120">
                @error('requested_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label" for="requested_short_name">{{ __('church_applications.requested_short_name') }}</label>
                <input id="requested_short_name" name="requested_short_name" type="text"
                       class="form-control @error('requested_short_name') is-invalid @enderror"
                       value="{{ old('requested_short_name') }}" maxlength="40">
                <div class="form-text">{{ __('church_applications.requested_short_name_hint') }}</div>
                @error('requested_short_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="place_country_code">{{ __('church_applications.place_country') }}</label>
                    <select id="place_country_code" name="place_country_code"
                            class="form-select @error('place_country_code') is-invalid @enderror">
                        <option value="">{{ __('church_applications.place_country_placeholder') }}</option>
                        @foreach($countries as $code)
                            <option value="{{ $code }}" @selected(old('place_country_code') === $code)>
                                {{ __('countries.'.$code) !== 'countries.'.$code ? __('countries.'.$code) : $code }}
                            </option>
                        @endforeach
                    </select>
                    @error('place_country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="place_governorate">{{ __('church_applications.place_governorate') }}</label>
                    <input id="place_governorate" name="place_governorate" type="text"
                           class="form-control @error('place_governorate') is-invalid @enderror"
                           value="{{ old('place_governorate') }}" maxlength="120">
                    @error('place_governorate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="place_district">{{ __('church_applications.place_district') }}</label>
                    <input id="place_district" name="place_district" type="text"
                           class="form-control @error('place_district') is-invalid @enderror"
                           value="{{ old('place_district') }}" maxlength="120">
                    @error('place_district')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-text mt-0">{{ __('church_applications.place_fields_hint') }}</div>
                </div>
            </div>

            <div>
                <label class="form-label" for="contact_name">
                    {{ __('church_applications.contact_name') }} <span class="text-danger">*</span>
                </label>
                <input id="contact_name" name="contact_name" type="text"
                       class="form-control @error('contact_name') is-invalid @enderror"
                       value="{{ old('contact_name') }}" required maxlength="120">
                @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="contact_email">
                        {{ __('church_applications.contact_email') }} <span class="text-danger">*</span>
                    </label>
                    <input id="contact_email" name="contact_email" type="email"
                           class="form-control @error('contact_email') is-invalid @enderror"
                           value="{{ old('contact_email') }}" required maxlength="191">
                    @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_mobile">
                        {{ __('church_applications.contact_mobile') }} <span class="text-danger">*</span>
                    </label>
                    <input id="contact_mobile" name="contact_mobile" type="text"
                           class="form-control @error('contact_mobile') is-invalid @enderror"
                           value="{{ old('contact_mobile') }}" required maxlength="40">
                    @error('contact_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label class="form-label" for="message">{{ __('church_applications.message') }}</label>
                <textarea id="message" name="message" rows="4"
                          class="form-control @error('message') is-invalid @enderror"
                          maxlength="5000">{{ old('message') }}</textarea>
                <div class="form-text">{{ __('church_applications.message_hint') }}</div>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <button type="submit" class="btn btn-primary">{{ __('church_applications.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
