<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\Course;
use App\Support\ChurchHost;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class ChurchSelfServeIsolationTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false, 'church_signup.enabled' => false]);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
        config([
            'church_signup.enabled' => true,
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'example.test',
        ]);
    }

    public function test_two_self_serve_churches_are_isolated_and_host_uses_slug(): void
    {
        Mail::fake();

        $churchA = $this->provisionSelfServe([
            'requested_name' => 'Isolation Parish A',
            'requested_short_name' => 'Iso A',
            'contact_email' => 'iso-a@church.example',
            'contact_mobile' => '01003334440',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
        ]);
        $churchB = $this->provisionSelfServe([
            'requested_name' => 'Isolation Parish B',
            'requested_short_name' => 'Iso B',
            'contact_email' => 'iso-b@church.example',
            'contact_mobile' => '01003334441',
            'place_district' => 'Maadi',
            'place_governorate' => 'Cairo',
        ]);

        $this->assertNotSame($churchA->church_id, $churchB->church_id);
        $this->assertStringContainsString($churchA->slug.'.example.test', ChurchHost::url($churchA, '/dashboard'));

        TenantContext::set($churchA);
        $courseA = Course::create([
            'title' => 'S1A_'.substr(uniqid(), -8),
            'description' => 'x',
            'year' => 2026,
        ]);
        $this->assertSame($churchA->church_id, $courseA->church_id);

        TenantContext::set($churchB);
        $this->assertNull(Course::find($courseA->course_id));

        TenantContext::set($churchA);
        $this->assertNotNull(Course::find($courseA->course_id));
    }

    public function test_two_one_service_accounts_may_share_name_and_place(): void
    {
        Mail::fake();

        $shared = [
            'requested_name' => 'Shared Youth Name',
            'requested_short_name' => 'Shared Youth',
            'place_district' => 'Nasr City',
            'place_governorate' => 'Cairo',
            'place_country_code' => 'EG',
            'account_kind' => Church::ACCOUNT_KIND_ONE_SERVICE,
            'contact_name' => 'Leader',
            'message' => null,
            'terms_accepted' => '1',
        ];

        $a = $this->provisionSelfServe(array_merge($shared, [
            'contact_email' => 'share-a@church.example',
            'contact_mobile' => '01003334442',
        ]));
        $b = $this->provisionSelfServe(array_merge($shared, [
            'contact_email' => 'share-b@church.example',
            'contact_mobile' => '01003334443',
        ]));

        $this->assertNull($a->place_key);
        $this->assertNull($b->place_key);
        $this->assertNotSame($a->church_id, $b->church_id);
    }

    /** @param  array<string, mixed>  $overrides */
    private function provisionSelfServe(array $overrides): Church
    {
        $payload = array_merge([
            'requested_name' => 'Self Serve Church',
            'requested_short_name' => 'Self Serve',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Founder',
            'contact_email' => 'founder@church.example',
            'contact_mobile' => '01003334449',
            'message' => null,
            'account_kind' => Church::ACCOUNT_KIND_PARISH,
            'terms_accepted' => '1',
        ], $overrides);

        $this->post(route('church-registration.store'), $payload)
            ->assertRedirect(route('church-registration.thanks'));

        $app = ChurchApplication::query()
            ->where('contact_email', $payload['contact_email'])
            ->latest('church_application_id')
            ->firstOrFail();

        $this->get(route('church-registration.verify', ['token' => $app->public_token]))
            ->assertRedirect(route('church-registration.status', ['token' => $app->public_token]));

        return $app->fresh()->church()->firstOrFail();
    }
}
