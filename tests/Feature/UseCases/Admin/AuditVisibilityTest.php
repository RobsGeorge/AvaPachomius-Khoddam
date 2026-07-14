<?php

namespace Tests\Feature\UseCases\Admin;

use App\Models\ActivityLog;
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
}
