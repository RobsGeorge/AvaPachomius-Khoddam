@extends('layouts.app')

@section('title', __('auth.register_title'))

@section('content')
<div class="container py-5 animate-in">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="app-card card shadow-lg border-0 rounded">
                <div class="card-header bg-primary text-white text-center fs-4 fw-bold">
                    <i class="bi bi-person-plus-fill me-2"></i>{{ __('auth.register_title') }}
                </div>

                <div class="card-body">
                    @if($errors->has('general'))
                        <div class="alert alert-danger d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div>{{ $errors->first('general') }}</div>
                        </div>
                    @elseif($errors->any())
                        <div class="alert alert-danger">
                            <strong>{{ __('register.fix_errors') }}</strong>
                            <ul class="mb-0 mt-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data"
                          id="registerForm" novalidate>
                        @csrf

                        @foreach(['first_name', 'second_name', 'third_name'] as $field)
                        <div class="row mb-3">
                            <label for="{{ $field }}" class="col-md-4 col-form-label">
                                <i class="bi bi-person-fill"></i> {{ __('register.' . $field) }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="{{ $field }}" type="text"
                                       class="form-control @error($field) is-invalid @enderror"
                                       name="{{ $field }}" value="{{ old($field) }}" required
                                       pattern="^[ء-ي\s]+$"
                                       title="{{ __('register.arabic_only') }}">
                                @error($field)
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        @endforeach

                        <div class="row mb-3">
                            <label for="national_id" class="col-md-4 col-form-label">
                                <i class="bi bi-card-text"></i> {{ __('register.national_id') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="national_id" type="text"
                                       class="form-control @error('national_id') is-invalid @enderror"
                                       name="national_id" value="{{ old('national_id') }}" required
                                       pattern="^\d{14}$" title="{{ __('register.national_id_hint') }}"
                                       maxlength="14" style="direction:ltr;">
                                @error('national_id')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="mobile_number" class="col-md-4 col-form-label">
                                {{ __('register.phone') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <div class="input-group" style="direction:ltr;">
                                    <span class="input-group-text">+20</span>
                                    <input id="mobile_number" type="tel"
                                           class="form-control @error('mobile_number') is-invalid @enderror"
                                           name="mobile_number" value="{{ old('mobile_number') }}" required
                                           pattern="\d{9,11}" minlength="9" maxlength="11"
                                           title="{{ __('register.phone_validation') }}"
                                           inputmode="numeric">
                                    @error('mobile_number')
                                        <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="form-text text-muted-theme">{{ __('register.phone_hint') }}</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label">
                                <i class="bi bi-envelope-fill"></i> {{ __('register.email') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="email" type="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="job" class="col-md-4 col-form-label">
                                <i class="bi bi-briefcase-fill"></i> {{ __('register.job') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="job" type="text"
                                       class="form-control @error('job') is-invalid @enderror"
                                       name="job" value="{{ old('job') }}" required>
                                @error('job')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="dob_display" class="col-md-4 col-form-label">
                                <i class="bi bi-calendar-date-fill"></i> {{ __('register.birth_date') }} <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="dob_display" type="text"
                                       class="form-control @error('date_of_birth') is-invalid @enderror"
                                       placeholder="DD/MM/YYYY" maxlength="10"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       title="{{ __('register.birth_format') }}" required
                                       autocomplete="bday" style="direction:ltr;">
                                <input type="hidden" id="date_of_birth" name="date_of_birth"
                                       value="{{ old('date_of_birth') }}">
                                @error('date_of_birth')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                                <div class="form-text text-muted-theme">{{ __('register.birth_hint') }}</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="profile_photo" class="col-md-4 col-form-label">
                                {{ __('register.profile_photo') }}
                                <span class="badge bg-secondary fw-normal">{{ __('register.optional') }}</span>
                            </label>
                            <div class="col-md-6">
                                <input id="profile_photo" type="file" accept="image/jpeg,image/png,image/jpg"
                                       class="form-control @error('profile_photo') is-invalid @enderror"
                                       name="profile_photo">
                                @error('profile_photo')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                                <div class="form-text text-muted-theme">{{ __('register.photo_hint') }}</div>

                                <div class="mt-3 text-center" id="previewWrap" style="display:none;">
                                    <img id="profilePreview" src="#" alt="{{ __('register.photo_preview') }}"
                                         class="rounded-circle border shadow"
                                         style="width:120px;height:120px;object-fit:cover;">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4 d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle-fill"></i> {{ __('register.submit') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="{{ route('login') }}" class="text-decoration-none text-muted-theme">
                            <i class="bi bi-box-arrow-in-left"></i> {{ __('register.have_account') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const messages = {
        dateFormatInvalid: @json(__('register.date_format_invalid')),
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

    const dobDisplay = document.getElementById('dob_display');
    const dobHidden  = document.getElementById('date_of_birth');

    if (dobHidden.value && /^\d{4}-\d{2}-\d{2}$/.test(dobHidden.value)) {
        const [y, m, d] = dobHidden.value.split('-');
        dobDisplay.value = `${d}/${m}/${y}`;
    }

    dobDisplay.addEventListener('input', function () {
        let digits = this.value.replace(/\D/g, '');
        let formatted = digits;
        if (digits.length > 2)  formatted = digits.slice(0, 2) + '/' + digits.slice(2);
        if (digits.length > 4)  formatted = formatted.slice(0, 5) + '/' + digits.slice(4, 8);
        this.value = formatted;
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(formatted)) {
            const [day, month, year] = formatted.split('/');
            dobHidden.value = `${year}-${month.padStart(2,'0')}-${day.padStart(2,'0')}`;
            dobDisplay.setCustomValidity('');
        } else {
            dobHidden.value = '';
            if (formatted.length > 0) dobDisplay.setCustomValidity(messages.dateFormatInvalid);
        }
    });

    document.getElementById('registerForm').addEventListener('submit', function (e) {
        const display = dobDisplay.value.trim();
        if (display && !/^\d{2}\/\d{2}\/\d{4}$/.test(display)) {
            e.preventDefault();
            dobDisplay.setCustomValidity(messages.dateFormatInvalid);
            dobDisplay.reportValidity();
            return;
        }
        dobDisplay.setCustomValidity('');
    });

    const mobileInput = document.getElementById('mobile_number');
    mobileInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length > 0 && (this.value.length < 9 || this.value.length > 11)) {
            this.setCustomValidity(messages.phoneValidation);
        } else {
            this.setCustomValidity('');
        }
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
        this.value = this.value.replace(/\D/g, '');
        this.setCustomValidity(this.value.length > 0 && this.value.length !== 14 ? messages.nationalIdHint : '');
    });
});
</script>
@endpush
@endsection
