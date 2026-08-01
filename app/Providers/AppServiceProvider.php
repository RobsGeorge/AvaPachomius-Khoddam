<?php

namespace App\Providers;

use App\Cache\ResilientFileStore;
use App\Contracts\TenantSecretStore;
use App\Filesystem\SoftChmodFilesystem;
use App\Database\LegacySchemaSync;
use App\Database\SafeMySqlConnection;
use App\Database\SafeSQLiteConnection;
use App\Http\View\Composers\AppLayoutComposer;
use App\Observability\Adapters\LocalProcFsAdapter;
use App\Observability\Adapters\NullInfraMetricsAdapter;
use App\Observability\AlertNotifier;
use App\Observability\Contracts\ErrorSink;
use App\Observability\Contracts\InfraMetricsAdapter;
use App\Observability\ObservabilityRecorder;
use App\Observability\Sinks\LogErrorSink;
use App\Observability\Sinks\NullErrorSink;
use App\Observability\Sinks\SentryErrorSink;
use App\Services\Tenancy\EncryptedConfigTenantSecretStore;
use App\Tenancy\TenantContext;
use App\Validation\SafeValidator;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
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
        $this->app->singleton(TenantSecretStore::class, EncryptedConfigTenantSecretStore::class);

        $this->app->singleton(ErrorSink::class, function () {
            return match (config('observability.error_sink', 'log')) {
                'null' => new NullErrorSink(),
                'sentry' => new SentryErrorSink(),
                default => new LogErrorSink(),
            };
        });

        $this->app->singleton(InfraMetricsAdapter::class, function () {
            return match (config('observability.infra_adapter', 'null')) {
                'local_proc' => new LocalProcFsAdapter(),
                default => new NullInfraMetricsAdapter(),
            };
        });

        $this->app->singleton(AlertNotifier::class, fn () => new AlertNotifier());

        $this->app->singleton(ObservabilityRecorder::class, function ($app) {
            return new ObservabilityRecorder(
                $app->make(ErrorSink::class),
                $app->make(AlertNotifier::class),
            );
        });

        // Override before any cache store is resolved (production CACHE_DRIVER=file).
        // SoftChmodFilesystem: deploy-owned cache nodes must not 500 on chmod().
        $this->app->booting(function () {
            Cache::extend('file', function ($app, array $config) {
                return $this->repository(
                    (new ResilientFileStore(
                        new SoftChmodFilesystem,
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

        try {
            File::ensureDirectoryExists($path, 02775);
            if (is_dir($path) && ! is_writable($path)) {
                @chmod($path, 02775);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
