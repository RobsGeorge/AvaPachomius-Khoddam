<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand: a survey may be course-wide (module_id NULL) instead of bound to one module.
 * Production MySQL still has NOT NULL from the original create; tests/SQLite get
 * nullable from a rebuild when needed. Never drop/rename the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_surveys') || ! Schema::hasColumn('feedback_surveys', 'module_id')) {
            return;
        }

        if ($this->moduleIdIsNullable()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `feedback_surveys` MODIFY `module_id` BIGINT UNSIGNED NULL');

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteWithNullableModuleId();
        }
    }

    public function down(): void
    {
        // Additive expand — keep module_id nullable.
    }

    private function moduleIdIsNullable(): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $connection = Schema::getConnection();

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $row = $connection->selectOne(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                [$database, 'feedback_surveys', 'module_id']
            );
            $nullable = is_object($row) ? ($row->IS_NULLABLE ?? null) : ($row['IS_NULLABLE'] ?? null);

            return strtoupper((string) $nullable) === 'YES';
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA table_info('feedback_surveys')");
            foreach ($rows as $row) {
                $name = is_object($row) ? ($row->name ?? null) : ($row['name'] ?? null);
                if ($name === 'module_id') {
                    $notnull = is_object($row) ? (int) ($row->notnull ?? 1) : (int) ($row['notnull'] ?? 1);

                    return $notnull === 0;
                }
            }
        }

        return false;
    }

    /**
     * SQLite cannot MODIFY nullability — rebuild (tests only; MySQL uses ALTER).
     */
    private function rebuildSqliteWithNullableModuleId(): void
    {
        $columns = Schema::getColumnListing('feedback_surveys');
        if ($columns === []) {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('feedback_surveys', 'feedback_surveys__module_id_tmp');

        Schema::create('feedback_surveys', function (Blueprint $table) use ($columns) {
            $table->id('survey_id');
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('module_id')->nullable()->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'module_id', 'status']);

            if (in_array('is_anonymous', $columns, true)) {
                $table->boolean('is_anonymous')->default(true);
            }
            if (in_array('blocks_exam_id', $columns, true)) {
                $table->unsignedBigInteger('blocks_exam_id')->nullable()->index();
            }
            if (in_array('blocks_project_assessment_id', $columns, true)) {
                $table->unsignedBigInteger('blocks_project_assessment_id')->nullable()->index();
            }
            if (in_array('church_id', $columns, true)) {
                $table->unsignedBigInteger('church_id')->nullable()->index();
            }
        });

        $selectCols = [
            'survey_id',
            'course_id',
            'module_id',
            'title',
            'description',
            'created_by_user_id',
            'status',
            'is_mandatory',
            'due_at',
            'opened_at',
            'closed_at',
            'created_at',
            'updated_at',
        ];
        foreach ([
            'is_anonymous',
            'blocks_exam_id',
            'blocks_project_assessment_id',
            'church_id',
        ] as $optional) {
            if (in_array($optional, $columns, true)) {
                $selectCols[] = $optional;
            }
        }

        $tmpCols = Schema::getColumnListing('feedback_surveys__module_id_tmp');
        $available = array_values(array_intersect($selectCols, $tmpCols));
        $colList = implode(', ', $available);

        DB::statement("INSERT INTO feedback_surveys ({$colList}) SELECT {$colList} FROM feedback_surveys__module_id_tmp");

        Schema::drop('feedback_surveys__module_id_tmp');
        Schema::enableForeignKeyConstraints();
    }
};
