<?php

namespace Tests\Unit\Support;

use App\Exceptions\StoragePermissionException;
use App\Support\ResilientCache;
use ErrorException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class ResilientCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ResilientCache::forgetReportedKeys();
    }

    public function test_it_caches_normally_when_storage_is_healthy(): void
    {
        Cache::swap(new Repository(new ArrayStore));

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->assertSame('value', ResilientCache::remember('k', 60, $compute));
        $this->assertSame('value', ResilientCache::remember('k', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function test_it_serves_an_uncached_value_when_the_cache_cannot_be_written(): void
    {
        Cache::swap(new Repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds)
            {
                throw StoragePermissionException::fromThrowable(
                    new ErrorException('file_put_contents(...): Failed to open stream: Permission denied')
                );
            }
        }));

        $this->assertSame('value', ResilientCache::remember('k', 60, fn () => 'value'));
    }

    /** Production hit this one as a raw ErrorException from mkdir() on a root-owned shard. */
    public function test_it_serves_an_uncached_value_when_a_shard_directory_cannot_be_created(): void
    {
        Cache::swap(new Repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds)
            {
                throw new ErrorException('mkdir(): Permission denied');
            }
        }));

        $this->assertSame('value', ResilientCache::remember('k', 60, fn () => 'value'));
    }

    public function test_it_rethrows_errors_that_are_not_storage_permission_failures(): void
    {
        Cache::swap(new Repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds)
            {
                throw new RuntimeException('redis is down');
            }
        }));

        $this->expectException(RuntimeException::class);

        ResilientCache::remember('k', 60, fn () => 'value');
    }
}
