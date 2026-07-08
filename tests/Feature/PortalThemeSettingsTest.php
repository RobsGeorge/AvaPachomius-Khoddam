<?php

namespace Tests\Feature;

use App\Models\PortalSettings;
use App\Services\PortalThemeService;
use Tests\Support\EventModuleTestCase;

class PortalThemeSettingsTest extends EventModuleTestCase
{
    public function test_superadmin_can_access_theme_settings_page(): void
    {
        $superadmin = $this->createUser([
            'email' => 'theme-sa@example.com',
            'is_superadmin' => true,
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.theme.index'))
            ->assertOk()
            ->assertSee(__('pages.theme_colors_title', [], 'en'), false);
    }

    public function test_non_superadmin_cannot_access_theme_settings(): void
    {
        $user = $this->createUser(['email' => 'theme-user@example.com']);

        $this->actingAs($user)
            ->get(route('superadmin.theme.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_save_draft_and_publish_theme_colors(): void
    {
        $superadmin = $this->createUser([
            'email' => 'theme-publish@example.com',
            'is_superadmin' => true,
        ]);

        $palette = [
            'light' => [
                'primary' => '#112233',
                'primary_hover' => '#223344',
                'title' => '#334455',
                'link' => '#445566',
            ],
            'dark' => [
                'primary' => '#aabbcc',
                'primary_hover' => '#99aabb',
                'title' => '#ccddee',
                'link' => '#ddeeff',
            ],
        ];

        $this->actingAs($superadmin)
            ->post(route('superadmin.theme.draft'), $palette)
            ->assertRedirect(route('superadmin.theme.index'))
            ->assertSessionHas('success');

        $settings = PortalSettings::current();
        $this->assertSame('#112233', $settings->theme_colors_draft['light']['primary']);
        $this->assertNull($settings->theme_colors_published);

        $this->actingAs($superadmin)
            ->post(route('superadmin.theme.publish'), $palette)
            ->assertRedirect(route('superadmin.theme.index'))
            ->assertSessionHas('success');

        $settings->refresh();
        $this->assertSame('#112233', $settings->theme_colors_published['light']['primary']);
        $this->assertNotNull($settings->theme_colors_published_at);

        $css = app(PortalThemeService::class)->publishedCssBlock();
        $this->assertStringContainsString('--color-primary: #112233;', $css);
    }

    public function test_published_theme_css_is_injected_in_layout(): void
    {
        $superadmin = $this->createUser([
            'email' => 'theme-layout@example.com',
            'is_superadmin' => true,
        ]);

        $palette = [
            'light' => [
                'primary' => '#abcdef',
                'primary_hover' => '#fedcba',
                'title' => '#123456',
                'link' => '#654321',
            ],
            'dark' => [
                'primary' => '#111111',
                'primary_hover' => '#222222',
                'title' => '#333333',
                'link' => '#444444',
            ],
        ];

        $this->actingAs($superadmin)
            ->post(route('superadmin.theme.publish'), $palette);

        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="portal-theme-published"', false)
            ->assertSee(route('portal.theme.css', ['v' => PortalSettings::current()->theme_colors_published_at->timestamp]), false);

        $this->get(route('portal.theme.css'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
            ->assertSee('--color-primary: #abcdef;', false);
    }
}
