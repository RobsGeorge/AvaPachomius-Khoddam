<?php

namespace Tests\Feature\UseCases\Account;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Http;
use Tests\Support\EventModuleTestCase;

/**
 * Contact Verification CV1 (narrow slice) — self-service mobile verification from
 * notification settings. Covers send-code, verify, invalid code, and missing number.
 */
class MobileVerificationTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.whatsapp.api_url' => 'https://graph.facebook.com/v18.0',
            'notifications.whatsapp.api_token' => 'test-token',
            'notifications.whatsapp.phone_number_id' => '1234567890',
        ]);
    }

    public function test_settings_page_shows_unverified_status_and_send_code_action(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('notifications.settings'))
            ->assertOk()
            ->assertSee(route('notifications.settings.mobile.send-code'))
            ->assertSee(__('notifications.mobile_unverified_badge'));
    }

    public function test_user_can_send_and_verify_a_mobile_code(): void
    {
        Http::fake([
            '*/messages' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        $user = $this->createUser();

        $this->actingAs($user)
            ->post(route('notifications.settings.mobile.send-code'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $otp = OtpCode::where('user_id', $user->user_id)->first();
        $this->assertNotNull($otp);

        $this->actingAs($user)
            ->post(route('notifications.settings.mobile.verify'), [
                'code' => $otp->code,
                'whatsapp_capable' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertTrue((bool) $user->whatsapp_capable);
        $this->assertNull(OtpCode::where('user_id', $user->user_id)->first());
    }

    public function test_invalid_code_is_rejected(): void
    {
        $user = $this->createUser();
        OtpCode::updateOrCreate(
            ['user_id' => $user->user_id],
            ['code' => 111111, 'expires_at' => now()->addMinutes(10)]
        );

        $this->actingAs($user)
            ->post(route('notifications.settings.mobile.verify'), ['code' => '999999'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_send_code_requires_a_mobile_number_on_file(): void
    {
        $user = $this->createUser(['mobile_number' => '']);

        $this->actingAs($user)
            ->post(route('notifications.settings.mobile.send-code'))
            ->assertSessionHasErrors('mobile');

        $this->assertNull(OtpCode::where('user_id', $user->user_id)->first());
    }
}
