<?php

namespace Tests\Feature\Tenancy;

use App\Models\AgePolicy;
use App\Models\Church;
use App\Models\ConsentLog;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Services\Maturity\ConsentLogRepository;
use App\Services\Maturity\EmancipationService;
use App\Services\Maturity\GuardianVisibilityGate;
use App\Services\Maturity\GuardianshipService;
use App\Services\Maturity\MaturityLadderService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

/**
 * Maturity ladder + guardianship (ADR Slice 6, regrounded onto Relationship).
 */
class MaturityLadderTest extends EventModuleTestCase
{
    private Church $church;

    private Organization $diocese;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = $this->createChurch([
            'slug' => 'maturity-church',
            'name' => 'Maturity Church',
        ]);

        $this->diocese = Organization::query()->create([
            'type' => Organization::TYPE_DIOCESE,
            'subdomain' => 'maturity-diocese-'.uniqid(),
            'name' => 'Maturity Diocese',
            'status' => 'active',
            'placement_policy' => Organization::PLACEMENT_SHARED,
        ]);

        $org = Organization::query()->findOrFail($this->church->organization_id);
        $org->update(['parent_id' => $this->diocese->organization_id]);

        // Non-18 majority to prove thresholds are policy-driven.
        AgePolicy::query()->create([
            'organization_id' => $this->diocese->organization_id,
            'digital_consent_age' => 14,
            'age_of_majority' => 16,
        ]);

