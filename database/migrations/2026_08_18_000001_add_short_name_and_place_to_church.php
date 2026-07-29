<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: preferred short_name (nav) + international place fields + place_key uniqueness.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church')) {
            return;
        }

        Schema::table('church', function (Blueprint $table) {
            if (! Schema::hasColumn('church', 'short_name')) {
                $table->string('short_name', 40)->nullable()->after('name');
            }
            if (! Schema::hasColumn('church', 'place_street')) {
                $table->string('place_street', 191)->nullable()->after('domain');
            }
            if (! Schema::hasColumn('church', 'place_district')) {
                $table->string('place_district', 120)->nullable()->after('place_street');
            }
            if (! Schema::hasColumn('church', 'place_region')) {
                $table->string('place_region', 120)->nullable()->after('place_district');
            }
            if (! Schema::hasColumn('church', 'place_governorate')) {
                $table->string('place_governorate', 120)->nullable()->after('place_region');
            }
            if (! Schema::hasColumn('church', 'place_country_code')) {
                $table->char('place_country_code', 2)->nullable()->after('place_governorate');
            }
            if (! Schema::hasColumn('church', 'place_key')) {
                $table->string('place_key', 191)->nullable()->after('place_country_code');
            }
        });

        if (Schema::hasColumn('church', 'place_key')
            && ! $this->indexExists('church', 'church_place_key_unique')) {
            Schema::table('church', function (Blueprint $table) {
                $table->unique('place_key', 'church_place_key_unique');
            });
        }

        // Backfill short_name from truncated name for existing rows.
        if (Schema::hasColumn('church', 'short_name')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('UPDATE church SET short_name = LEFT(name, 40) WHERE short_name IS NULL OR short_name = \'\'');
            } else {
                foreach (DB::table('church')->whereNull('short_name')->orWhere('short_name', '')->cursor() as $row) {
                    $short = mb_substr((string) $row->name, 0, 40);
                    DB::table('church')->where('church_id', $row->church_id)->update(['short_name' => $short]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('church')) {
            return;
        }

        if ($this->indexExists('church', 'church_place_key_unique')) {
            Schema::table('church', function (Blueprint $table) {
                $table->dropUnique('church_place_key_unique');
            });
        }

        Schema::table('church', function (Blueprint $table) {
            foreach ([
                'short_name',
                'place_street',
                'place_district',
                'place_region',
                'place_governorate',
                'place_country_code',
                'place_key',
            ] as $column) {
                if (Schema::hasColumn('church', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

            return count($rows) > 0;
        }

        return false;
    }
};
