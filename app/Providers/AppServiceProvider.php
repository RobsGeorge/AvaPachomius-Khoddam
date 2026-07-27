<?php

namespace App\Providers;

use App\Cache\ResilientFileStore;
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
use Illuminate\Support\Facades\Cache;
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
        $this->app->singleton(\App\Services\ScheduledTaskRunner::class);
        $this->app->singleton(\App\Services\SchedulerHealthService::class);

        // Override before any cache store is resolved (production CACHE_DRIVER=file).
        $this->app->booting(function () {
            Cache::extend('file', function ($app, array $config) {
                return $this->repository(
                    (new ResilientFileStore(
                        $app['files'],
                        $config['path'],
                        $config['permission'] ?? null
                    ))->setLockDirectory($config['lock_path'] ?? null)
                );
            });
        });
    }

    public function boot(): void
    {
        $this->ensureFileCacheDirectoryExists();

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

    /**
     * Guarantees the file-cache root exists after deploy optimize:clear / empty clones.
     */
    protected function ensureFileCacheDirectoryExists(): void
    {
        $path = config('cache.stores.file.path');

        if (! is_string($path) || $path === '') {
            return;
        }

        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}
