<?php

namespace Tests\Feature\PublicSite;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchUser;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\PublicSite\ChurchPublicProfile;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * T10a — public church profile (settings.public) + guest /about page.
 */
class ChurchPublicProfileTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    public function test_permissions_sync_registers_public_site_keys(): void
    {
        foreach (['public_site.profile', 'public_site.theme', 'public_site.manage', 'public_site.publish'] as $key) {
            $this->assertDatabaseHas('permissions', ['key' => $key]);
        }
    }

    public function test_tenant_zero_has_public_site_capability(): void
    {
        $this->assertTrue(Church::main()->hasCapability('public_site'));
    }

    public function test_church_admin_can_update_public_profile(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->put(route('church.public-profile.update'), [
                'tagline' => ['ar' => 'كنيسة الاختبار', 'en' => 'Test Church'],
                'about' => ['ar' => 'نبذة', 'en' => 'About us'],
                'address' => '123 Main St',
                'city' => 'Cairo',
                'phone' => '01000000000',
                'email' => 'info@example.com',
                'social' => [
                    'facebook' => 'https://facebook.com/example',
                    'youtube' => '',
                    'instagram' => '',
                ],
                'liturgy_hours' => [
                    ['day' => 'Sunday', 'time_ar' => '٨ ص', 'time_en' => '8am'],
                ],
                'show_on_public_site' => [
                    'tagline' => '1',
                    'about' => '1',
                    'address' => '1',
                    'contact' => '1',
                    'social' => '1',
                    'liturgy_hours' => '1',
                ],
            ])
            ->assertRedirect(route('church.public-profile.edit'));

        $church = Church::main()->fresh();
        $profile = ChurchPublicProfile::fromSettings($church->settings);
        $this->assertSame('كنيسة الاختبار', $profile['tagline']['ar']);
        $this->assertSame('Test Church', $profile['tagline']['en']);
        $this->assertSame('123 Main St', $profile['address']);
        $this->assertSame('Sunday', $profile['liturgy_hours'][0]['day']);
        $this->assertSame('٨ ص', $profile['liturgy_hours'][0]['time']['ar']);

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'public_site.profile_updated',
            'user_id' => $admin->user_id,
        ]);
    }

    public function test_servant_cannot_edit_public_profile(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.public-profile.edit'))
            ->assertForbidden();
    }

    public function test_guest_can_view_public_profile_page(): void
    {
        $church = Church::main();
        $settings = is_array($church->settings) ? $church->settings : [];
        $settings[ChurchPublicProfile::SETTINGS_KEY] = ChurchPublicProfile::normalizeInput([
            'tagline' => ['ar' => 'شعار عام', 'en' => 'Public tagline'],
            'about' => ['ar' => 'نبذة عامة', 'en' => 'Public about'],
            'city' => 'Alexandria',
        ]);
        $church->settings = $settings;
        $church->save();

        $this->get(route('public.church.profile'))
            ->assertOk()
            ->assertSee('شعار عام')
            ->assertSee('Alexandria');
    }

    public function test_guest_page_404s_when_tenancy_on_and_capability_disabled(): void
    {
        config(['tenancy.enabled' => true]);

        $church = Church::main();
        ChurchCapability::query()
            ->where('church_id', $church->church_id)
            ->where('capability_key', 'public_site')
            ->update(['enabled' => false]);
        $church->unsetRelation('capabilities');

        TenantContext::set($church->fresh());

        $this->get(route('public.church.profile'))
            ->assertNotFound();
    }

    public function test_guest_page_does_not_leak_other_church_settings(): void
    {
        config(['tenancy.enabled' => true]);

        $main = Church::main();
        Church::create([
            'slug' => 'other-t10a',
            'name' => 'Other Church',
            'status' => 'active',
            'settings' => [
                ChurchPublicProfile::SETTINGS_KEY => ChurchPublicProfile::normalizeInput([
                    'tagline' => ['ar' => 'سرّ الكنيسة الأخرى', 'en' => 'Other secret'],
                ]),
            ],
        ]);

        $mainSettings = is_array($main->settings) ? $main->settings : [];
        $mainSettings[ChurchPublicProfile::SETTINGS_KEY] = ChurchPublicProfile::normalizeInput([
            'tagline' => ['ar' => 'ملف المستأجر الحالي', 'en' => 'Current tenant'],
        ]);
        $main->settings = $mainSettings;
        $main->save();

        TenantContext::set($main->fresh());

        $this->get(route('public.church.profile'))
            ->assertOk()
            ->assertSee('ملف المستأجر الحالي')
            ->assertDontSee('سرّ الكنيسة الأخرى');
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-t10a@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles[$templateSlug]->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }
}
