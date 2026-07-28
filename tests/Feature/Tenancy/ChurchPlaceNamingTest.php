<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\User;
use App\Services\ChurchProvisioningService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ChurchPlaceNamingTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false]);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_provisioning_stores_short_name_place_and_syncs_public_profile(): void
    {
        $church = app(ChurchProvisioningService::class)->create([
            'slug' => 'st-mary-smouha',
            'name' => 'Saint Mary Coptic Orthodox Church Smouha',
            'short_name' => 'St Mary Smouha',
            'capabilities' => ['church_management'],
            'place_street' => '12 Nile St',
            'place_district' => 'Smouha',
            'place_region' => 'East',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
        ]);

        $this->assertSame('St Mary Smouha', $church->short_name);
        $this->assertSame('EG', $church->place_country_code);
        $this->assertSame('Alexandria', $church->place_governorate);
        $this->assertNotNull($church->place_key);
        $this->assertStringContainsString('Smouha', (string) data_get($church->settings, 'public.address'));
        $this->assertSame('Smouha', data_get($church->settings, 'public.city'));
        $this->assertStringContainsString('Smouha', $church->shownName());
        $this->assertSame('St Mary Smouha', $church->preferredShortName());
    }

    public function test_duplicate_name_and_place_is_rejected(): void
    {
        $payload = [
            'name' => 'St Mary',
            'short_name' => 'St Mary',
            'capabilities' => ['church_management'],
            'place_district' => 'Maadi',
            'place_governorate' => 'Cairo',
            'place_country_code' => 'EG',
        ];

        app(ChurchProvisioningService::class)->create(array_merge($payload, ['slug' => 'st-mary-maadi-a']));

        $this->expectException(ValidationException::class);
        app(ChurchProvisioningService::class)->create(array_merge($payload, ['slug' => 'st-mary-maadi-b']));
    }

    public function test_same_name_different_places_allowed(): void
    {
        $base = [
            'name' => 'St Mary',
            'short_name' => 'St Mary',
            'capabilities' => ['church_management'],
            'place_country_code' => 'EG',
        ];

        $a = app(ChurchProvisioningService::class)->create(array_merge($base, [
            'slug' => 'st-mary-alex',
            'place_governorate' => 'Alexandria',
            'place_district' => 'Smouha',
        ]));
        $b = app(ChurchProvisioningService::class)->create(array_merge($base, [
            'slug' => 'st-mary-cairo',
            'place_governorate' => 'Cairo',
            'place_district' => 'Maadi',
        ]));

        $this->assertNotSame($a->place_key, $b->place_key);
    }

    public function test_http_create_requires_place_and_short_name(): void
    {
        $admin = $this->createSuperadmin();

        $this->actingAs($admin)
            ->post(route('superadmin.churches.store'), [
                'slug' => 'needs-place',
                'name' => 'Needs Place Church',
                'capabilities' => ['church_management'],
            ])
            ->assertSessionHasErrors(['short_name', 'place_country_code']);
    }

    public function test_http_create_succeeds_with_identity_and_place(): void
    {
        $admin = $this->createSuperadmin();

        $response = $this->actingAs($admin)
            ->post(route('superadmin.churches.store'), [
                'slug' => 'st-george-brooklyn',
                'name' => 'St George Coptic Orthodox Church',
                'short_name' => 'St George Brooklyn',
                'place_country_code' => 'US',
                'place_governorate' => 'New York',
                'place_district' => 'Brooklyn',
                'place_street' => '419 7th St',
                'capabilities' => ['church_management'],
            ]);

        $church = Church::where('slug', 'st-george-brooklyn')->first();
        $this->assertNotNull($church);
        $response->assertRedirect(route('superadmin.churches.show', $church));
        $this->assertSame('US', $church->place_country_code);
        $this->assertSame('St George Brooklyn', $church->short_name);
    }

    public function test_suggest_slug_endpoint_returns_suggestions_and_shown_name(): void
    {
        $admin = $this->createSuperadmin();

        $this->actingAs($admin)
            ->getJson(route('superadmin.churches.suggest-slug', [
                'short_name' => 'St Mina',
                'name' => 'Saint Mina Church',
                'place_country_code' => 'EG',
                'place_governorate' => 'Giza',
            ]))
            ->assertOk()
            ->assertJsonStructure(['suggestions', 'shown_name'])
            ->assertJsonPath('suggestions.0', 'st-mina')
            ->assertJsonFragment(['shown_name' => 'St Mina — Giza, EG']);
    }

    public function test_update_identity_recomputes_place_key_and_syncs_org_region(): void
    {
        $church = app(ChurchProvisioningService::class)->create([
            'slug' => 'update-place-church',
            'name' => 'Update Place Church',
            'short_name' => 'Update Place',
            'capabilities' => ['church_management'],
            'place_country_code' => 'EG',
            'place_governorate' => 'Alexandria',
            'place_district' => 'Sporting',
        ]);

        $updated = app(ChurchProvisioningService::class)->updateIdentity($church, [
            'name' => 'Update Place Church',
            'short_name' => 'Sporting St Mark',
            'place_country_code' => 'EG',
            'place_governorate' => 'Alexandria',
            'place_district' => 'Sporting',
            'place_street' => 'New Street',
            'status' => 'active',
        ]);

        $this->assertSame('Sporting St Mark', $updated->short_name);
        $this->assertSame('Alexandria', $updated->organization?->region);
        $this->assertStringContainsString('New Street', (string) data_get($updated->settings, 'public.address'));
    }

    private function createSuperadmin(): User
    {
        return $this->createUser([
            'email' => 'place-sa-'.uniqid('', true).'@example.com',
            'is_superadmin' => true,
        ]);
    }
}
