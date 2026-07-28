<?php

namespace App\Services\Tenancy;

use App\Models\Church;
use App\Models\ChurchService;
use App\Models\Organization;
use App\Models\StructureTemplate;
use App\Services\ChurchProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Automated checks for docs/staging-acceptance-checklist.md (T7 + T8 + T10c).
 */
class StagingAcceptanceChecker
{
    public const STATUS_PASS = 'pass';

    public const STATUS_WARN = 'warn';

    public const STATUS_FAIL = 'fail';

    public function __construct(
        private ChurchProvisioningService $provisioning,
    ) {}

    /**
     * @return list<array{name: string, status: string, message: string}>
     */
    public function runT7(bool $expectMultiTenant = false, string $pilotSlug = 'pilot-service', bool $repairOrgs = false): array
    {
        $results = [];

        if (! Schema::hasTable('church')) {
            return [$this->fail('church_table', 'Table `church` is missing — run migrations.')];
        }

        $main = Church::query()->where('slug', config('tenancy.main_slug', 'avapakhomios'))->first();
        if (! $main) {
            $results[] = $this->fail('main_church', 'Tenant Zero church not found (slug: '.config('tenancy.main_slug').').');
        } else {
            $results[] = $this->pass('main_church', "Tenant Zero church #{$main->church_id} ({$main->slug}).");
        }

        $enabled = (bool) config('tenancy.enabled');
        if ($expectMultiTenant && ! $enabled) {
            $results[] = $this->fail('multi_tenant', 'MULTI_TENANT=false but --expect-multi-tenant was set.');
        } elseif ($enabled) {
            $results[] = $this->pass('multi_tenant', 'MULTI_TENANT=true.');
            if (! config('tenancy.base_domain')) {
                $results[] = $this->warn('tenancy_base_domain', 'TENANCY_BASE_DOMAIN is empty.');
            } else {
                $results[] = $this->pass('tenancy_base_domain', 'TENANCY_BASE_DOMAIN='.config('tenancy.base_domain'));
            }
        } else {
            $results[] = $this->warn('multi_tenant', 'MULTI_TENANT=false (OK for pre-cutover; enable for staging pilot).');
        }

        foreach (Church::query()->get() as $church) {
            if ($repairOrgs) {
                $linked = $this->provisioning->ensureOrganizationLinked($church);
                if ($linked) {
                    $results[] = $this->pass('org_link', "Church {$church->slug}: organizations row linked (repaired if needed).");
                } else {
                    $results[] = $this->fail('org_link', "Church {$church->slug}: could not link organizations row.");
                }
            } elseif ($this->churchHasOrganizationLink($church)) {
                $results[] = $this->pass('org_link', "Church {$church->slug}: organizations link OK.");
            } else {
                $results[] = $this->fail('org_link', "Church {$church->slug}: missing organizations link — re-run with --repair-orgs.");
            }
        }

        $nullableTables = array_flip((array) config('tenancy.tenant_tables_nullable_church_id', ['roles']));

        foreach ((array) config('tenancy.tenant_tables', []) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'church_id')) {
                continue;
            }

            $nullQuery = DB::table($table)->whereNull('church_id');
            if ($table === 'roles' && Schema::hasColumn('roles', 'is_template')) {
                $nullQuery->where('is_template', false);
            }

