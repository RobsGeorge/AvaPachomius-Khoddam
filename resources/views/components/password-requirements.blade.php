@props([
    'passwordId' => 'password',
    'confirmId' => 'password_confirmation',
    'formId' => null,
])

<div id="{{ $passwordId }}-requirements" class="password-requirements mt-2 mb-3" dir="rtl">
    <p class="form-text mb-2 fw-semibold">يجب أن تحتوي كلمة المرور على:</p>
    <ul class="list-unstyled small mb-0">
        <li class="password-rule d-flex align-items-center gap-2 mb-1" data-rule="length">
            <i class="bi bi-circle text-muted rule-icon" aria-hidden="true"></i>
            <span>8 أحرف على الأقل</span>
        </li>
        <li class="password-rule d-flex align-items-center gap-2 mb-1" data-rule="uppercase">
            <i class="bi bi-circle text-muted rule-icon" aria-hidden="true"></i>
            <span>حرف كبير واحد على الأقل (A–Z)</span>
        </li>
        <li class="password-rule d-flex align-items-center gap-2 mb-1" data-rule="number">
            <i class="bi bi-circle text-muted rule-icon" aria-hidden="true"></i>
            <span>رقم واحد على الأقل (0–9)</span>
        </li>
        <li class="password-rule d-flex align-items-center gap-2 mb-1" data-rule="special">
            <i class="bi bi-circle text-muted rule-icon" aria-hidden="true"></i>
            <span>رمز خاص واحد على الأقل (! @ # $ …)</span>
        </li>
        <li class="password-rule d-flex align-items-center gap-2" data-rule="match">
            <i class="bi bi-circle text-muted rule-icon" aria-hidden="true"></i>
            <span>تطابق كلمة المرور مع التأكيد</span>
        </li>
    </ul>
    <div id="{{ $passwordId }}-feedback" class="invalid-feedback d-block" style="display: none;"></div>
</div>

@once
    @push('scripts')
        <script>
            window.PasswordValidator = {
                checks: {
                    length: (value) => value.length >= 8,
                    uppercase: (value) => /[A-Z]/.test(value),
                    number: (value) => /[0-9]/.test(value),
                    special: (value) => /[^A-Za-z0-9]/.test(value),
                    match: (password, confirm) => password.length > 0 && password === confirm,
                },

                evaluate(password, confirm) {
                    return {
                        length: this.checks.length(password),
                        uppercase: this.checks.uppercase(password),
                        number: this.checks.number(password),
                        special: this.checks.special(password),
                        match: this.checks.match(password, confirm),
                    };
                },

                isValid(password, confirm) {
                    const results = this.evaluate(password, confirm);
                    return Object.values(results).every(Boolean);
                },

                bind({ passwordId, confirmId, formId, requirementsId }) {
                    const passwordInput = document.getElementById(passwordId);
                    const confirmInput = document.getElementById(confirmId);
                    const form = document.getElementById(formId);
                    const requirements = document.getElementById(requirementsId);

                    if (!passwordInput || !confirmInput || !form || !requirements) {
                        return;
                    }

                    const feedback = requirements.querySelector(`#${passwordId}-feedback`);
                    const ruleItems = requirements.querySelectorAll('.password-rule');

                    const updateUi = () => {
                        const results = this.evaluate(passwordInput.value, confirmInput.value);
                        let allValid = true;

                        ruleItems.forEach((item) => {
                            const rule = item.dataset.rule;
                            const passed = results[rule];
                            const icon = item.querySelector('.rule-icon');

                            item.classList.toggle('text-success', passed);
                            item.classList.toggle('text-muted', !passed);

                            if (icon) {
                                icon.className = passed
                                    ? 'bi bi-check-circle-fill text-success rule-icon'
                                    : 'bi bi-circle text-muted rule-icon';
                            }

                            if (!passed) {
                                allValid = false;
                            }
                        });

                        passwordInput.classList.toggle('is-invalid', passwordInput.value.length > 0 && !allValid);
                        confirmInput.classList.toggle(
                            'is-invalid',
                            confirmInput.value.length > 0 && !results.match
                        );

                        return allValid;
                    };

                    const validate = () => {
                        const valid = updateUi();

                        if (passwordInput.value.length === 0) {
                            passwordInput.setCustomValidity('كلمة المرور مطلوبة.');
                        } else if (!valid) {
                            passwordInput.setCustomValidity('كلمة المرور لا تستوفي جميع الشروط المطلوبة.');
                        } else {
                            passwordInput.setCustomValidity('');
                        }

                        if (confirmInput.value.length === 0) {
                            confirmInput.setCustomValidity('تأكيد كلمة المرور مطلوب.');
                        } else if (passwordInput.value !== confirmInput.value) {
                            confirmInput.setCustomValidity('كلمتا المرور غير متطابقتين.');
                        } else {
                            confirmInput.setCustomValidity('');
                        }

                        return valid && form.checkValidity();
                    };

                    passwordInput.addEventListener('input', validate);
                    confirmInput.addEventListener('input', validate);

                    form.addEventListener('submit', (event) => {
                        if (!validate()) {
                            event.preventDefault();
                            event.stopPropagation();

                            if (feedback) {
                                feedback.textContent = 'يرجى استيفاء جميع شروط كلمة المرور قبل المتابعة.';
                                feedback.style.display = 'block';
                            }
                        } else if (feedback) {
                            feedback.style.display = 'none';
                        }

                        form.classList.add('was-validated');
                    });
                },
            };
        </script>
    @endpush
@endonce

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            PasswordValidator.bind({
                passwordId: @json($passwordId),
                confirmId: @json($confirmId),
                formId: @json($formId ?? 'set-password-form'),
                requirementsId: @json($passwordId . '-requirements'),
            });
        });
    </script>
@endpush
