<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T6 — financial module first cut: payroll runs/lines + money-in.
 * Amounts are integer minor units; currency + fx_rate on every money row.
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('payroll_run', function (Blueprint $table) {
            $table->id('payroll_run_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft')->index(); // draft|finalized
            $table->char('currency', 3)->default('EGP');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('payroll_line', function (Blueprint $table) {
            $table->id('payroll_line_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->bigInteger('gross_minor');
            $table->bigInteger('deductions_minor')->default(0);
            $table->bigInteger('net_minor');
            $table->char('currency', 3)->default('EGP');
            $table->decimal('fx_rate', 24, 12)->default('1');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'user_id']);
        });

        SchemaGuards::createTableIfMissing('money_in', function (Blueprint $table) {
            $table->id('money_in_id');
            $table->unsignedBigInteger('church_id')->index();
            $table->string('source', 191);
            $table->string('category', 120)->index();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('EGP');
            $table->decimal('fx_rate', 24, 12)->default('1');
            $table->date('received_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['money_in', 'payroll_line', 'payroll_run'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
