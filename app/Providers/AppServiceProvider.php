<?php

namespace App\Providers;

use App\Database\LegacySchemaSync;
use App\Database\SafeMySqlConnection;
use App\Database\SafeSQLiteConnection;
use App\Http\View\Composers\AppLayoutComposer;
use App\Tenancy\TenantContext;
use App\Validation\SafeValidator;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new SafeMySqlConnection($connection, $database, $prefix, $config);
        });

        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new SafeSQLiteConnection($connection, $database, $prefix, $config);
        });

        $this->app->singleton(\App\Tenancy\TenantContext::class, fn () => new \App\Tenancy\TenantContext());
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // CVE-2026-48019 / GHSA-5vg9-5847-vvmq — reject CRLF in the built-in email rule
        // until Laravel is upgraded past 12.60 (no official L10 backport).
        Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            return new SafeValidator($translator, $data, $rules, $messages, $attributes);
        });

        Event::listen(MigrationsStarted::class, function () {
            LegacySchemaSync::syncAll();
        });

        View::composer(['layouts.app', 'layouts.navigation'], AppLayoutComposer::class);

        // T2 — @capability('exams') ... @endcapability. Returns true when no church is
        // bound (tenancy dormant) so nav renders unchanged in production until cutover.
        Blade::if('capability', fn (string $key) => TenantContext::current()?->hasCapability($key) ?? true);
    }
}
