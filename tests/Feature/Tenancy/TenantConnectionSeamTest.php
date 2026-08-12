<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Organization;
use App\Models\Residence;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * Diocese-tier residency seam — placement on organizations + repointable tenant
 * connection. Shared path must remain a true no-op (including Tenant Zero).
 * Canary model: Residence (after Family soft-deprecation).
 */
class TenantConnectionSeamTest extends EventModuleTestCase
{
    private ?string $isolatedSqlitePath = null;

    protected function tearDown(): void
    {
        TenantContext::clear();
        TenantDatabaseResolver::reset();

        if ($this->isolatedSqlitePath && is_file($this->isolatedSqlitePath)) {
            @unlink($this->isolatedSqlitePath);
            $this->isolatedSqlitePath = null;
        }

        parent::tearDown();
    }

    public function test_tenant_zero_stays_shared_placement(): void
    {
        $main = Organization::main();

        $this->assertSame(Organization::PLACEMENT_SHARED, $main->placement_policy);
        $this->assertFalse((bool) $main->db_isolated);
        $this->assertNull($main->db_name);
        $this->assertNull($main->db_user);
        $this->assertNull($main->db_password_encrypted);
    }

    public function test_shared_path_residence_writes_primary_and_church_scope_applies(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch(['slug' => 'seam-shared-b', 'name' => 'Seam Shared B']);

        TenantContext::set($churchA);
        $this->assertFalse(TenantDatabaseResolver::usesIsolatedConnection());
        $this->assertSame(config('database.default'), (new Residence)->getConnectionName());

        $residenceA = Residence::create(['address' => 'Shared Residence A']);
        $this->assertSame($churchA->church_id, (int) $residenceA->church_id);
        $this->assertTrue(
            DB::connection(config('database.default'))
                ->table('residences')
                ->where('residence_id', $residenceA->residence_id)
                ->exists()
        );

        TenantContext::set($churchB);
        $residenceB = Residence::create(['address' => 'Shared Residence B']);
        $this->assertSame($churchB->church_id, (int) $residenceB->church_id);
        $this->assertNull(Residence::find($residenceA->residence_id));
        $this->assertNotNull(Residence::find($residenceB->residence_id));

        TenantContext::set($churchA);
        $this->assertNotNull(Residence::find($residenceA->residence_id));
        $this->assertNull(Residence::find($residenceB->residence_id));
    }

    public function test_top_level_org_without_parent_defaults_to_shared(): void
    {
        $church = $this->createChurch(['slug' => 'seam-orphan', 'name' => 'Seam Orphan']);
        $org = Organization::query()->findOrFail($church->organization_id);
        $this->assertNull($org->parent_id);

        TenantContext::set($church);
        $placement = TenantDatabaseResolver::resolvePlacementOrganization($church);

        $this->assertNotNull($placement);
        $this->assertSame((int) $org->organization_id, (int) $placement->organization_id);
        $this->assertFalse(TenantDatabaseResolver::usesIsolatedConnection());

        Residence::create(['address' => 'Orphan Residence']);
        $this->assertFalse(TenantDatabaseResolver::usesIsolatedConnection());
    }

    public function test_isolated_diocese_writes_tenant_schema_not_primary(): void
    {
        [$church, $diocese] = $this->seedIsolatedDioceseChurch();

        $primaryBefore = DB::connection(config('database.default'))->table('residences')->count();

        TenantContext::set($church);
        $this->assertTrue(TenantDatabaseResolver::usesIsolatedConnection());
        $this->assertSame('tenant', (new Residence)->getConnectionName());

        $residence = Residence::create(['address' => 'Isolated Residence']);
        $this->assertSame($church->church_id, (int) $residence->church_id);

        $this->assertSame(
            $primaryBefore,
            DB::connection(config('database.default'))->table('residences')->count(),
            'Primary schema must stay untouched under isolated placement'
        );
        $this->assertTrue(
            DB::connection('tenant')->table('residences')->where('residence_id', $residence->residence_id)->exists()
        );
        $this->assertSame(
            'Isolated Residence',
            DB::connection('tenant')->table('residences')->where('residence_id', $residence->residence_id)->value('address')
        );

        // Bind Tenant Zero again — must purge + leave isolated connection.
        TenantContext::set(Church::main());
        $this->assertFalse(TenantDatabaseResolver::usesIsolatedConnection());
        $this->assertSame(config('database.default'), (new Residence)->getConnectionName());
        $this->assertNull(Residence::withoutTenancy()->find($residence->residence_id));
    }

    public function test_db_password_encrypted_at_rest_and_hidden(): void
    {
        $org = Organization::main();
        $plain = 'diocese-db-secret-never-log';

        $org->forceFill(['db_password_encrypted' => $plain])->save();

        $raw = DB::table('organizations')
            ->where('organization_id', $org->organization_id)
            ->value('db_password_encrypted');

        $this->assertNotNull($raw);
        $this->assertNotSame($plain, $raw);
        $this->assertSame($plain, $org->fresh()->db_password_encrypted);
        $this->assertArrayNotHasKey('db_password_encrypted', $org->fresh()->toArray());

        // Restore Tenant Zero — do not leave credentials on org 1.
        $org->forceFill([
            'db_password_encrypted' => null,
            'db_isolated' => false,
            'db_name' => null,
            'db_user' => null,
            'placement_policy' => Organization::PLACEMENT_SHARED,
        ])->save();
    }

    /**
     * @return array{0: Church, 1: Organization}
     */
    private function seedIsolatedDioceseChurch(): array
    {
        $this->isolatedSqlitePath = database_path('testing_tenant_isolated_'.uniqid('', true).'.sqlite');
        if (is_file($this->isolatedSqlitePath)) {
            unlink($this->isolatedSqlitePath);
        }
        touch($this->isolatedSqlitePath);

        // Create the church first so its numerically aligned organizations row is
        // claimed before the diocese row (ensureOrganizationLinked requires
        // organization_id === church_id when that id already exists).
        $church = $this->createChurch([
            'slug' => 'seam-isolated-'.uniqid(),
            'name' => 'Seam Isolated Church',
        ]);

        $diocese = Organization::create([
            'parent_id' => null,
            'type' => Organization::TYPE_DIOCESE,
            'subdomain' => 'seam-diocese-'.uniqid(),
            'name' => 'Seam Diocese',
            'status' => 'active',
            'placement_policy' => Organization::PLACEMENT_DIOCESE_DB,
            'db_isolated' => true,
            'db_name' => $this->isolatedSqlitePath,
            'db_user' => null,
            'db_password_encrypted' => 'isolated-test-password',
        ]);

        $churchOrg = Organization::query()->findOrFail($church->organization_id);
        $churchOrg->forceFill([
            'parent_id' => $diocese->organization_id,
            'type' => Organization::TYPE_CHURCH,
            'placement_policy' => Organization::PLACEMENT_SHARED,
            'db_isolated' => false,
        ])->save();

        // Point tenant at the isolated sqlite and create the canary table only there.
        TenantDatabaseResolver::bindIsolated($diocese->fresh());
        Schema::connection('tenant')->create('residences', function (Blueprint $table) {
            $table->id('residence_id');
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('address', 500);
            $table->string('geo', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        // Leave isolated flag set so the next TenantContext::set re-binds cleanly;
        // reset then re-bind via set() in the test body.
        TenantDatabaseResolver::bindShared();

        return [$church->fresh(), $diocese->fresh()];
    }
}
