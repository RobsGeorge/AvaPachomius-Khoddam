<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 — seed Tenant Zero in `organizations` (id=1) and align the legacy `church` row.
 * Idempotent: safe on production replay and on migrate:fresh after T0 church seed.
 */
return new class extends Migration
{
    private const MAIN_ORG_ID = 1;

    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        $subdomain = 'avapakhomios';
        $name = 'كنيسة الأنبا باخوميوس';

        $existingId = DB::table('organizations')
            ->where('subdomain', $subdomain)
            ->orWhere('organization_id', self::MAIN_ORG_ID)
            ->value('organization_id');

        if (! $existingId) {
            DB::table('organizations')->insert([
                'organization_id' => self::MAIN_ORG_ID,
                'parent_id' => null,
                'type' => 'church',
                'subdomain' => $subdomain,
                'name' => $name,
                'region' => null,
                'theme' => null,
                'settings' => json_encode(['locale' => 'ar', 'timezone' => 'Africa/Cairo']),
                'onboarding_state' => json_encode(['phase' => 'tenant_zero', 'completed' => true]),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mainOrgId = self::MAIN_ORG_ID;
        } else {
            DB::table('organizations')->where('organization_id', $existingId)->update([
                'type' => 'church',
                'subdomain' => $subdomain,
                'name' => $name,
                'status' => 'active',
                'updated_at' => now(),
            ]);
            $mainOrgId = (int) $existingId;
        }

        if (Schema::hasTable('church')) {
            MigrationSupport::addColumn('church', 'organization_id', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->index();
            });

            $churchRow = DB::table('church')->where('church_id', $mainOrgId)->first()
                ?? DB::table('church')->where('slug', config('tenancy.main_slug', 'avapakhomios'))->first()
                ?? DB::table('church')->orderBy('church_id')->first();

            if ($churchRow) {
                DB::table('church')->where('church_id', $churchRow->church_id)->update([
                    'slug' => $subdomain,
                    'name' => $name,
                    'organization_id' => $mainOrgId,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('church')->insertOrIgnore([
                    'church_id' => $mainOrgId,
                    'organization_id' => $mainOrgId,
                    'slug' => $subdomain,
                    'name' => $name,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Expand-only: leave stamped data.
    }
};
