<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('live_feedback_responses');
        Schema::dropIfExists('live_feedback_sessions');
    }

    public function down(): void
    {
        // Live feedback removed; tables are not recreated.
    }
};
