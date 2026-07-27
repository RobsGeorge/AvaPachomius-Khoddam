<?php

namespace Tests\Unit\Testing;

use Tests\TestCase;

/**
 * Guards the staging/prod shell-leak fix: CreatesApplication + phpunit.xml force=true.
 */
class IsolatedTestingEnvironmentTest extends TestCase
{
    public function test_runtime_env_is_testing_not_staging_or_production(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertFalse(app()->environment('production'));
        $this->assertFalse(app()->environment('staging'));
        $this->assertSame('testing', $_SERVER['APP_ENV'] ?? null);
        $this->assertSame('testing', $_ENV['APP_ENV'] ?? null);
        $this->assertSame('testing', getenv('APP_ENV') ?: null);
    }

    public function test_database_is_forced_to_sqlite_memory(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', $_SERVER['DB_CONNECTION'] ?? null);
        $this->assertSame(':memory:', $_SERVER['DB_DATABASE'] ?? null);
    }

    public function test_multi_tenant_is_forced_off_during_tests(): void
    {
        $this->assertFalse((bool) config('tenancy.enabled'));
        $this->assertSame('false', (string) ($_SERVER['MULTI_TENANT'] ?? ''));
        $this->assertSame('false', (string) (getenv('MULTI_TENANT') ?: ''));
    }

    public function test_app_url_is_localhost_not_staging_host(): void
    {
        $url = (string) config('app.url');
        $this->assertStringContainsString('localhost', $url);
        $this->assertStringNotContainsString('avapakhomios.com', $url);
        $this->assertSame('http://localhost', $_SERVER['APP_URL'] ?? null);
    }

    public function test_config_cache_path_points_at_disabled_testing_file(): void
    {
        $path = (string) ($_SERVER['APP_CONFIG_CACHE'] ?? '');
        $this->assertStringContainsString('config.testing-disabled.php', $path);
        $this->assertFileDoesNotExist(base_path($path));
    }

    public function test_ephemeral_drivers_are_forced_for_cache_session_queue_mail(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('array', config('mail.default'));
    }

    public function test_phpunit_xml_forces_critical_env_keys(): void
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));
        $this->assertStringContainsString('force="true"', $xml);

        foreach ([
            'APP_ENV',
            'APP_URL',
            'DB_CONNECTION',
            'DB_DATABASE',
            'MULTI_TENANT',
            'CACHE_DRIVER',
            'SESSION_DRIVER',
        ] as $key) {
            $this->assertMatchesRegularExpression(
                '/<env\s+name="'.preg_quote($key, '/').'"\s+value="[^"]*"\s+force="true"\s*\/>/',
                $xml,
                "phpunit.xml must force={$key}"
            );
        }
    }
}
