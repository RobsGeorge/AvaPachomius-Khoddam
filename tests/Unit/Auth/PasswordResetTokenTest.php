<?php

namespace Tests\Unit\Auth;

use App\Auth\PasswordResetToken;
use PHPUnit\Framework\TestCase;

class PasswordResetTokenTest extends TestCase
{
    public function test_generate_returns_lowercase_hex(): void
    {
        $token = PasswordResetToken::generate();

        $this->assertSame(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
    }

    public function test_normalize_strips_bidi_marks_whitespace_and_hex_case(): void
    {
        $token = 'abCD'.str_repeat('ef01', 7);
        $mangled = "\u{202B} ".$token." \u{202C}\n";

        $this->assertSame(strtolower($token), PasswordResetToken::normalize($mangled));
    }

    public function test_normalize_urldecodes_the_token(): void
    {
        $this->assertSame('ab', PasswordResetToken::normalize('%61%42'));
    }
}
