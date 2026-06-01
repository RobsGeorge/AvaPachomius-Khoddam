@extends('layouts.app')

@section('title', 'تسجيل حساب جديد')

@section('content')
<div class="container py-5 text-end" dir="rtl">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0 rounded">
                <div class="card-header bg-primary text-white text-center fs-4 fw-bold">
                    <i class="bi bi-person-plus-fill me-2"></i>تسجيل حساب جديد
                </div>

                <div class="card-body bg-light">

                    {{-- General errors (non-field errors from the controller) --}}
                    @if($errors->has('general'))
                        <div class="alert alert-danger d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div>{{ $errors->first('general') }}</div>
                        </div>
                    @elseif($errors->any())
                        <div class="alert alert-danger">
                            <strong>يرجى تصحيح الأخطاء التالية:</strong>
                            <ul class="mb-0 mt-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data"
                          id="registerForm" novalidate>
                        @csrf

                        {{-- الاسم الأول --}}
                        <div class="row mb-3">
                            <label for="first_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الأول <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="first_name" type="text"
                                       class="form-control @error('first_name') is-invalid @enderror"
                                       name="first_name" value="{{ old('first_name') }}" required
                                       pattern="^[ء-ي\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('first_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- الاسم الثاني --}}
                        <div class="row mb-3">
                            <label for="second_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الثاني <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="second_name" type="text"
                                       class="form-control @error('second_name') is-invalid @enderror"
                                       name="second_name" value="{{ old('second_name') }}" required
                                       pattern="^[ء-ي\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('second_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- الاسم الثالث --}}
                        <div class="row mb-3">
                            <label for="third_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الثالث <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="third_name" type="text"
                                       class="form-control @error('third_name') is-invalid @enderror"
                                       name="third_name" value="{{ old('third_name') }}" required
                                       pattern="^[ء-ي\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('third_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- الرقم القومي --}}
                        <div class="row mb-3">
                            <label for="national_id" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-card-text"></i> الرقم القومي <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="national_id" type="text"
                                       class="form-control @error('national_id') is-invalid @enderror"
                                       name="national_id" value="{{ old('national_id') }}" required
                                       pattern="^\d{14}$" title="الرقم القومي يجب أن يكون 14 رقمًا بالضبط"
                                       maxlength="14" style="direction:ltr;">
                                @error('national_id')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        {{-- رقم الهاتف --}}
                        <div class="row mb-3">
                            <label for="mobile_number" class="col-md-4 col-form-label text-md-end">
                                رقم الهاتف <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <div class="input-group" style="direction:ltr;">
                                    <span class="input-group-text">+20</span>
                                    <input id="mobile_number" type="tel"
                                           class="form-control @error('mobile_number') is-invalid @enderror"
                                           name="mobile_number" value="{{ old('mobile_number') }}" required
                                           pattern="\d{9,11}" minlength="9" maxlength="11"
                                           title="رقم الهاتف يجب أن يكون بين 9 و 11 رقمًا"
                                           inputmode="numeric">
                                    @error('mobile_number')
                                        <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="form-text text-end">9–11 رقمًا (مثال: 1012345678)</div>
                            </div>
                        </div>

                        {{-- البريد الإلكتروني --}}
                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-envelope-fill"></i> البريد الإلكتروني <span class="text-danger">*</span>
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

                        {{-- الوظيفة --}}
                        <div class="row mb-3">
                            <label for="job" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-briefcase-fill"></i> الوظيفة <span class="text-danger">*</span>
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

                        {{-- تاريخ الميلاد (dd/MM/YYYY display, YYYY-MM-DD submitted) --}}
                        <div class="row mb-3">
                            <label for="dob_display" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-calendar-date-fill"></i> تاريخ الميلاد <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input id="dob_display" type="text"
                                       class="form-control @error('date_of_birth') is-invalid @enderror"
                                       placeholder="DD/MM/YYYY" maxlength="10"
                                       pattern="\d{2}/\d{2}/\d{4}"
                                       title="أدخل تاريخ الميلاد بتنسيق يوم/شهر/سنة" required
                                       autocomplete="bday" style="direction:ltr;">
                                <input type="hidden" id="date_of_birth" name="date_of_birth"
                                       value="{{ old('date_of_birth') }}">
                                @error('date_of_birth')
                                    <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                @enderror
                                <div class="form-text text-end">مثال: 15/06/1990</div>
                            </div>
                        </div>

                        {{-- الصورة الشخصية (اختياري) --}}
                        <div class="row mb-3">
                            <label for="profile_photo" class="col-md-4 col-form-label text-md-end">
                                الصورة الشخصية
                                <span class="badge bg-secondary fw-normal">اختياري</span>
                            </label>
                            <div class="col-md-6">
                                <input id="profile_photo" type="file" accept="image/jpeg,image/png,image/jpg"
                                       class="form-control @error('profile_photo') is-invalid @enderror"
                                       name="profile_photo">
                                @error('profile_photo')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                                <div class="form-text text-end">JPG أو PNG — حد أقصى 2 ميجابايت</div>

                                <div class="mt-3 text-center" id="previewWrap" style="display:none;">
                                    <img id="profilePreview" src="#" alt="معاينة الصورة"
                                         class="rounded-circle border shadow"
                                         style="width:120px;height:120px;object-fit:cover;">
                                </div>
                            </div>
                        </div>

                        {{-- زر التسجيل --}}
                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4 d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle-fill"></i> تسجيل وإنشاء حساب
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <a href="{{ route('login') }}" class="text-decoration-none">
                            <i class="bi bi-box-arrow-in-left"></i> هل لديك حساب؟ تسجيل الدخول
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

    // ── Profile photo preview ─────────────────────────────────────────
    const photoInput = document.getElementById('profile_photo');
    const preview    = document.getElementById('profilePreview');
    const wrap       = document.getElementById('previewWrap');

    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) { wrap.style.display = 'none'; return; }

        const allowed = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowed.includes(file.type)) {
            photoInput.classList.add('is-invalid');
            wrap.style.display = 'none';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            photoInput.classList.add('is-invalid');
            wrap.style.display = 'none';
            return;
        }

        photoInput.classList.remove('is-invalid');
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            wrap.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // ── Date of birth: dd/MM/YYYY display → YYYY-MM-DD hidden field ──
    const dobDisplay = document.getElementById('dob_display');
    const dobHidden  = document.getElementById('date_of_birth');

    // Populate display from old() value (YYYY-MM-DD) on validation error
    if (dobHidden.value && /^\d{4}-\d{2}-\d{2}$/.test(dobHidden.value)) {
        const [y, m, d] = dobHidden.value.split('-');
        dobDisplay.value = `${d}/${m}/${y}`;
    }

    dobDisplay.addEventListener('input', function () {
        // Strip non-digits
        let digits = this.value.replace(/\D/g, '');

        // Auto-insert slashes
        let formatted = digits;
        if (digits.length > 2)  formatted = digits.slice(0, 2) + '/' + digits.slice(2);
        if (digits.length > 4)  formatted = formatted.slice(0, 5) + '/' + digits.slice(4, 8);
        this.value = formatted;

        // Update hidden field when complete
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(formatted)) {
            const [day, month, year] = formatted.split('/');
            dobHidden.value = `${year}-${month.padStart(2,'0')}-${day.padStart(2,'0')}`;
            dobDisplay.setCustomValidity('');
        } else {
            dobHidden.value = '';
            if (formatted.length > 0) {
                dobDisplay.setCustomValidity('أدخل التاريخ بتنسيق DD/MM/YYYY');
            }
        }
    });

    // Ensure hidden field is submitted when form submits
    document.getElementById('registerForm').addEventListener('submit', function (e) {
        const display = dobDisplay.value.trim();
        if (display && !/^\d{2}\/\d{2}\/\d{4}$/.test(display)) {
            e.preventDefault();
            dobDisplay.setCustomValidity('أدخل التاريخ بتنسيق DD/MM/YYYY');
            dobDisplay.reportValidity();
            return;
        }
        dobDisplay.setCustomValidity('');
    });

    // ── Phone: digits only, min 9 ─────────────────────────────────────
    const mobileInput = document.getElementById('mobile_number');
    mobileInput.addEventListener('input', function () {
        // Strip non-digits
        this.value = this.value.replace(/\D/g, '');

        if (this.value.length > 0 && (this.value.length < 9 || this.value.length > 11)) {
            this.setCustomValidity('رقم الهاتف يجب أن يكون بين 9 و 11 رقمًا');
        } else {
            this.setCustomValidity('');
        }
    });

    // ── Arabic name validation ────────────────────────────────────────
    const arabicRegex = /^[ء-ي\s]+$/;

    ['first_name', 'second_name', 'third_name'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('input', function () {
            if (this.value && !arabicRegex.test(this.value)) {
                this.setCustomValidity('الرجاء الكتابة باللغة العربية فقط');
            } else {
                this.setCustomValidity('');
            }
        });
    });

    // ── National ID: exactly 14 digits ───────────────────────────────
    const natId = document.getElementById('national_id');
    natId.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length > 0 && this.value.length !== 14) {
            this.setCustomValidity('الرقم القومي يجب أن يكون 14 رقمًا بالضبط');
        } else {
            this.setCustomValidity('');
        }
    });
});
</script>
@endpush

@endsection
