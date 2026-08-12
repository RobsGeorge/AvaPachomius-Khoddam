<?php

use App\Database\SchemaGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T9 — platform billing & entitlements (expand-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        SchemaGuards::createTableIfMissing('platform_feature', function (Blueprint $table) {
            $table->string('feature_key', 60)->primary();
            $table->string('type', 20); // boolean | limit | enum
            $table->string('maps_to_capability', 40)->nullable();
            $table->string('label_key', 120);
            $table->json('enum_options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('subscription_plan', function (Blueprint $table) {
            $table->id('plan_id');
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('tier_rank')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->string('status', 20)->default('draft'); // draft | active | archived
            $table->unsignedInteger('includes_seats')->default(50);
            $table->string('seat_overage_policy', 20)->default('block'); // block | warn | bill
            $table->string('stripe_product_id', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        SchemaGuards::createTableIfMissing('plan_price', function (Blueprint $table) {
            $table->id('plan_price_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('billing_interval', 10); // month | year
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('EGP');
            $table->string('stripe_price_id', 120)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index('plan_id');
            $table->unique(['plan_id', 'billing_interval']);
        });

        SchemaGuards::createTableIfMissing('plan_entitlement', function (Blueprint $table) {
            $table->id('plan_entitlement_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('feature_key', 60);
            $table->json('value');
            $table->unique(['plan_id', 'feature_key']);
            $table->index('plan_id');
        });

        SchemaGuards::createTableIfMissing('billing_account', function (Blueprint $table) {
            $table->id('billing_account_id');
            $table->unsignedBigInteger('organization_id')->unique();
            $table->string('stripe_customer_id', 120)->nullable();
            $table->string('billing_email', 191)->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->string('default_currency', 3)->default('EGP');
            $table->timestamps();
            $table->index('stripe_customer_id');
        });

        SchemaGuards::createTableIfMissing('church_subscription', function (Blueprint $table) {
            $table->id('church_subscription_id');
            $table->unsignedBigInteger('church_id')->unique();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('plan_price_id')->nullable();
            $table->unsignedBigInteger('billing_account_id')->nullable();
            $table->string('status', 20)->default('comped');
            $table->string('stripe_subscription_id', 120)->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->unsignedInteger('seat_count_purchased')->nullable();
            $table->unsignedInteger('seat_count_effective')->nullable();
            $table->unsignedBigInteger('comped_by_user_id')->nullable();
            $table->string('comp_reason', 255)->nullable();
            $table->timestamps();
            $table->index('plan_id');
            $table->index('billing_account_id');
            $table->index('status');
        });

        SchemaGuards::createTableIfMissing('church_entitlement_override', function (Blueprint $table) {
            $table->id('church_entitlement_override_id');
            $table->unsignedBigInteger('church_id');
            $table->string('feature_key', 60);
            $table->json('value');
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('granted_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'feature_key']);
            $table->index('church_id');
        });

        SchemaGuards::createTableIfMissing('church_entitlement_snapshot', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->primary();
            $table->json('features');
            $table->timestamp('resolved_at');
        });

        SchemaGuards::createTableIfMissing('church_usage_counter', function (Blueprint $table) {
            $table->id('church_usage_counter_id');
            $table->unsignedBigInteger('church_id');
            $table->string('feature_key', 60);
            $table->string('period_key', 20)->default('lifetime');
            $table->unsignedBigInteger('used_amount')->default(0);
            $table->unique(['church_id', 'feature_key', 'period_key']);
            $table->index('church_id');
        });

        SchemaGuards::createTableIfMissing('billing_webhook_event', function (Blueprint $table) {
            $table->id('billing_webhook_event_id');
            $table->string('stripe_event_id', 120)->unique();
            $table->string('type', 80);
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_event');
        Schema::dropIfExists('church_usage_counter');
        Schema::dropIfExists('church_entitlement_snapshot');
        Schema::dropIfExists('church_entitlement_override');
        Schema::dropIfExists('church_subscription');
        Schema::dropIfExists('billing_account');
        Schema::dropIfExists('plan_entitlement');
        Schema::dropIfExists('plan_price');
        Schema::dropIfExists('subscription_plan');
        Schema::dropIfExists('platform_feature');
    }
};
