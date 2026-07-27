<?php

namespace Tests\Feature\Observability;

use App\Models\ObservabilityEvent;
use App\Observability\ObservabilityRecorder;
use App\Observability\Sinks\NullErrorSink;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class PlatformObservabilityTest extends EventModuleTestCase
{
    public function test_recorder_persists_event_without_secrets(): void
    {
        $this->assertTrue(Schema::hasTable('observability_events'));

        $recorder = new ObservabilityRecorder(new NullErrorSink());
        $recorder->record('auth', 'warning', 'Login failure: Invalid credentials', [
            'password' => 'should-not-store',
            'email' => 'someone@example.com',
        ]);

        $event = ObservabilityEvent::withoutTenancy()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('auth', $event->category);
        $this->assertSame('[redacted]', $event->context['password'] ?? null);
        $this->assertSame('someone@example.com', $event->context['email'] ?? null);
    }

    public function test_superadmin_can_open_observability_incidents(): void
    {
        ObservabilityEvent::withoutTenancy()->create([
            'occurred_at' => now(),
            'severity' => 'error',
            'category' => 'exception',
            'fingerprint' => hash('sha256', 'test-fp'),
            'message' => 'Boom for observability UI',
            'exception_class' => 'RuntimeException',
        ]);

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'obs-super@example.com',
            'registration_completed' => true,
            'is_verified' => true,
        ]);

        $this->actingAs($super)
            ->get(route('superadmin.observability.index'))
            ->assertOk()
            ->assertSee('Boom for observability UI', false);
    }

    public function test_non_superadmin_cannot_open_platform_observability(): void
    {
        $user = $this->createUser([
            'is_superadmin' => false,
            'email' => 'obs-member@example.com',
            'registration_completed' => true,
            'is_verified' => true,
        ]);

        $this->actingAs($user)
            ->get(route('superadmin.observability.index'))
            ->assertForbidden();
    }

    public function test_failed_web_login_records_auth_event(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'missing-user@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect();

        $this->assertTrue(
            ObservabilityEvent::withoutTenancy()
                ->where('category', 'auth')
                ->where('message', 'like', '%Invalid credentials%')
                ->exists()
        );
    }

    public function test_assign_request_id_header_present(): void
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }
}
