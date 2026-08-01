<?php

namespace Tests\Unit\Cache;

use App\Cache\ResilientFileStore;
use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResilientFileStoreTest extends TestCase
{
    private string $directory;

    private ResilientFileStore $store;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->directory = storage_path('framework/cache/resilient-test-'.uniqid('', true));
        $this->files->makeDirectory($this->directory, 0777, true, true);
        $this->store = new ResilientFileStore($this->files, $this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory) && is_dir($this->directory)) {
            $this->files->deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    public function test_file_driver_is_bound_to_resilient_store(): void
    {
        config(['cache.default' => 'file']);
        $this->app->make('cache')->forgetDriver();

        $this->assertInstanceOf(ResilientFileStore::class, Cache::getStore());
    }

    public function test_put_recovers_when_hash_subdirectory_was_deleted(): void
    {
        $this->assertTrue($this->store->put('user:29:demo', 'v1', 60));
        $this->assertSame('v1', $this->store->get('user:29:demo'));

        $this->wipeHashDirectories();

        // Production failure mode: directory gone between ensure + file_put_contents.
        $this->assertTrue($this->store->put('user:29:demo', 'v2', 60));
        $this->assertSame('v2', $this->store->get('user:29:demo'));
    }

    public function test_add_recovers_when_hash_subdirectory_was_deleted(): void
    {
        $this->assertTrue($this->store->add('rate:limit:a', 1, 60));

        $this->wipeHashDirectories();

        $this->assertTrue($this->store->add('rate:limit:b', 1, 60));
        $this->assertSame(1, $this->store->get('rate:limit:b'));
    }

    public function test_put_recreates_missing_base_directory(): void
    {
        $this->files->deleteDirectory($this->directory);
        $this->assertDirectoryDoesNotExist($this->directory);

        $this->assertTrue($this->store->put('orphan-key', 'ok', 60));
        $this->assertSame('ok', $this->store->get('orphan-key'));
        $this->assertDirectoryExists($this->directory);
    }

    public function test_stock_file_store_throws_on_toctou_directory_race(): void
    {
        $files = new class extends Filesystem
        {
            public bool $vanishAfterPositiveExists = false;

            public function exists($path)
            {
                $result = parent::exists($path);

                // One-shot: concurrent cache:clear / flush after FileStore's exists() check.
                if ($this->vanishAfterPositiveExists && $result && is_dir($path)) {
                    $this->vanishAfterPositiveExists = false;
                    parent::deleteDirectory($path);
                }

                return $result;
            }
        };

        $directory = storage_path('framework/cache/stock-race-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $stock = new FileStore($files, $directory);

        try {
            $this->assertTrue($stock->put('race-key', 'v1', 60));

            $files->vanishAfterPositiveExists = true;

            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessageMatches('/Failed to open stream: No such file or directory/i');

            $stock->put('race-key', 'v2', 60);
        } finally {
            $files->vanishAfterPositiveExists = false;
            if (is_dir($directory)) {
                $files->deleteDirectory($directory);
            }
        }
    }

    public function test_resilient_store_survives_toctou_directory_race(): void
    {
        $files = new class extends Filesystem
        {
            public bool $vanishAfterPositiveExists = false;

            public function exists($path)
            {
                $result = parent::exists($path);

                if ($this->vanishAfterPositiveExists && $result && is_dir($path)) {
                    $this->vanishAfterPositiveExists = false;
                    parent::deleteDirectory($path);
                }

                return $result;
            }
        };

        $directory = storage_path('framework/cache/resilient-race-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            $this->assertTrue($store->put('race-key', 'v1', 60));

            $files->vanishAfterPositiveExists = true;

            $this->assertTrue($store->put('race-key', 'v2', 60));
            $this->assertSame('v2', $store->get('race-key'));
        } finally {
            $files->vanishAfterPositiveExists = false;
            if (is_dir($directory)) {
                $files->deleteDirectory($directory);
            }
        }
    }

    private function wipeHashDirectories(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach ($this->files->directories($this->directory) as $directory) {
            $this->files->deleteDirectory($directory);
        }
    }

    public function test_put_recovers_when_hash_subdirectory_is_not_writable(): void
    {
        $this->assertTrue($this->store->put('perm-key', 'v1', 60));

        $hashDir = dirname($this->store->path('perm-key'));
        $this->files->chmod($hashDir, 0555);

        $this->assertTrue($this->store->put('perm-key', 'v2', 60));
        $this->assertSame('v2', $this->store->get('perm-key'));
    }

    public function test_put_survives_chmod_operation_not_permitted_after_write(): void
    {
        $files = new class extends Filesystem
        {
            public function chmod($path, $mode = null)
            {
                if ($mode !== null) {
                    throw new \ErrorException('chmod(): Operation not permitted');
                }

                return parent::chmod($path, null);
            }
        };

        $directory = storage_path('framework/cache/resilient-chmod-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory, 0664);

        try {
            $this->assertTrue($store->put('login-perm-cache', ['ok' => true], 60));
            $this->assertSame(['ok' => true], $store->get('login-perm-cache'));
        } finally {
            if (is_dir($directory)) {
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_put_recreates_unwritable_hash_dir_when_chmod_is_forbidden(): void
    {
        $files = new class extends Filesystem
        {
            public function chmod($path, $mode = null)
            {
                if ($mode !== null) {
                    throw new \ErrorException('chmod(): Operation not permitted');
                }

                return parent::chmod($path, null);
            }
        };

        $directory = storage_path('framework/cache/resilient-recreate-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            $this->assertTrue($store->put('recreate-key', 'v1', 60));
            $hashDir = dirname($store->path('recreate-key'));
            // Simulate deploy-owned non-writable hash dir (www-data cannot chmod).
            @chmod($hashDir, 0555);

            if (is_writable($hashDir)) {
                $this->markTestSkipped('Platform does not honor chmod 0555 for is_writable()');
            }

            $this->assertTrue($store->put('recreate-key', 'v2', 60));
            $this->assertSame('v2', $store->get('recreate-key'));
            clearstatcache(true, dirname($store->path('recreate-key')));
            $this->assertDirectoryExists(dirname($store->path('recreate-key')));
        } finally {
            if (is_dir($directory)) {
                @chmod($directory, 0777);
                foreach ((new Filesystem)->directories($directory) as $child) {
                    @chmod($child, 0777);
                }
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_non_missing_path_errors_are_not_swallowed(): void
    {
        $files = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false)
            {
                throw new \ErrorException('Permission denied');
            }
        };

        $directory = storage_path('framework/cache/resilient-perm-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Permission denied');
            $store->put('denied-key', 'x', 60);
        } finally {
            if (is_dir($directory)) {
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_unrecoverable_stream_permission_denied_becomes_storage_permission_exception(): void
    {
        $files = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false)
            {
                throw new \ErrorException(
                    'file_put_contents('.$path.'): Failed to open stream: Permission denied'
                );
            }
        };

        $directory = storage_path('framework/cache/resilient-storage-ex-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            $this->expectException(\App\Exceptions\StoragePermissionException::class);
            $store->put('user:43:perms', 'x', 60);
        } finally {
            if (is_dir($directory)) {
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_chmod_operation_not_permitted_on_unrecoverable_root_becomes_storage_exception(): void
    {
        $files = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false)
            {
                throw new \ErrorException('chmod(): Operation not permitted');
            }
        };

        $directory = storage_path('framework/cache/resilient-chmod-ex-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            // chmod errors are "recoverable" once; second failure still wraps.
            $this->expectException(\App\Exceptions\StoragePermissionException::class);
            $store->put('impersonate-cache', 'x', 60);
        } finally {
            if (is_dir($directory)) {
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_non_error_exception_throwables_are_not_swallowed(): void
    {
        $files = new class extends Filesystem
        {
            public function put($path, $contents, $lock = false)
            {
                throw new \RuntimeException('disk exploded');
            }
        };

        $directory = storage_path('framework/cache/resilient-runtime-'.uniqid('', true));
        $files->makeDirectory($directory, 0777, true, true);
        $store = new ResilientFileStore($files, $directory);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('disk exploded');
            $store->put('boom-key', 'x', 60);
        } finally {
            if (is_dir($directory)) {
                (new Filesystem)->deleteDirectory($directory);
            }
        }
    }

    public function test_add_does_not_overwrite_existing_key_after_recovery(): void
    {
        $this->assertTrue($this->store->add('once-key', 'first', 60));
        $this->wipeHashDirectories();

        // Key payload is gone with the hash dirs; add should recreate and succeed.
        $this->assertTrue($this->store->add('once-key', 'second', 60));
        $this->assertSame('second', $this->store->get('once-key'));

        // With directory intact, add must refuse overwrite.
        $this->assertFalse($this->store->add('once-key', 'third', 60));
        $this->assertSame('second', $this->store->get('once-key'));
    }

    public function test_put_handles_keys_with_colons_and_unicode(): void
    {
        $key = 'tenant:كنيسة:rate-limit:42';
        $this->wipeHashDirectories();
        $this->assertTrue($this->store->put($key, ['ok' => true], 60));
        $this->assertSame(['ok' => true], $this->store->get($key));
    }

    public function test_repeated_flush_and_put_cycles_do_not_throw(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->store->put('cycle-'.$i, 'v'.$i, 30));
            $this->wipeHashDirectories();
            if ($i % 2 === 0) {
                $this->files->deleteDirectory($this->directory);
            }
            $this->assertTrue($this->store->put('cycle-'.$i, 'v'.$i.'-b', 30));
            $this->assertSame('v'.$i.'-b', $this->store->get('cycle-'.$i));
        }
    }
}
