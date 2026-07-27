<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $this->forceIsolatedTestingEnvironment();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Staging/prod shells export APP_ENV, APP_URL, DB_*, MULTI_TENANT. PHPUnit's
     * force="true" updates putenv/$_ENV but not $_SERVER, and Laravel's env() can
     * still read $_SERVER. A production config cache also bypasses env entirely.
     */
    private function forceIsolatedTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => 'base64:QpqWBoNnyF/v8SmlRC/DzLq9d75hjncrv55mbjLWsVc=',
            'MULTI_TENANT' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'BCRYPT_ROUNDS' => '4',
            // Point config cache at a non-existent file so staging's config.php
            // (baked with mysql / MULTI_TENANT=true) is never loaded in tests.
            'APP_CONFIG_CACHE' => 'bootstrap/cache/config.testing-disabled.php',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
