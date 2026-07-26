<?php

namespace Tests\Feature\PublicSite;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\PublicSite\ChurchBranding;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

/**
 * T10b — church branding (settings.branding) + portal/public CSS wiring.
 */
class ChurchBrandingTest extends EventModuleTestCase
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
        Storage::fake('public');
    }

    public function test_church_admin_can_save_palette_and_see_public_css_vars(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->put(route('church.branding.update'), [
                'palette' => 'olive',
                'font_display' => 'cairo',
                'font_body' => 'amiri',
                'apply_to_portal' => '1',
            ])
            ->assertRedirect(route('church.branding.edit'));

        $church = Church::main()->fresh();
        $branding = ChurchBranding::fromSettings($church->settings);
        $this->assertSame('olive', $branding['palette']);
        $this->assertSame(ChurchBranding::PALETTES['olive']['primary'], $branding['primary']);
        $this->assertSame('amiri', $branding['font_body']);

        $this->get(route('public.church.profile'))
            ->assertOk()
            ->assertSee('--ps-primary:', false)
            ->assertSee(ChurchBranding::PALETTES['olive']['primary'], false)
            ->assertSee('public-site', false);

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'public_site.branding_updated',
            'user_id' => $admin->user_id,
        ]);
    }

    public function test_custom_palette_rejects_low_contrast(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->from(route('church.branding.edit'))
            ->put(route('church.branding.update'), [
                'palette' => 'custom',
                'primary' => '#fffffe',
                'accent' => '#c9a227',
                'primary_text' => '#ffffff',
                'font_display' => 'cairo',
                'font_body' => 'cairo',
                'apply_to_portal' => '1',
            ])
            ->assertRedirect(route('church.branding.edit'))
            ->assertSessionHasErrors('primary_text');
    }

    public function test_logo_upload_and_portal_css_injection(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');
        $file = UploadedFile::fake()->image('logo.png', 120, 80);

        $this->actingAs($admin)
            ->put(route('church.branding.update'), [
                'palette' => 'deaconia',
                'font_display' => 'cairo',
                'font_body' => 'cairo',
                'apply_to_portal' => '1',
                'logo' => $file,
            ])
            ->assertRedirect(route('church.branding.edit'));

        $branding = ChurchBranding::fromSettings(Church::main()->fresh()->settings);
        $this->assertNotEmpty($branding['logo_path']);
        Storage::disk('public')->assertExists($branding['logo_path']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-primary: #114b4f;', false)
            ->assertSee('storage/'.$branding['logo_path'], false);
    }

    public function test_servant_cannot_edit_branding(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.branding.edit'))
            ->assertForbidden();
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-t10b@example.com']);

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
