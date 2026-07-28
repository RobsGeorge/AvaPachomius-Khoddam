<?php

use App\Database\MigrationSupport;
use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T9e — service-level subscriptions (church floor + service add-ons).
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('billing_account', 'service_id', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->unique()->after('organization_id');
        });

        // Allow church + per-service billing accounts under the same organization.
        $this->dropUniqueIfExists('billing_account', 'billing_account_organization_id_unique');
        if (Schema::hasTable('billing_account') && ! $this->indexExists('billing_account', 'billing_account_organization_id_index')) {
            Schema::table('billing_account', function (Blueprint $table) {
                $table->index('organization_id');
            });
        }

        MigrationSupport::addColumn('subscription_plan', 'scope', function (Blueprint $table) {
            $table->string('scope', 20)->default('both')->after('status'); // church | service | both
        });

        SchemaGuards::createTableIfMissing('service_subscription', function (Blueprint $table) {
            $table->id('service_subscription_id');
            $table->unsignedBigInteger('service_id')->unique();
            $table->unsignedBigInteger('church_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('plan_price_id')->nullable();
            $table->unsignedBigInteger('billing_account_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('stripe_subscription_id', 120)->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->unsignedBigInteger('comped_by_user_id')->nullable();
            $table->string('comp_reason', 255)->nullable();
            $table->timestamps();
            $table->index('church_id');
            $table->index('plan_id');
            $table->index('billing_account_id');
            $table->index('status');
        });

        SchemaGuards::createTableIfMissing('service_entitlement_override', function (Blueprint $table) {
            $table->id('service_entitlement_override_id');
            $table->unsignedBigInteger('service_id');
            $table->string('feature_key', 60);
            $table->json('value');
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('granted_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'feature_key']);
            $table->index('service_id');
        });

        SchemaGuards::createTableIfMissing('service_entitlement_snapshot', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->primary();
            $table->json('features');
            $table->timestamp('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_entitlement_snapshot');
        Schema::dropIfExists('service_entitlement_override');
        Schema::dropIfExists('service_subscription');

        if (Schema::hasTable('subscription_plan') && Schema::hasColumn('subscription_plan', 'scope')) {
            Schema::table('subscription_plan', function (Blueprint $table) {
                $table->dropColumn('scope');
            });
        }

        if (Schema::hasTable('billing_account') && Schema::hasColumn('billing_account', 'service_id')) {
            Schema::table('billing_account', function (Blueprint $table) {
                $table->dropUnique(['service_id']);
                $table->dropColumn('service_id');
            });
        }
    }

    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropUnique($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();
        $row = $connection->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ((int) ($row->c ?? 0)) > 0;
    }
};
