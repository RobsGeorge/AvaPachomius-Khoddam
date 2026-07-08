<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('user', 'font_size_preference', function (Blueprint $table) {
            $definition = $table->string('font_size_preference', 20)->default('normal');

            if (Schema::hasColumn('user', 'student_onboarding_completed_at')) {
                $definition->after('student_onboarding_completed_at');
            }
        });
    }

    public function down(): void
    {
        // Brownfield-safe migrations do not drop columns.
    }
};
