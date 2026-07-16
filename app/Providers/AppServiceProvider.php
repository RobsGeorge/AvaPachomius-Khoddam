<?php

namespace App\Providers;

use App\Database\LegacySchemaSync;
use App\Database\SafeMySqlConnection;
use App\Database\SafeSQLiteConnection;
use App\Http\View\Composers\AppLayoutComposer;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsStarted;
use App\Tenancy\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

        Event::listen(MigrationsStarted::class, function () {
            LegacySchemaSync::syncAll();
        });

        View::composer(['layouts.app', 'layouts.navigation'], AppLayoutComposer::class);

        // T2 — @capability('exams') ... @endcapability. Returns true when no church is
        // bound (tenancy dormant) so nav renders unchanged in production until cutover.
        Blade::if('capability', fn (string $key) => TenantContext::current()?->hasCapability($key) ?? true);
    }
}
