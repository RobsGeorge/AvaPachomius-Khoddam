<?php

namespace Tests\Unit\Validation;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeEmailValidationTest extends TestCase
{
    public function test_email_rule_rejects_crlf_injection_payloads(): void
    {
        $payloads = [
            "victim@example.com\r\nBcc:attacker@evil.com",
            "victim@example.com\nCc:attacker@evil.com",
            "ok@example.com\r\nSubject: injected",
        ];

        foreach ($payloads as $email) {
            $validator = Validator::make(
                ['email' => $email],
                ['email' => ['required', 'email']]
            );

            $this->assertTrue(
                $validator->fails(),
                'Expected CRLF email payload to fail validation: '.json_encode($email)
            );
        }
    }

    public function test_email_rule_still_accepts_normal_addresses(): void
    {
        $validator = Validator::make(
            ['email' => 'servant@example.com'],
            ['email' => ['required', 'email']]
        );

        $this->assertFalse($validator->fails());
    }
}
