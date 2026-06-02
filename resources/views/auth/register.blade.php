@extends('layouts.app')

@section('title', __('auth.register_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:720px;">

    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.register_title') }}</h2>
        <p class="text-muted-theme small">{{ __('app.tagline') }}</p>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>{{ $errors->first('general') }}</div>
        </div>
    @elseif($errors->any())
        <div class="alert alert-danger mb-3">
            <strong>{{ __('register.fix_errors') }}</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data"
                  id="registerForm" novalidate>
                @csrf

                @foreach(['first_name', 'second_name', 'third_name'] as $field)
                <div class="mb-3">
                    <label for="{{ $field }}" class="form-label">
                        <i class="bi bi-person-fill"></i> {{ __('register.' . $field) }} <span class="text-danger">*</span>
                    </label>
                    <input id="{{ $field }}" type="text"
                           class="form-control @error($field) is-invalid @enderror"
                           name="{{ $field }}" value="{{ old($field) }}" required
                           pattern="^[ء-ي\s]+$"
                           title="{{ __('register.arabic_only') }}">
                    @error($field)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                @endforeach

                <div class="mb-3">
                    <label for="national_id" class="form-label">
                        <i class="bi bi-card-text"></i> {{ __('register.national_id') }} <span class="text-danger">*</span>
                    </label>
                    <input id="national_id" type="text"
                           class="form-control @error('national_id') is-invalid @enderror"
                           name="national_id" value="{{ old('national_id') }}" required
                           pattern="^\d{14}$" title="{{ __('register.national_id_hint') }}"
                           maxlength="14" style="direction:ltr;" inputmode="numeric">
                    @error('national_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="mobile_number" class="form-label">
                        <i class="bi bi-telephone-fill"></i> {{ __('register.phone') }} <span class="text-danger">*</span>
                    </label>
                    <div class="input-group phone-input-group" style="direction:ltr;">
                        <span class="input-group-text">+20</span>
                        <input id="mobile_number" type="tel"
                               class="form-control @error('mobile_number') is-invalid @enderror"
                               name="mobile_number" value="{{ old('mobile_number') }}" required
                               pattern="\d{9}" minlength="9" maxlength="9"
                               title="{{ __('register.phone_validation') }}"
                               inputmode="numeric"
                               placeholder="{{ __('register.phone_placeholder') }}">
                    </div>
                    @error('mobile_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted-theme">{{ __('register.phone_hint') }}</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope-fill"></i> {{ __('register.email') }} <span class="text-danger">*</span>
                    </label>
                    <input id="email" type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="job" class="form-label">
                        <i class="bi bi-briefcase-fill"></i> {{ __('register.job') }} <span class="text-danger">*</span>
                    </label>
                    <input id="job" type="text"
                           class="form-control @error('job') is-invalid @enderror"
                           name="job" value="{{ old('job') }}" required>
                    @error('job')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="date_of_birth" class="form-label">
                        <i class="bi bi-calendar-date-fill"></i> {{ __('register.birth_date') }} <span class="text-danger">*</span>
                    </label>
                    <input id="date_of_birth" name="date_of_birth" type="text"
                           class="form-control dob-flatpickr @error('date_of_birth') is-invalid @enderror"
                           value="{{ old('date_of_birth') }}"
                           placeholder="{{ __('register.birth_placeholder') }}"
                           required autocomplete="bday"
                           aria-label="{{ __('register.birth_picker') }}">
                    @error('date_of_birth')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted-theme">{{ __('register.birth_hint') }}</div>
                </div>

                <div class="mb-4">
                    <label for="profile_photo" class="form-label">
                        <i class="bi bi-camera-fill"></i> {{ __('register.profile_photo') }}
                        <span class="badge bg-secondary fw-normal">{{ __('register.optional') }}</span>
                    </label>
                    <input id="profile_photo" type="file" accept="image/jpeg,image/png,image/jpg"
                           class="form-control @error('profile_photo') is-invalid @enderror"
                           name="profile_photo">
                    @error('profile_photo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted-theme">{{ __('register.photo_hint') }}</div>

                    <div class="mt-3 text-center" id="previewWrap" style="display:none;">
                        <img id="profilePreview" src="#" alt="{{ __('register.photo_preview') }}"
                             class="rounded-circle border shadow profile-preview-img">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-check-circle-fill"></i> {{ __('register.submit') }}
                </button>
            </form>
        </div>

        <div class="card-footer text-center py-3">
            <a href="{{ route('login') }}" class="text-muted-theme text-decoration-none">
                <i class="bi bi-box-arrow-in-left"></i> {{ __('register.have_account') }}
            </a>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@if(request()->cookie('theme', 'light') === 'dark')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
@endif
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@if(app()->getLocale() === 'ar')
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const messages = {
        dateRequired: @json(__('register.date_required')),
        phoneValidation: @json(__('register.phone_validation')),
        arabicValidation: @json(__('register.arabic_validation')),
        nationalIdHint: @json(__('register.national_id_hint')),
    };

    const photoInput = document.getElementById('profile_photo');
    const preview    = document.getElementById('profilePreview');
    const wrap       = document.getElementById('previewWrap');

    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) { wrap.style.display = 'none'; return; }
        const allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowed.includes(file.type) || file.size > 2 * 1024 * 1024) {
            photoInput.classList.add('is-invalid');
            wrap.style.display = 'none';
            return;
        }
        photoInput.classList.remove('is-invalid');
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; wrap.style.display = 'block'; };
        reader.readAsDataURL(file);
    });

    const dobInput = document.getElementById('date_of_birth');
    const maxDate = new Date();
    const minDate = new Date();
    minDate.setFullYear(minDate.getFullYear() - 100);

    const fpOptions = {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        altInputClass: 'form-control dob-flatpickr-alt',
        maxDate: maxDate,
        minDate: minDate,
        allowInput: false,
        disableMobile: true,
        defaultDate: dobInput.value || null,
    };

    @if(app()->getLocale() === 'ar')
    fpOptions.locale = flatpickr.l10ns.ar;
    @endif

    const dobPicker = flatpickr(dobInput, fpOptions);

    if (dobInput.classList.contains('is-invalid') && dobPicker.altInput) {
        dobPicker.altInput.classList.add('is-invalid');
    }

    document.getElementById('registerForm').addEventListener('submit', function (e) {
        if (!dobInput.value) {
            e.preventDefault();
            if (dobPicker.altInput) {
                dobPicker.altInput.setCustomValidity(messages.dateRequired);
                dobPicker.altInput.reportValidity();
            } else {
                dobInput.setCustomValidity(messages.dateRequired);
                dobInput.reportValidity();
            }
            return;
        }
        if (dobPicker.altInput) dobPicker.altInput.setCustomValidity('');
    });

    const mobileInput = document.getElementById('mobile_number');
    mobileInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
        this.setCustomValidity(this.value.length > 0 && this.value.length !== 9 ? messages.phoneValidation : '');
    });

    const arabicRegex = /^[ء-ي\s]+$/;
    ['first_name', 'second_name', 'third_name'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('input', function () {
            this.setCustomValidity(this.value && !arabicRegex.test(this.value) ? messages.arabicValidation : '');
        });
    });

    const natId = document.getElementById('national_id');
    natId.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 14);
        this.setCustomValidity(this.value.length > 0 && this.value.length !== 14 ? messages.nationalIdHint : '');
    });
});
</script>
@endpush
@endsection
