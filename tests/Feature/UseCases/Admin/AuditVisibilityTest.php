<?php

namespace Tests\Feature\UseCases\Admin;

use App\Models\ActivityLog;
use App\Models\LoginTrial;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * F-09 audit-log visibility + F-07 print (TC-SA-*, TC-PRINT-*). Covers the CSV
 * export of the (filtered) activity log and the presence of print affordances.
 */
class AuditVisibilityTest extends EventModuleTestCase
{
    public function test_superadmin_can_export_activity_log_as_csv(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-super@example.com']);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'GET', 'route_name' => 'dashboard',
            'url' => 'https://portal.test/dashboard', 'ip_address' => '127.0.0.1',
            'device_summary' => 'Windows / Chrome', 'response_status' => 200, 'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('superadmin.audit.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('route_name', $csv);       // header row
        $this->assertStringContainsString('dashboard', $csv);        // data row
    }

    public function test_date_filter_scopes_the_export(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-date@example.com']);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'GET', 'route_name' => 'old-route',
            'url' => 'u', 'ip_address' => '127.0.0.1', 'device_summary' => 'd', 'response_status' => 200,
            'created_at' => now()->subDays(30),
        ]);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'GET', 'route_name' => 'recent-route',
            'url' => 'u', 'ip_address' => '127.0.0.1', 'device_summary' => 'd', 'response_status' => 200,
            'created_at' => now(),
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('superadmin.audit.export', ['from' => now()->subDays(2)->toDateString()]))
            ->streamedContent();

        $this->assertStringContainsString('recent-route', $csv);
        $this->assertStringNotContainsString('old-route', $csv);
    }

    public function test_group_filter_scopes_activity_and_export(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-group@example.com']);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'SYSTEM', 'route_name' => 'events.action.created',
            'url' => 'events.action.created', 'ip_address' => '127.0.0.1', 'device_summary' => 'd',
            'response_status' => 200, 'created_at' => now(),
        ]);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'SYSTEM', 'route_name' => 'church.created',
            'url' => 'church.created', 'ip_address' => '127.0.0.1', 'device_summary' => 'd',
            'response_status' => 200, 'created_at' => now(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('superadmin.audit.index', ['tab' => 'activity', 'group' => 'events']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('events.action.created', (string) $html);
        $this->assertStringNotContainsString('church.created', (string) $html);

        $csv = $this->actingAs($admin)
            ->get(route('superadmin.audit.export', ['group' => 'events']))
            ->streamedContent();

        $this->assertStringContainsString('events.action.created', $csv);
        $this->assertStringNotContainsString('church.created', $csv);
    }

    public function test_auth_rollups_and_group_chips_render(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-rollup@example.com']);

        LoginTrial::create([
            'user_id' => $admin->user_id,
            'email' => $admin->email,
            'password_attempt' => '',
            'context' => 'login',
            'success' => true,
            'created_at' => now(),
        ]);
        LoginTrial::create([
            'user_id' => $admin->user_id,
            'email' => $admin->email,
            'password_attempt' => '',
            'context' => 'login',
            'success' => false,
            'failure_reason' => 'invalid',
            'created_at' => now(),
        ]);
        ActivityLog::create([
            'user_id' => $admin->user_id,
            'http_method' => 'SYSTEM',
            'route_name' => 'auth.password_changed',
            'url' => 'auth.password_changed',
            'request_input' => ['event' => 'auth.password_changed', 'source' => 'account'],
            'response_status' => 200,
            'created_at' => now(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('superadmin.audit.index', ['tab' => 'activity']))
            ->assertOk()
            ->assertSee(__('pages.audit_group_auth'))
            ->assertSee(__('pages.audit_rollup_logins_ok'))
            ->getContent();

        $this->assertStringContainsString((string) __('pages.audit_rollup_logins_ok'), (string) $html);
        $this->assertStringContainsString('auth.password_changed', (string) $html);
        $this->assertStringNotContainsString('secret-password', (string) $html);
        $this->assertMatchesRegularExpression('/>\s*1\s*</', (string) $html);
    }

    public function test_legacy_module_events_still_filters(): void
    {
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-legacy@example.com']);
        ActivityLog::create([
            'user_id' => $admin->user_id, 'http_method' => 'SYSTEM', 'route_name' => 'events.action.x',
            'url' => 'u', 'response_status' => 200, 'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('superadmin.audit.index', ['tab' => 'activity', 'module' => 'events']))
            ->assertOk()
            ->assertSee('events.action.x');
    }

    public function test_non_superadmin_cannot_export_audit_log(): void
    {
        $user = $this->createUser(['email' => 'audit-plain@example.com']);

        $this->actingAs($user)->get(route('superadmin.audit.export'))->assertForbidden();
    }

    public function test_final_grades_page_offers_a_print_button(): void
    {
        // A rendered layout always links print.css; assert the print affordance ships.
        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'print-super@example.com']);

        $html = $this->actingAs($admin)->get(route('my-learning.index'))->getContent();

        $this->assertStringContainsString('css/print.css', (string) $html);
        $this->assertStringContainsString('window.print()', (string) $html);
    }

    public function test_audit_prune_deletes_aged_rows_and_keeps_recent(): void
    {
        config([
            'audit.retention.activity_logs_days' => 7,
            'audit.retention.login_trials_days' => 7,
        ]);

        $admin = $this->createUser(['is_superadmin' => true, 'email' => 'audit-prune@example.com']);

        ActivityLog::withoutTenancy()->create([
            'user_id' => $admin->user_id, 'http_method' => 'GET', 'route_name' => 'old-activity',
            'url' => 'u', 'response_status' => 200, 'created_at' => now()->subDays(30),
        ]);
        ActivityLog::withoutTenancy()->create([
            'user_id' => $admin->user_id, 'http_method' => 'GET', 'route_name' => 'new-activity',
            'url' => 'u', 'response_status' => 200, 'created_at' => now(),
        ]);
        LoginTrial::create([
            'user_id' => $admin->user_id, 'email' => $admin->email, 'password_attempt' => '',
            'context' => 'login', 'success' => true, 'created_at' => now()->subDays(30),
        ]);
        LoginTrial::create([
            'user_id' => $admin->user_id, 'email' => $admin->email, 'password_attempt' => '',
            'context' => 'login', 'success' => true, 'created_at' => now(),
        ]);

        Artisan::call('audit:prune');

        $this->assertFalse(ActivityLog::withoutTenancy()->where('route_name', 'old-activity')->exists());
        $this->assertTrue(ActivityLog::withoutTenancy()->where('route_name', 'new-activity')->exists());
        $this->assertSame(1, LoginTrial::query()->count());
    }
}
