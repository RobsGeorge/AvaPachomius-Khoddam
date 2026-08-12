<?php

namespace Tests\Unit\Console;

use App\Console\Commands\QaCourseTestersCommand;
use App\Services\QaCourseTestersService;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class QaCourseTestersValidationTest extends TestCase
{
    private function invoke(string $method, mixed ...$args): mixed
    {
        $cmd = $this->app->make(QaCourseTestersCommand::class);
        $ref = new ReflectionMethod(QaCourseTestersCommand::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($cmd, ...$args);
    }

    public function test_validate_password_rejects_short_secrets(): void
    {
        $this->assertSame(
            'Password must be at least 8 characters when provided.',
            $this->invoke('validatePassword', 'short')
        );
        $this->assertSame(
            'Password must be at least 8 characters when provided.',
            $this->invoke('validatePassword', '1234567')
        );
    }

    public function test_validate_password_accepts_eight_or_more_chars(): void
    {
        $this->assertNull($this->invoke('validatePassword', '12345678'));
        $this->assertNull($this->invoke('validatePassword', 'QaTestPass1!'));
    }

    public function test_validate_domain_rejects_empty_at_spaces_and_bad_hosts(): void
    {
        $this->assertSame('Email domain cannot be empty.', $this->invoke('validateDomain', ' '));
        $this->assertSame(
            'Email domain must not include @ or spaces.',
            $this->invoke('validateDomain', 'user@evil.test')
        );
        $this->assertSame(
            'Email domain must not include @ or spaces.',
            $this->invoke('validateDomain', 'has space.test')
        );
        $this->assertNotNull($this->invoke('validateDomain', 'not_a_valid_host'));
        $this->assertNotNull($this->invoke('validateDomain', 'localhost'));
        $this->assertNotNull($this->invoke('validateDomain', '-bad.example'));
    }

    public function test_validate_domain_accepts_reserved_and_normal_hosts(): void
    {
        $this->assertNull($this->invoke('validateDomain', 'avapakhomios.qa'));
        $this->assertNull($this->invoke('validateDomain', 'example.test'));
        $this->assertNull($this->invoke('validateDomain', 'matrix.test'));
        $this->assertNull($this->invoke('validateDomain', 'church.example.com'));
    }

    public function test_service_provision_rejects_short_password(): void
    {
        $qa = $this->app->make(QaCourseTestersService::class);

        try {
            $qa->provision(
                password: 'short',
                domain: 'svcreject.test',
                courseIds: [1],
                writeCredentials: false,
                admins: 1,
                instructors: 0,
                students: 0,
            );
            $this->fail('Expected ValidationException for short password');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'at least 8 characters',
                collect($e->errors())->flatten()->implode(' ')
            );
        }
    }
}
