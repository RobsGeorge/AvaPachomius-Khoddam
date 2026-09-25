<?php

namespace Tests\Feature;

use App\Mail\ChurchApplicationProvisionedMail;
use App\Mail\ResetPasswordMail;
use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchSubscription;
use App\Models\ChurchUser;
use App\Models\User;
use App\Support\ChurchHost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ChurchSelfServeSignupTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
        config(['church_signup.enabled' => true]);
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'requested_name' => 'St George Trial Parish',
            'requested_short_name' => 'St George Trial',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Father George',
            'contact_email' => 'founder-s1@church.example',
            'contact_mobile' => '01001112220',
            'message' => 'Start trial',
            'account_kind' => Church::ACCOUNT_KIND_PARISH,
            'terms_accepted' => '1',
        ], $overrides);
    }

    public function test_flag_off_verify_still_queues_without_church(): void
    {
        config(['church_signup.enabled' => false]);

        $this->post(route('church-registration.store'), [
            'requested_name' => 'Queued Church',
            'contact_name' => 'Contact',
            'contact_email' => 'queue-s1@church.example',
            'contact_mobile' => '01001112221',
        ])->assertRedirect(route('church-registration.thanks'));

        $app = ChurchApplication::query()->firstOrFail();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        $app->refresh();
        $this->assertSame(ChurchApplication::STATUS_PENDING, $app->status);
        $this->assertNull($app->church_id);
        $this->assertSame(1, Church::query()->count());
    }

    public function test_verify_provisions_parish_trial_founder_and_password_mail(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-18 09:00:00');

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 's1-super@example.com',
        ]);

        $this->post(route('church-registration.store'), $this->validPayload())
            ->assertRedirect(route('church-registration.thanks'));

        $app = ChurchApplication::query()->firstOrFail();
        $this->assertSame(Church::ACCOUNT_KIND_PARISH, $app->account_kind);
        $this->assertNotNull($app->terms_accepted_at);

        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        $app->refresh();
        $this->assertSame(ChurchApplication::STATUS_APPROVED, $app->status);
        $this->assertNotNull($app->church_id);

        $church = $app->church()->firstOrFail();
        $this->assertSame(Church::ACCOUNT_KIND_PARISH, $church->account_kind);
        $this->assertTrue($church->hasCapability('church_management'));
        $this->assertTrue($church->hasCapability('curriculum'));
        $this->assertNotNull($church->place_key);

        $founder = User::query()->where('email', 'founder-s1@church.example')->firstOrFail();
        $this->assertSame(User::REGISTRATION_LANE_CHURCH_FOUNDER, $founder->registration_lane);
        $this->assertTrue((bool) $founder->is_verified);
        $this->assertSame(User::APPLICATION_STATUS_APPROVED, $founder->application_status);
        $this->assertTrue(
            ChurchUser::query()
                ->where('church_id', $church->church_id)
                ->where('user_id', $founder->user_id)
                ->exists()
        );

        $sub = ChurchSubscription::query()->where('church_id', $church->church_id)->firstOrFail();
        $this->assertSame('trialing', $sub->status);
        $this->assertNull($sub->plan_id);
        $this->assertTrue($sub->current_period_end->equalTo(now()->addDays(30)));

        Mail::assertSent(ResetPasswordMail::class, fn ($mail) => $mail->hasTo('founder-s1@church.example'));
        Mail::assertSent(ChurchApplicationProvisionedMail::class, fn ($mail) => $mail->hasTo($super->email));

        $this->get(route('church-registration.status', ['token' => $app->public_token]))
            ->assertOk()
            ->assertSee(__('church_applications.status_provisioned_hint'), false)
            ->assertSee($church->slug, false);

        $this->assertStringContainsString($church->slug, ChurchHost::url($church, '/login'));
    }

    public function test_one_service_skips_parish_ops_and_place_key_and_uses_seven_day_trial(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-09-18 09:00:00');

        $this->post(route('church-registration.store'), $this->validPayload([
            'requested_name' => 'Youth Service Only',
            'requested_short_name' => 'Youth Svc',
            'contact_email' => 'service-s1@church.example',
            'contact_mobile' => '01001112222',
            'account_kind' => Church::ACCOUNT_KIND_ONE_SERVICE,
        ]))->assertRedirect(route('church-registration.thanks'));

        $app = ChurchApplication::query()->firstOrFail();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]));

        $church = $app->fresh()->church()->firstOrFail();
        $this->assertSame(Church::ACCOUNT_KIND_ONE_SERVICE, $church->account_kind);
        $this->assertTrue($church->hasCapability('curriculum'));
        $this->assertFalse($church->hasCapability('church_management'));
        $this->assertNull($church->place_key);

        $sub = ChurchSubscription::query()->where('church_id', $church->church_id)->firstOrFail();
        $this->assertTrue($sub->current_period_end->equalTo(now()->addDays(7)));
    }

    public function test_verify_is_idempotent_and_does_not_duplicate_church(): void
    {
        Mail::fake();

        $this->post(route('church-registration.store'), $this->validPayload([
            'contact_email' => 'idem-s1@church.example',
            'contact_mobile' => '01001112223',
        ]));

        $app = ChurchApplication::query()->firstOrFail();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]));
        $churchId = $app->fresh()->church_id;
        $userCount = User::query()->where('email', 'idem-s1@church.example')->count();

        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        $this->assertSame($churchId, $app->fresh()->church_id);
        $this->assertSame(1, Church::query()->where('church_id', $churchId)->count());
        $this->assertSame($userCount, User::query()->where('email', 'idem-s1@church.example')->count());
    }

    public function test_second_signup_same_email_is_blocked_while_trial_open(): void
    {
        Mail::fake();

        $this->post(route('church-registration.store'), $this->validPayload())
            ->assertRedirect(route('church-registration.thanks'));

        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'requested_name' => 'Second Parish',
                'requested_short_name' => 'Second',
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors('contact_email');
    }

    public function test_self_serve_form_requires_scope_and_terms(): void
    {
        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'account_kind' => null,
                'terms_accepted' => null,
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors(['account_kind', 'terms_accepted']);
    }

    public function test_reserved_slug_is_not_assigned(): void
    {
        Mail::fake();

        $this->post(route('church-registration.store'), $this->validPayload([
            'requested_name' => 'Admin',
            'requested_short_name' => 'Admin',
            'contact_email' => 'admin-name-s1@church.example',
            'contact_mobile' => '01001112224',
        ]));

        $app = ChurchApplication::query()->firstOrFail();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]));

        $slug = $app->fresh()->church()->firstOrFail()->slug;
        $this->assertNotSame('admin', $slug);
        $this->assertFalse(app(\App\Services\ChurchSlugSuggester::class)->isReserved($slug));
    }

    public function test_existing_user_is_reused_as_founder(): void
    {
        Mail::fake();

        $existing = $this->createUser([
            'email' => 'existing-founder@church.example',
            'mobile_number' => '01001112225',
            'registration_lane' => User::REGISTRATION_LANE_OPEN,
        ]);

        $this->post(route('church-registration.store'), $this->validPayload([
            'contact_email' => $existing->email,
            'contact_mobile' => '01001112226',
        ]));

        $app = ChurchApplication::query()->firstOrFail();
        $this->get(route('church-registration.verify', ['token' => $app->public_token]));

        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
        $this->assertTrue(
            ChurchUser::query()
                ->where('church_id', $app->fresh()->church_id)
                ->where('user_id', $existing->user_id)
                ->exists()
        );
        Mail::assertNotSent(ResetPasswordMail::class);
        Mail::assertSent(\App\Mail\ChurchFounderReadyMail::class);
    }
}
