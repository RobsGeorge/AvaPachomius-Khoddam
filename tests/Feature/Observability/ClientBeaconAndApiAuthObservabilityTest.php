<?php

namespace Tests\Feature\Observability;

use App\Models\ObservabilityEvent;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ClientBeaconAndApiAuthObservabilityTest extends EventModuleTestCase
{
    public function test_client_error_beacon_persists_frontend_event(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->postJson(route('observability.client-errors'), [
            'message' => 'ReferenceError: foo is not defined',
            'source' => 'https://example.test/app.js',
            'lineno' => 12,
            'type' => 'window.onerror',
            'url' => 'https://example.test/dashboard',
            'stack' => 'ReferenceError: foo is not defined\n    at x',
        ])->assertOk()->assertJson(['ok' => true]);

        $event = ObservabilityEvent::withoutTenancy()
            ->where('category', 'frontend')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('foo is not defined', $event->message);
    }

    public function test_api_login_failure_records_auth_event(): void
    {
        Mail::fake();

        User::query()->where('email', 'api-obs@example.com')->delete();

        $this->postJson('/api/v1/login', [
            'identifier' => 'api-obs@example.com',
        ])->assertOk();

        $this->postJson('/api/v1/login/verify', [
            'identifier' => 'api-obs@example.com',
            'otp' => '000000',
        ])->assertStatus(422);

        $this->assertTrue(
            ObservabilityEvent::withoutTenancy()
                ->where('category', 'auth')
                ->where('message', 'like', '%API login failure%')
                ->exists()
        );
    }

    public function test_api_unverified_login_records_auth_event(): void
    {
        Mail::fake();

        $user = $this->createUser([
            'email' => 'api-unverified-obs@example.com',
            'password' => Hash::make('Password123!'),
            'registration_completed' => false,
            'is_verified' => false,
            'is_superadmin' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'identifier' => $user->email,
        ])->assertOk();

        $otp = OtpCode::query()->where('user_id', $user->user_id)->value('code');

        $this->postJson('/api/v1/login/verify', [
            'identifier' => $user->email,
            'otp' => (string) $otp,
        ])->assertStatus(422);

        $this->assertTrue(
            ObservabilityEvent::withoutTenancy()
                ->where('category', 'auth')
                ->where('message', 'like', '%Account not verified%')
                ->exists()
        );
    }
}
