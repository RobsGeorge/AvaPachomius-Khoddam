<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignment_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 50);
            $table->string('locale', 5);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->timestamps();

            $table->unique(['template_key', 'locale'], 'role_assign_email_tpl_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignment_email_templates');
    }
};
