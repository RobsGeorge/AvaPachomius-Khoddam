<?php

namespace App\Support;

use App\Rules\StrongPassword;

class PasswordRules
{
    /** Backend validation rules for a new password field. */
    public static function field(): array
    {
        return ['required', 'string', 'confirmed', new StrongPassword];
    }

    public static function messages(): array
    {
        return [
            'password.required'  => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
