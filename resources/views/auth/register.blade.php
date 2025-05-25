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
                    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- الاسم الأول -->
                        <div class="row mb-3">
                            <label for="first_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الأول
                            </label>
                            <div class="col-md-6">
                                <input id="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror"
                                       name="first_name" value="{{ old('first_name') }}" required
                                       pattern="^[\u0621-\u064A\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('first_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- الاسم الثاني -->
                        <div class="row mb-3">
                            <label for="second_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الثاني
                            </label>
                            <div class="col-md-6">
                                <input id="second_name" type="text" class="form-control @error('second_name') is-invalid @enderror"
                                       name="second_name" value="{{ old('second_name') }}" required
                                       pattern="^[\u0621-\u064A\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('second_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- الاسم الثالث -->
                        <div class="row mb-3">
                            <label for="third_name" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-person-fill"></i> الاسم الثالث
                            </label>
                            <div class="col-md-6">
                                <input id="third_name" type="text" class="form-control @error('third_name') is-invalid @enderror"
                                       name="third_name" value="{{ old('third_name') }}" required
                                       pattern="^[\u0621-\u064A\s]+$"
                                       title="الرجاء إدخال الاسم باللغة العربية فقط">
                                @error('third_name')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- الرقم القومي -->
                        <div class="row mb-3">
                            <label for="national_id" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-card-text"></i> الرقم القومي
                            </label>
                            <div class="col-md-6">
                                <input id="national_id" type="text" class="form-control @error('national_id') is-invalid @enderror"
                                       name="national_id" value="{{ old('national_id') }}" required
                                       pattern="^\d{14}$" title="الرقم القومي يجب أن يكون 14 رقمًا بالضبط">
                                @error('national_id')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- رقم الهاتف -->
                        <div class="row mb-3">
                            <label for="mobile_number" class="col-md-4 col-form-label text-md-end">رقم الهاتف</label>
                            <div class="col-md-6 d-flex">
                                
                                <input id="mobile_number" type="text" class="form-control rounded-end @error('mobile_number') is-invalid @enderror"
                                    name="mobile_number" value="{{ old('mobile_number') }}" required
                                    style="direction: ltr;" maxlength="10">
                                <span class="input-group-text bg-white border rounded-start" style="direction: ltr;">+20</span>
                                
                                @error('mobile_number')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                            
                        </div>


                        <!-- البريد الإلكتروني -->
                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-envelope-fill"></i> البريد الإلكتروني
                            </label>
                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                       name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- الوظيفة -->
                        <div class="row mb-3">
                            <label for="job" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-briefcase-fill"></i> الوظيفة
                            </label>
                            <div class="col-md-6">
                                <input id="job" type="text" class="form-control @error('job') is-invalid @enderror"
                                       name="job" value="{{ old('job') }}" required>
                                @error('job')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- تاريخ الميلاد -->
                        <div class="row mb-3">
                            <label for="date_of_birth" class="col-md-4 col-form-label text-md-end">
                                <i class="bi bi-calendar-date-fill"></i> تاريخ الميلاد
                            </label>
                            <div class="col-md-6">
                                <input id="date_of_birth" type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                                @error('date_of_birth')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <!-- صورة الملف الشخصي -->
                        <div class="row mb-3">
                            <label for="profile_photo" class="col-md-4 col-form-label text-md-end">الصورة الشخصية</label>
                            <div class="col-md-6">
                                <input id="profile_photo" type="file" accept="image/*"
                                    class="form-control @error('profile_photo') is-invalid @enderror"
                                    name="profile_photo" required>
                                @error('profile_photo')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror

                                <!-- Circular Preview -->
                                <div class="mt-3 text-center">
                                    <img id="profilePreview" src="#" alt="صورة المعاينة"
                                        class="rounded-circle border shadow" style="width: 120px; height: 120px; object-fit: cover; display: none;">
                                </div>
                            </div>
                        </div>


                        <!-- زر التسجيل -->
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
    const input = document.getElementById('profile_photo');
    const preview = document.getElementById('profilePreview');

    input.addEventListener('change', function () {
        const file = input.files[0];

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'inline-block';
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const arabicRegex = /^[\u0621-\u064A\s]+$/;
        const nationalIdRegex = /^\d{14}$/;
        const mobileRegex = /^\d{10}$/;

        function showError(input, message) {
            let feedback = input.parentElement.querySelector('.instant-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.classList.add('text-danger', 'instant-feedback', 'mt-1');
                input.parentElement.appendChild(feedback);
            }
            feedback.innerText = message;
            input.classList.add('is-invalid');
        }

        function clearError(input) {
            const feedback = input.parentElement.querySelector('.instant-feedback');
            if (feedback) feedback.remove();
            input.classList.remove('is-invalid');
        }

        function validateArabic(input, label) {
            if (!arabicRegex.test(input.value.trim())) {
                showError(input, `الرجاء إدخال ${label} باللغة العربية فقط`);
            } else {
                clearError(input);
            }
        }

        function validateNationalId(input) {
            if (!nationalIdRegex.test(input.value.trim())) {
                showError(input, 'الرقم القومي يجب أن يكون 14 رقمًا');
            } else {
                clearError(input);
            }
        }

        function validateEmail(input) {
            if (!input.validity.valid) {
                showError(input, 'الرجاء إدخال بريد إلكتروني صالح');
            } else {
                clearError(input);
            }
        }

        document.getElementById('first_name').addEventListener('input', function () {
            validateArabic(this, 'الاسم الأول');
        });

        document.getElementById('second_name').addEventListener('input', function () {
            validateArabic(this, 'الاسم الثاني');
        });

        document.getElementById('third_name').addEventListener('input', function () {
            validateArabic(this, 'الاسم الثالث');
        });

        document.getElementById('national_id').addEventListener('input', function () {
            validateNationalId(this);
        });

        

        document.getElementById('email').addEventListener('input', function () {
            validateEmail(this);
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // File type validation
    function validateFile(inputId) {
        const input = document.getElementById(inputId);
        const errorDiv = document.getElementById(inputId + '_error');
        input.addEventListener('change', function () {
            const file = input.files[0];
            if (file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    input.classList.add('is-invalid');
                    errorDiv.classList.remove('d-none');
                } else {
                    input.classList.remove('is-invalid');
                    errorDiv.classList.add('d-none');
                }
            }
        });
    }

    validateFile('profile_photo');
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function validateFile(inputId) {
        const input = document.getElementById(inputId);
        const errorDiv = document.getElementById(inputId + '_error');
        const maxSize = 2 * 1024 * 1024; // 2MB

        input.addEventListener('change', function () {
            const file = input.files[0];
            if (file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                const isValidType = allowedTypes.includes(file.type);
                const isValidSize = file.size <= maxSize;

                if (!isValidType || !isValidSize) {
                    input.classList.add('is-invalid');
                    errorDiv.innerText = !isValidType 
                        ? 'يسمح فقط بصور أو ملفات PDF' 
                        : 'الملف يجب ألا يتجاوز 2 ميجا بايت';
                    errorDiv.classList.remove('d-none');
                } else {
                    input.classList.remove('is-invalid');
                    errorDiv.classList.add('d-none');
                }
            }
        });
    }

    validateFile('profile_photo');
});
</script>
@endpush

@endsection
