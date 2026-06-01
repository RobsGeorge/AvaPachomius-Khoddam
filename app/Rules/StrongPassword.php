<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (strlen($password) < 8) {
            $fail('يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.');
        }

        if (! preg_match('/[A-Z]/', $password)) {
            $fail('يجب أن تحتوي كلمة المرور على حرف كبير (A–Z) واحد على الأقل.');
        }

        if (! preg_match('/[0-9]/', $password)) {
            $fail('يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail('يجب أن تحتوي كلمة المرور على رمز خاص واحد على الأقل (مثل ! @ # $).');
        }
    }
}
