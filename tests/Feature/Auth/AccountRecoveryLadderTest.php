<?php

namespace Tests\Feature\Auth;

use App\Models\AccessLedgerEntry;
use App\Models\AccountRecoveryChallenge;
use App\Models\User;
use App\Services\Auth\Recovery\AccountRecoveryService;
use App\Services\Auth\Recovery\AdminAssistedRecoveryService;
use App\Services\Auth\Recovery\CredentialChangeService;
use App\Services\Auth\Recovery\PossessionProof;
use App\Services\Auth\Recovery\RecoveryOtpVerifier;
use App\Services\Auth\Recovery\SupportRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\Support\EventModuleTestCase;

class AccountRecoveryLadderTest extends EventModuleTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.whatsapp.api_url' => 'https://graph.facebook.com/v18.0',
            'notifications.whatsapp.api_token' => 'test-token',
            'notifications.whatsapp.phone_number_id' => '1234567890',
        ]);
    }

    public function test_self_serve_rebind_works_via_second_verified_identifier(): void
    {
        Mail::fake();
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $user = $this->createUser([
            'email' => 'recover@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01011112222',
            'mobile_verified_at' => now(),
        ]);

        $recovery = app(AccountRecoveryService::class);
        $start = $recovery->beginSelfServeRebind(
            $user,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01033334444',
        );
        $this->assertTrue($start['ok']);
        $challenge = $start['challenge'];

        // Capture proof-channel OTP by re-hashing is hard; issue known code via direct update for phase verify path.
        // Re-start with controlled OTP by setting hash after create:
        $proofCode = '111111';
        $challenge->otp_hash = Hash::make($proofCode);
        $challenge->otp_expires_at = now()->addMinutes(10);
        $challenge->save();

        $verifier = app(RecoveryOtpVerifier::class);
        $advanced = $verifier->verify($challenge->fresh(), $proofCode);
        $this->assertSame('advanced', $advanced['status']);

        $assertedCode = '222222';
        $challenge = $advanced['challenge'];
        $challenge->otp_hash = Hash::make($assertedCode);
        $challenge->otp_expires_at = now()->addMinutes(10);
        $challenge->save();

        $minted = $verifier->verify($challenge->fresh(), $assertedCode);
        $this->assertSame('proof', $minted['status']);
        $this->assertInstanceOf(PossessionProof::class, $minted['proof']);

        $updated = app(CredentialChangeService::class)->completeRebind($minted['proof']);
        $this->assertSame('01033334444', $updated->mobile_number);
        $this->assertNotNull($updated->mobile_verified_at);

        $this->assertTrue(
            AccessLedgerEntry::query()->where('action', 'recovery')->where('subject_id', $user->user_id)->exists()
        );
        $this->assertTrue(
            AccessLedgerEntry::query()
                ->where('action', 'recovery')
                ->where('subject_id', $user->user_id)
                ->get()
                ->contains(fn ($e) => ($e->context['outcome'] ?? null) === 'alert_sent')
        );
    }

    public function test_admin_vouch_sends_otp_to_new_number(): void
    {
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.2']]], 200)]);
        Mail::fake();

        $admin = $this->createUser(['email' => 'admin-rec@example.com']);
        $subject = $this->createUser([
            'email' => 'member-rec@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01055556666',
            'mobile_verified_at' => now(),
        ]);

        $result = app(AdminAssistedRecoveryService::class)->vouchAndSendOtp(
            $admin,
            $subject,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01077778888',
        );
        $this->assertTrue($result['ok']);
        $challenge = $result['challenge'];
        $this->assertSame(AccountRecoveryChallenge::TIER_ADMIN_ASSISTED, $challenge->tier);
        $this->assertSame('01077778888', $challenge->asserted_value);
        $this->assertSame(AccountRecoveryChallenge::PHASE_ASSERTED, $challenge->phase);

        Http::assertSent(function ($request) {
            $to = $request['to'] ?? null;

            return str_contains($request->url(), '/messages') && $to === '201077778888';
        });

        $ref = new ReflectionClass(PossessionProof::class);
        $this->assertTrue($ref->getConstructor()->isPrivate());
    }

    public function test_complete_without_valid_possession_proof_fails(): void
    {
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.x']]], 200)]);
        Mail::fake();

        $admin = $this->createUser(['email' => 'admin-neg@example.com']);
        $subject = $this->createUser([
            'email' => 'member-neg@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01055550000',
            'mobile_verified_at' => now(),
        ]);
        $oldMobile = $subject->mobile_number;

        $result = app(AdminAssistedRecoveryService::class)->vouchAndSendOtp(
            $admin,
            $subject,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01077770000',
        );
        $challenge = $result['challenge'];

        $bogus = $this->makeInvalidProof($subject, $challenge);

        try {
            app(CredentialChangeService::class)->completeRebind($bogus);
            $this->fail('Expected ValidationException when completing without a valid proof token.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('proof', $e->errors());
        }

        $this->assertSame($oldMobile, $subject->fresh()->mobile_number);
    }

    public function test_admin_action_alone_does_not_flip_credential(): void
    {
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.3']]], 200)]);
        Mail::fake();

        $admin = $this->createUser(['email' => 'admin2@example.com']);
        $subject = $this->createUser([
            'email' => 'member2@example.com',
            'mobile_number' => '01012121212',
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        app(AdminAssistedRecoveryService::class)->vouchAndSendOtp(
            $admin,
            $subject,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01099990000',
        );

        $this->assertSame('01012121212', $subject->fresh()->mobile_number);
        $this->assertFalse(method_exists(AdminAssistedRecoveryService::class, 'completeRebind'));
        $this->assertFalse(method_exists(SupportRecoveryService::class, 'completeRebind'));
    }

    public function test_minor_and_safeguarding_block_self_serve(): void
    {
        Mail::fake();
        Http::fake();

        $minor = $this->createUser([
            'email' => 'minor@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01020000001',
            'mobile_verified_at' => now(),
            'is_minor' => true,
        ]);

        $result = app(AccountRecoveryService::class)->beginSelfServeRebind(
            $minor,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01020000002',
        );
        $this->assertFalse($result['ok']);
        $this->assertSame('self_serve_blocked', $result['reason']);

        $flagged = $this->createUser([
            'email' => 'flagged@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01020000003',
            'mobile_verified_at' => now(),
            'safeguarding_restricted' => true,
        ]);

        $result2 = app(AccountRecoveryService::class)->beginSelfServeRebind(
            $flagged,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01020000004',
        );
        $this->assertFalse($result2['ok']);
        $this->assertSame('self_serve_blocked', $result2['reason']);

        $this->assertTrue(
            AccountRecoveryChallenge::query()
                ->where('user_id', $minor->user_id)
                ->where('outcome', AccountRecoveryChallenge::OUTCOME_REJECTED)
                ->exists()
        );
    }

    public function test_rate_limit_and_ledger_and_notify_on_failed_attempt(): void
    {
        Mail::fake();
        Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.4']]], 200)]);

        $user = $this->createUser([
            'email' => 'ratelimit@example.com',
            'email_verified_at' => now(),
            'mobile_number' => '01030000001',
            'mobile_verified_at' => now(),
        ]);

        $recovery = app(AccountRecoveryService::class);
        for ($i = 0; $i < 5; $i++) {
            $recovery->beginSelfServeRebind(
                $user,
                AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
                '0103000001'.$i,
            );
        }

        $blocked = $recovery->beginSelfServeRebind(
            $user,
            AccountRecoveryChallenge::PURPOSE_REBIND_MOBILE,
            '01030000099',
        );
        $this->assertFalse($blocked['ok']);
        $this->assertSame('rate_limited', $blocked['reason']);

        $this->assertTrue(
            AccountRecoveryChallenge::query()
                ->where('user_id', $user->user_id)
                ->where('outcome', AccountRecoveryChallenge::OUTCOME_RATE_LIMITED)
                ->exists()
        );

        $this->assertGreaterThanOrEqual(
            6,
            AccessLedgerEntry::query()->where('action', 'recovery')->where('subject_id', $user->user_id)->count()
        );

        $alertCount = AccessLedgerEntry::query()
            ->where('action', 'recovery')
            ->where('subject_id', $user->user_id)
            ->get()
            ->filter(fn ($e) => ($e->context['outcome'] ?? null) === 'alert_sent')
            ->count();
        $this->assertGreaterThanOrEqual(6, $alertCount);
    }

    /**
     * Build a PossessionProof VO pointing at a nonexistent/wrong token hash — must fail complete.
     */
    private function makeInvalidProof(User $user, AccountRecoveryChallenge $challenge): PossessionProof
    {
        $record = \App\Models\PossessionProofRecord::query()->create([
            'account_recovery_challenge_id' => $challenge->account_recovery_challenge_id,
            'user_id' => $user->user_id,
            'token_hash' => hash('sha256', 'not-the-token'),
            'purpose' => $challenge->purpose,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        return PossessionProof::mintFromRecord($record, 'different-plaintext');
    }
}
