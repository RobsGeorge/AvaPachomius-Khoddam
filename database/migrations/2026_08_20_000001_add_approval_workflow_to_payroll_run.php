<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T6 finance — optional Submit → Approve path alongside the existing direct
 * Finalize flow. Additive only: `status` stays a free string column, so the
 * new "pending_approval" value needs no schema change; these are the audit
 * columns for who submitted/approved/rejected a run and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('payroll_run', 'submitted_at', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('notes');
        });
        MigrationSupport::addColumn('payroll_run', 'submitted_by_id', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by_id')->nullable()->after('submitted_at');
        });
        MigrationSupport::addColumn('payroll_run', 'approved_at', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('submitted_by_id');
        });
        MigrationSupport::addColumn('payroll_run', 'approved_by_id', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by_id')->nullable()->after('approved_at');
        });
        MigrationSupport::addColumn('payroll_run', 'rejected_at', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_by_id');
        });
        MigrationSupport::addColumn('payroll_run', 'rejected_by_id', function (Blueprint $table) {
            $table->unsignedBigInteger('rejected_by_id')->nullable()->after('rejected_at');
        });
        MigrationSupport::addColumn('payroll_run', 'rejection_reason', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('rejected_by_id');
        });
    }

    public function down(): void
    {
        $columns = [
            'submitted_at', 'submitted_by_id',
            'approved_at', 'approved_by_id',
            'rejected_at', 'rejected_by_id',
            'rejection_reason',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('payroll_run', $column)) {
                Schema::table('payroll_run', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
