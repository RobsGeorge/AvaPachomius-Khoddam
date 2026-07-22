<?php

namespace App\Validation;

use Illuminate\Validation\Validator;

/**
 * Hardens Laravel's built-in email rule against CRLF injection (CVE-2026-48019 /
 * GHSA-5vg9-5847-vvmq). Official patches exist only for Laravel 12.60+ / 13.10+;
 * this app stays on Laravel 10 until the planned framework upgrade.
 */
class SafeValidator extends Validator
{
    /**
     * @param  mixed  $value
     * @param  array<int, int|string>  $parameters
     */
    public function validateEmail($attribute, $value, $parameters): bool
    {
        if (is_string($value) && preg_match('/[\r\n]/', $value)) {
            return false;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            $string = (string) $value;
            if (preg_match('/[\r\n]/', $string)) {
                return false;
            }
        }

        return parent::validateEmail($attribute, $value, $parameters);
    }
}