        TenantContext::set($this->church);
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_rung0_child_can_share_parent_mobile_on_person_record(): void
    {
        $parentMobile = '01000000001';

        $parent = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'أب',
            'second_name' => 'اختبار',
            'third_name' => 'واحد',
            'date_of_birth' => '1985-01-01',
            'mobile_number' => $parentMobile,
            'is_minor' => false,
        ]);

        $child = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'طفل',
            'second_name' => 'اختبار',
            'third_name' => 'واحد',
            'date_of_birth' => '2018-01-01',
            'mobile_number' => $parentMobile,
            'is_minor' => true,
        ]);

        $this->assertSame($parentMobile, $child->mobile_number);
        $this->assertSame($parentMobile, $parent->mobile_number);
        $this->assertSame(0, User::query()->where('person_id', $child->person_id)->count());
        $this->assertSame(
            MaturityLadderService::RUNG_RECORD_ONLY,
            app(MaturityLadderService::class)->rung($child, $this->church)
        );
    }

    public function test_emancipation_uses_policy_majority_not_hardcoded_eighteen(): void
    {
        // Age 15 on frozen now (digital=14, majority=16) — open rung-2 first.
        [$guardianUser, $guardianPerson, $ward, $edge] = $this->seedGuardianWard(
            wardDob: '2011-07-01',
        );

        app(GuardianshipService::class)->openChildHeldAccount($ward, $guardianUser, [
            'email' => 'ward-held@example.com',
            'password' => 'password-password',
        ], $this->church);

        // Advance past policy majority (16), still well below a hardcoded 18.
        Carbon::setTestNow(Carbon::parse('2027-08-01 12:00:00'));
        $result = app(EmancipationService::class)->run(Carbon::parse('2027-08-01'));

        $this->assertSame(1, $result['ended']);
        $edge->refresh();
        $this->assertNotNull($edge->end_date);
        $this->assertSame('2027-08-01', $edge->end_date->format('Y-m-d'));
        $this->assertTrue(
            Relationship::withoutTenancy()->where('relationship_id', $edge->relationship_id)->exists(),
            'Edge must be preserved (history), never deleted'
        );

        $ladder = app(MaturityLadderService::class);
        $this->assertTrue($ladder->needsSelfConsent($ward->fresh()));
        $this->assertFalse($ladder->canReviewHeldData($ward->fresh()));

        // Re-open for the under-majority negative case (simulate pre-majority scan).
        $edge->forceFill(['end_date' => null])->save();
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00')); // age 15
        $early = app(EmancipationService::class)->run(Carbon::parse('2026-08-01'));
        $this->assertSame(0, $early['ended']);
        $this->assertNull($edge->fresh()->end_date);
    }

    public function test_guardian_cannot_suppress_emancipation_at_majority(): void
    {
        [$guardianUser, $guardianPerson, $ward, $edge] = $this->seedGuardianWard(
            wardDob: '2010-01-01',
        );

        // No suppress column / API — running the job still ends the edge.
        $result = app(EmancipationService::class)->run();
        $this->assertSame(1, $result['ended']);
        $this->assertNotNull($edge->fresh()->end_date);
        $this->assertTrue($edge->fresh()->exists());
    }

    public function test_guardian_can_open_rung2_after_digital_consent_before_majority(): void
    {
        [$guardianUser, $guardianPerson, $ward] = $this->seedGuardianWard(
            wardDob: '2011-08-01', // age 15 on test now; digital=14, majority=16
        );

        $user = app(GuardianshipService::class)->openChildHeldAccount($ward, $guardianUser, [
            'email' => 'teen@example.com',
            'password' => 'password-password',
        ], $this->church);

        $this->assertSame((int) $ward->person_id, (int) $user->person_id);
        $this->assertSame(
            MaturityLadderService::RUNG_CHILD_HELD,
            app(MaturityLadderService::class)->rung($ward->fresh(), $this->church)
        );
        $this->assertTrue(
            ConsentLog::withoutTenancy()
                ->where('person_id', $ward->person_id)
                ->where('scope', ConsentLog::SCOPE_RUNG2_CREDENTIAL)
                ->exists()
        );
    }

    public function test_guardian_visibility_restricted_hides_pastoral_stub(): void
    {
        // Slice 9 dependency: pastoral wall not fully built — gate stub must still hide.
        [$guardianUser, $guardianPerson, $ward, $edge] = $this->seedGuardianWard(
            wardDob: '2015-01-01',
        );

        $actor = $this->createUser(['email' => 'priest-vis@example.com']);
        $role = $this->createRole('Priest');
        // Grant permission key directly.
        $perm = \App\Models\Permission::query()->where('key', 'people.guardian_visibility.manage')->first();
        if (! $perm) {
            $this->artisan('permissions:sync');
            $perm = \App\Models\Permission::query()->where('key', 'people.guardian_visibility.manage')->firstOrFail();
        }
        $role->permissions()->syncWithoutDetaching([$perm->permission_id]);
        // Attach church role so can() resolves — use Gate via actingAs + forceFill is_superadmin for simplicity.
        $actor->forceFill(['is_superadmin' => true])->save();

        $updated = app(GuardianshipService::class)->setGuardianVisibility(
            $edge,
            Relationship::VISIBILITY_RESTRICTED,
            $actor
        );

        $gate = app(GuardianVisibilityGate::class);
        $this->assertTrue($gate->allows($updated, GuardianVisibilityGate::CATEGORY_CUSTODIAL));
        $this->assertFalse($gate->allows($updated, GuardianVisibilityGate::CATEGORY_PASTORAL));
        $this->assertFalse($gate->allows($updated, GuardianVisibilityGate::CATEGORY_PROTECTED));
        $this->assertFalse(
            $gate->guardianMaySee($guardianUser, $ward, GuardianVisibilityGate::CATEGORY_PASTORAL)
        );
    }

    public function test_under_age_self_registration_redirects_without_touching_existing_person(): void
    {
        $existing = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'موجود',
            'second_name' => 'طفل',
            'third_name' => 'سجل',
            'date_of_birth' => '2016-05-05',
            'national_id' => '29901011234567',
            'mobile_number' => '1099998888',
            'is_minor' => true,
        ]);
        $before = $existing->fresh()->toArray();

        $response = $this->post(route('register.store'), [
            'first_name' => 'موجود',
            'second_name' => 'طفل',
            'third_name' => 'سجل',
            'national_id' => '29901011234567',
            'mobile_number' => '1099998888',
            'email' => 'kid-self@example.com',
            'job' => 'طالب',
            'date_of_birth' => '2016-05-05',
        ]);

        $response->assertRedirect(route('register.ask-parent'));
        $this->assertEquals($before, $existing->fresh()->toArray());
        $this->assertFalse(User::query()->where('email', 'kid-self@example.com')->exists());
    }

    public function test_consent_log_refuses_update(): void
    {
        $person = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'أ',
            'date_of_birth' => '2012-01-01',
        ]);
        $entry = app(ConsentLogRepository::class)->append(
            $person,
            $person,
            ConsentLog::SCOPE_GUARDIAN_CUSTODY,
            $this->church->church_id
        );

        $this->expectException(\LogicException::class);
        app(ConsentLogRepository::class)->update($entry, ['scope' => 'tamper']);
    }

    public function test_self_emancipation_consent_grants_review_rights(): void
    {
        [$guardianUser, $guardianPerson, $ward, $edge] = $this->seedGuardianWard(
            wardDob: '2011-01-01', // age 15 on 2026-08-01
        );

        $childUser = app(GuardianshipService::class)->openChildHeldAccount($ward, $guardianUser, [
            'email' => 'emancipate@example.com',
            'password' => 'password-password',
        ], $this->church);

        Carbon::setTestNow(Carbon::parse('2027-08-01 12:00:00')); // age 16+
        app(EmancipationService::class)->run();
        $this->assertTrue(app(MaturityLadderService::class)->needsSelfConsent($ward->fresh()));

        app(GuardianshipService::class)->recordSelfEmancipationConsent($ward->fresh(), $childUser);
        $this->assertFalse(app(MaturityLadderService::class)->needsSelfConsent($ward->fresh()));
        $this->assertTrue(app(MaturityLadderService::class)->canReviewHeldData($ward->fresh()));
        $this->assertSame(
            MaturityLadderService::RUNG_EMANCIPATED,
            app(MaturityLadderService::class)->rung($ward->fresh(), $this->church)
        );
    }

    public function test_below_digital_consent_cannot_open_rung2(): void
    {
        [$guardianUser, , $ward] = $this->seedGuardianWard(wardDob: '2015-08-01'); // age 11

        $this->expectException(ValidationException::class);
        app(GuardianshipService::class)->openChildHeldAccount($ward, $guardianUser, [
            'email' => 'too-young@example.com',
            'password' => 'password-password',
        ], $this->church);
    }

    /**
     * @return array{0: User, 1: Person, 2: Person, 3: Relationship}
     */
    private function seedGuardianWard(string $wardDob): array
    {
        $guardianUser = $this->createUser([
            'email' => 'guardian-'.uniqid().'@example.com',
            'mobile_number' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'date_of_birth' => '1980-01-01',
        ]);
        $guardianPerson = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'وصي',
            'second_name' => 'اختبار',
            'third_name' => 'أ',
            'date_of_birth' => '1980-01-01',
            'mobile_number' => $guardianUser->mobile_number,
            'is_minor' => false,
        ]);
        $guardianUser->forceFill(['person_id' => $guardianPerson->person_id])->save();

        $ward = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'قاصر',
            'second_name' => 'اختبار',
            'third_name' => 'ب',
            'date_of_birth' => $wardDob,
            'mobile_number' => $guardianUser->mobile_number,
            'is_minor' => true,
        ]);

        $verifier = $this->createUser(['email' => 'verifier-'.uniqid().'@example.com']);
        $edge = app(GuardianshipService::class)->linkGuardian(
            $guardianPerson,
            $ward,
            $verifier,
            $this->church
        );

        $this->assertSame(Relationship::TYPE_GUARDIAN_OF, $edge->type);
        $this->assertSame((int) $verifier->user_id, (int) $edge->verified_by);
        $this->assertNull($edge->end_date);

        return [$guardianUser->fresh(), $guardianPerson, $ward, $edge];
    }
}
