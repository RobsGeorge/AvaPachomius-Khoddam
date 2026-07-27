<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\EventTheme;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * Event mode ("Liturgical" sub-theme) — activation + admin gating.
 */
class EventThemeModeTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_layout_gets_liturgical_class_when_manual_override_on(): void
    {
        $this->setEventTheme(['enabled_manual' => true, 'periods' => []]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('event-liturgical');
    }

    public function test_layout_has_no_liturgical_class_when_off(): void
    {
        $this->setEventTheme(['enabled_manual' => false, 'periods' => []]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('event-liturgical');
    }

    public function test_scheduled_period_covering_today_activates_class(): void
    {
        $today = now()->toDateString();
        $this->setEventTheme([
            'enabled_manual' => false,
            'periods' => [['start' => $today, 'end' => $today, 'label' => 'Feast']],
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('event-liturgical');
    }

    public function test_church_admin_can_enable_and_it_is_audited(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->put(route('church.event-theme.update'), [
                'enabled_manual' => '1',
                'periods' => [
                    ['start' => '2026-01-06', 'end' => '2026-01-08', 'label' => 'Nativity'],
                ],
            ])
            ->assertRedirect(route('church.event-theme.edit'));

        $config = EventTheme::fromSettings(Church::main()->fresh()->settings);
        $this->assertTrue($config['enabled_manual']);
        $this->assertCount(1, $config['periods']);
        $this->assertSame('Nativity', $config['periods'][0]['label']);

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'event_theme.updated',
            'user_id' => $admin->user_id,
        ]);
    }

    public function test_servant_cannot_access_event_mode_settings(): void
    {
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($servant)
            ->get(route('church.event-theme.edit'))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $config */
    private function setEventTheme(array $config): void
    {
        $church = Church::main();
        $settings = is_array($church->settings) ? $church->settings : [];
        $settings[EventTheme::SETTINGS_KEY] = $config;
        $church->settings = $settings;
        $church->save();

        TenantContext::set($church->fresh());
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-eventtheme@example.com']);

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
