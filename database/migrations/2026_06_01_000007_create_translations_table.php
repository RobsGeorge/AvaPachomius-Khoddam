<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('translations', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 191);
            $table->string('locale', 5);
            $table->text('value');
            $table->timestamps();

            $table->unique(['group', 'key', 'locale']);
            $table->index(['locale', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
