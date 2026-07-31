<?php

namespace Tests\Feature;

use App\Mail\ChurchApplicationSubmittedMail;
use App\Models\ChurchApplication;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

class ChurchApplicationVerificationTest extends EventModuleTestCase
{
    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'requested_name' => 'Verify Church',
            'requested_short_name' => 'Verify',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Father Verify',
            'contact_email' => 'verify@church.example',
            'contact_mobile' => '01005556666',
            'message' => 'Please review.',
        ], $overrides);
    }

    private function unverifiedApplication(array $overrides = []): ChurchApplication
    {
        return ChurchApplication::create(array_merge([
            'requested_name' => 'Unverified Church',
            'contact_name' => 'Contact',
            'contact_email' => 'unverified@example.com',
            'contact_mobile' => '01007778888',
            'status' => ChurchApplication::STATUS_UNVERIFIED,
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => null,
            'submitted_at' => now(),
        ], $overrides));
    }

    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'verify-super@example.com',
            'registration_completed' => true,
        ]);
    }

    public function test_submit_creates_unverified_and_sends_mail(): void
    {
        Mail::fake();

        $this->post(route('church-registration.store'), $this->validPayload())
            ->assertRedirect(route('church-registration.thanks'));

        $app = ChurchApplication::query()->first();
        $this->assertNotNull($app);
        $this->assertSame(ChurchApplication::STATUS_UNVERIFIED, $app->status);
        $this->assertNotNull($app->public_token);
        $this->assertNull($app->email_verified_at);

        Mail::assertSent(ChurchApplicationSubmittedMail::class, fn ($mail) => $mail->hasTo('verify@church.example'));
    }

    public function test_verify_promotes_to_pending_review(): void
    {
        $app = $this->unverifiedApplication();

        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        $app->refresh();
        $this->assertSame(ChurchApplication::STATUS_PENDING, $app->status);
        $this->assertNotNull($app->email_verified_at);

        $this->get(route('church-registration.status', ['token' => $app->public_token]))
            ->assertOk()
            ->assertSee(__('church_applications.status_pending_review'), false)
            ->assertSee('Unverified Church', false);
    }

    public function test_verify_is_idempotent(): void
    {
        $app = $this->unverifiedApplication();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]));
        $firstVerifiedAt = $app->fresh()->email_verified_at;

        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        $this->assertSame(ChurchApplication::STATUS_PENDING, $app->fresh()->status);
        $this->assertTrue($firstVerifiedAt->equalTo($app->fresh()->email_verified_at));
    }

    public function test_bad_token_returns_404(): void
    {
        $this->get(route('church-registration.verify', ['token' => str_repeat('a', 48)]))
            ->assertNotFound();
        $this->get(route('church-registration.status', ['token' => str_repeat('b', 48)]))
            ->assertNotFound();
    }

    public function test_unverified_hidden_from_superadmin_index(): void
    {
        $this->unverifiedApplication(['requested_name' => 'Hidden Unverified']);
        ChurchApplication::create([
            'requested_name' => 'Visible Pending',
            'contact_name' => 'Visible',
            'contact_email' => 'visible@example.com',
            'contact_mobile' => '01009998888',
            'status' => ChurchApplication::STATUS_PENDING,
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.church-applications.index'))
            ->assertOk()
            ->assertSee('Visible Pending', false)
            ->assertDontSee('Hidden Unverified', false);
    }

    public function test_after_verify_appears_in_superadmin_index(): void
    {
        $app = $this->unverifiedApplication(['requested_name' => 'Now Visible Church']);

        $this->get(route('church-registration.verify', ['token' => $app->public_token]));

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.church-applications.index'))
            ->assertOk()
            ->assertSee('Now Visible Church', false);
    }

    public function test_honeypot_still_creates_no_row_and_sends_no_mail(): void
    {
        Mail::fake();

        $this->post(route('church-registration.store'), $this->validPayload([
            'website' => 'https://bot.example',
        ]))->assertRedirect(route('church-registration.thanks'));

        $this->assertSame(0, ChurchApplication::query()->count());
        Mail::assertNothingSent();
    }

    public function test_status_shows_rejection_note(): void
    {
        $app = ChurchApplication::create([
            'requested_name' => 'Rejected Church',
            'contact_name' => 'Contact',
            'contact_email' => 'rejected@example.com',
            'contact_mobile' => '01001231234',
            'status' => ChurchApplication::STATUS_REJECTED,
            'admin_note' => 'Incomplete place details.',
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => now(),
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->get(route('church-registration.status', ['token' => $app->public_token]))
            ->assertOk()
            ->assertSee('Incomplete place details.', false);
    }

    public function test_verify_and_status_routes_are_throttled(): void
    {
        foreach (['church-registration.verify', 'church-registration.status'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        }
    }

    public function test_cannot_approve_unverified_application(): void
    {
        $app = $this->unverifiedApplication();

        $this->actingAs($this->superadmin())
            ->from(route('superadmin.church-applications.show', $app))
            ->post(route('superadmin.church-applications.approve', $app))
            ->assertRedirect()
            ->assertSessionHasErrors('application');

        $this->assertSame(ChurchApplication::STATUS_UNVERIFIED, $app->fresh()->status);
    }
}