            $hasNulls = $nullQuery->exists();
            if ($hasNulls && ! isset($nullableTables[$table])) {
                $results[] = $this->fail("null_church_id_{$table}", "NULL church_id row(s) in `{$table}`.");
            } elseif ($hasNulls) {
                $results[] = $this->warn("null_church_id_{$table}", "Nullable-template NULL church_id in `{$table}` (expected for templates).");
            } else {
                $results[] = $this->pass("null_church_id_{$table}", "No unexpected NULL church_id in `{$table}`.");
            }
        }

        if (Church::where('slug', $pilotSlug)->exists()) {
            $results[] = $this->pass('pilot_church', "Pilot church slug [{$pilotSlug}] exists.");
        } else {
            $results[] = $this->warn('pilot_church', "Pilot church [{$pilotSlug}] not found — run tenancy:seed-pilot-church.");
        }

        return $results;
    }

    /**
     * @return list<array{name: string, status: string, message: string}>
     */
    public function runT8(): array
    {
        $results = [];

        if (! Schema::hasTable('structure_templates')) {
            return [$this->fail('structure_templates', 'Table `structure_templates` missing — run T8a migrations.')];
        }

        foreach ([
            StructureTemplate::KEY_EDUCATIONAL_STANDARD,
            StructureTemplate::KEY_MEETING_FLAT,
            StructureTemplate::KEY_CARE_SECTOR,
        ] as $key) {
            if (StructureTemplate::byKey($key)) {
                $results[] = $this->pass("template_{$key}", "Template [{$key}] seeded.");
            } else {
                $results[] = $this->fail("template_{$key}", "Missing structure template [{$key}].");
            }
        }

        if (! Schema::hasTable('service')) {
            $results[] = $this->fail('service_table', 'Table `service` missing.');

            return $results;
        }

        foreach (['slug', 'structure_template_id'] as $col) {
            if (Schema::hasColumn('service', $col)) {
                $results[] = $this->pass("service_column_{$col}", "service.{$col} present.");
            } else {
                $results[] = $this->fail("service_column_{$col}", "service.{$col} missing.");
            }
        }

        $default = ChurchService::defaultService();
        if (! $default) {
            $results[] = $this->fail('default_service', 'No default service row.');
        } elseif (($default->slug ?? '') === 'servants-prep') {
            $results[] = $this->pass('servants_prep_slug', 'Default service slug is servants-prep.');
            $edu = StructureTemplate::byKey(StructureTemplate::KEY_EDUCATIONAL_STANDARD);
            if ($edu && (int) $default->structure_template_id === (int) $edu->structure_template_id) {
                $results[] = $this->pass('servants_prep_template', 'Default service uses educational_standard.');
            } else {
                $results[] = $this->warn('servants_prep_template', 'Default service not bound to educational_standard.');
            }
        } else {
            $results[] = $this->warn('servants_prep_slug', 'Default service slug is '.($default->slug ?: '(empty)').', expected servants-prep on Tenant Zero.');
        }

        if (Schema::hasTable('service_units')) {
            $unitCount = (int) DB::table('service_units')->count();
            if ($unitCount > 0) {
                $results[] = $this->pass('service_units', "{$unitCount} service_units row(s).");
            } else {
                $results[] = $this->warn('service_units', 'service_units empty — OK if no courses on default service yet.');
            }
        } else {
            $results[] = $this->fail('service_units', 'Table `service_units` missing.');
        }

        if (Schema::hasTable('enrollments')) {
            $enrollmentCount = (int) DB::table('enrollments')->count();
            $ucrCount = Schema::hasTable('user_course_role')
                ? (int) DB::table('user_course_role')->count()
                : 0;
            if ($ucrCount === 0 || $enrollmentCount >= $ucrCount) {
                $results[] = $this->pass('enrollments', "enrollments={$enrollmentCount}, user_course_role={$ucrCount}.");
            } else {
                $results[] = $this->fail('enrollments', "enrollments ({$enrollmentCount}) < user_course_role ({$ucrCount}) — re-run migrate or assign to dual-write.");
            }
        } else {
            $results[] = $this->fail('enrollments', 'Table `enrollments` missing — run T8b migrations.');
        }

        if (Schema::hasTable('attendance') && Schema::hasColumn('attendance', 'lock_version')) {
            $results[] = $this->pass('attendance_lock', 'attendance.lock_version column present.');
        } elseif (Schema::hasTable('attendance')) {
            $results[] = $this->fail('attendance_lock', 'attendance.lock_version missing — run T8b migrations.');
        }

        return $results;
    }

    /**
     * @return list<array{name: string, status: string, message: string}>
     */
    public function runT10c(): array
    {
        $results = [];

        foreach (['church_site', 'church_site_section', 'church_media'] as $table) {
            if (Schema::hasTable($table)) {
                $results[] = $this->pass("t10c_table_{$table}", "Table `{$table}` present.");
            } else {
                $results[] = $this->fail("t10c_table_{$table}", "Table `{$table}` missing — run T10c migrations.");
            }
        }

        if (Schema::hasTable('church_site')) {
            foreach (['church_id', 'theme_draft', 'theme_published', 'published_at'] as $col) {
                if (Schema::hasColumn('church_site', $col)) {
                    $results[] = $this->pass("church_site_column_{$col}", "church_site.{$col} present.");
                } else {
                    $results[] = $this->fail("church_site_column_{$col}", "church_site.{$col} missing.");
                }
            }
        }

        if (Schema::hasTable('church_site_section')) {
            foreach (['church_id', 'church_site_id', 'type', 'content_draft', 'content_published'] as $col) {
                if (Schema::hasColumn('church_site_section', $col)) {
                    $results[] = $this->pass("church_site_section_column_{$col}", "church_site_section.{$col} present.");
                } else {
                    $results[] = $this->fail("church_site_section_column_{$col}", "church_site_section.{$col} missing.");
                }
            }
        }

        $capabilityKeys = array_keys((array) config('capabilities', []));
        if (in_array('public_site', $capabilityKeys, true)) {
            $results[] = $this->pass('t10c_capability', 'public_site capability defined in config/capabilities.php.');
        } else {
            $results[] = $this->fail('t10c_capability', 'public_site capability missing from config/capabilities.php.');
        }

        $permissionCatalog = (array) config('permissions.public_site.permissions', []);
        foreach (['public_site.profile', 'public_site.theme', 'public_site.manage', 'public_site.publish'] as $key) {
            if (array_key_exists($key, $permissionCatalog)) {
                $results[] = $this->pass("t10c_permission_{$key}", "Permission [{$key}] in catalog.");
            } else {
                $results[] = $this->fail("t10c_permission_{$key}", "Permission [{$key}] missing from config/permissions.php.");
            }

            if (Schema::hasTable('permissions')) {
                if (DB::table('permissions')->where('key', $key)->exists()) {
                    $results[] = $this->pass("t10c_permission_synced_{$key}", "Permission [{$key}] synced to DB.");
                } else {
                    $results[] = $this->warn("t10c_permission_synced_{$key}", "Permission [{$key}] not in DB — run permissions:sync.");
                }
            }
        }

        foreach (['site.homepage.edit', 'site.homepage.publish', 'site.homepage.unpublish'] as $routeName) {
            if (Route::has($routeName)) {
                $results[] = $this->pass("t10c_route_{$routeName}", "Route [{$routeName}] registered.");
            } else {
                $results[] = $this->fail("t10c_route_{$routeName}", "Route [{$routeName}] missing — T10c routes not loaded.");
            }
        }

        if (class_exists(\App\Http\Controllers\Church\HomepageEditorController::class)) {
            $results[] = $this->pass('t10c_editor_controller', 'HomepageEditorController present.');
        } else {
            $results[] = $this->fail('t10c_editor_controller', 'HomepageEditorController missing.');
        }

        if (class_exists(\App\Services\PublicSite\ChurchSiteService::class)) {
            $results[] = $this->pass('t10c_site_service', 'ChurchSiteService present.');
        } else {
            $results[] = $this->fail('t10c_site_service', 'ChurchSiteService missing.');
        }

        return $results;
    }

    /** @param  list<array{name: string, status: string, message: string}>  $results */
    public function hasFailures(array $results): bool
    {
        foreach ($results as $row) {
            if ($row['status'] === self::STATUS_FAIL) {
                return true;
            }
        }

        return false;
    }

    private function churchHasOrganizationLink(Church $church): bool
    {
        if (! Schema::hasTable('organizations')) {
            return true;
        }

        if ($church->organization_id && Organization::query()->where('organization_id', $church->organization_id)->exists()) {
            return true;
        }

        return Organization::query()->where('organization_id', $church->church_id)->exists()
            || Organization::query()->where('subdomain', $church->slug)->exists();
    }

    /** @return array{name: string, status: string, message: string} */
    private function pass(string $name, string $message): array
    {
        return ['name' => $name, 'status' => self::STATUS_PASS, 'message' => $message];
    }

    /** @return array{name: string, status: string, message: string} */
    private function warn(string $name, string $message): array
    {
        return ['name' => $name, 'status' => self::STATUS_WARN, 'message' => $message];
    }

    /** @return array{name: string, status: string, message: string} */
    private function fail(string $name, string $message): array
    {
        return ['name' => $name, 'status' => self::STATUS_FAIL, 'message' => $message];
    }
}
