<?php

namespace App\Providers;

use App\Database\LegacySchemaSync;
use App\Database\SafeMySqlConnection;
use App\Database\SafeSQLiteConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
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
    }

    public function boot(): void
    {
        Event::listen(MigrationsStarted::class, function () {
            LegacySchemaSync::syncAll();
        });
    }
}
