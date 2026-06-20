<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id('event_id');
            $table->string('title', 100);
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('capacity');
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->foreignId('course_id')->nullable()->constrained('course', 'course_id')->nullOnDelete();
            $table->string('visibility', 30)->default('institution');
            $table->json('eligible_roles')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('check_in_token', 64)->unique();
            $table->foreignId('created_by_id')->constrained('user', 'user_id');
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_admins', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('user', 'user_id')->cascadeOnDelete();
            $table->foreignId('assigned_by_id')->constrained('user', 'user_id');
            $table->timestamp('assigned_at')->useCurrent();
        });

        Schema::create('event_reservation_exceptions', function (Blueprint $table) {
            $table->id('exception_id');
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_id')->constrained('user', 'user_id');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_reservations', function (Blueprint $table) {
            $table->id('reservation_id');
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->string('status', 20);
            $table->dateTime('reserved_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'reserved_at']);
        });

        Schema::create('event_check_ins', function (Blueprint $table) {
            $table->id('check_in_id');
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user', 'user_id')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('event_reservations', 'reservation_id')->cascadeOnDelete();
            $table->dateTime('checked_in_at');
            $table->foreignId('checked_in_by_id')->constrained('user', 'user_id');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_module_test_runs', function (Blueprint $table) {
            $table->id('test_run_id');
            $table->string('suite', 30);
            $table->unsignedInteger('passed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('summary')->nullable();
            $table->longText('output')->nullable();
            $table->string('status', 20)->default('completed');
            $table->foreignId('triggered_by_id')->nullable()->constrained('user', 'user_id')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_module_test_runs');
        Schema::dropIfExists('event_check_ins');
        Schema::dropIfExists('event_reservations');
        Schema::dropIfExists('event_reservation_exceptions');
        Schema::dropIfExists('event_admins');
        Schema::dropIfExists('events');
    }
};
