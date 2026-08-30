<?php

use App\Database\MigrationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSupport::addColumn('user', 'deleted_at', function (Blueprint $table) {
            $definition = $table->timestamp('deleted_at')->nullable();

            if (Schema::hasColumn('user', 'updated_at')) {
                $definition->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        // Brownfield-safe migrations do not drop columns.
    }
};
