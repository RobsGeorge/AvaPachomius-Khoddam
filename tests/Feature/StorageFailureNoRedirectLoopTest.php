<?php

namespace Tests\Feature;

use App\Exceptions\StoragePermissionException;
use App\Support\ResilientCache;
use ErrorException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\Support\EventModuleTestCase;

/**
 * Production incident: a cache shard directory owned by the deploy user made every
 * cache write throw for whoever's key hashed into it. The storage error was rendered
 * as a redirect, so the browser bounced `/` → `/login` → `/` forever and users saw
 * ERR_TOO_MANY_REDIRECTS.
 */
class StorageFailureNoRedirectLoopTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ResilientCache::forgetReportedKeys();
    }

    private function failEveryCacheWrite(): void
    {
        Cache::swap(new Repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds)
            {
                throw StoragePermissionException::fromThrowable(new ErrorException(
                    'file_put_contents(/var/www/.../cache/data/20/0f/abc): Failed to open stream: Permission denied'
                ));
            }

            public function forever($key, $value)
            {
                return $this->put($key, $value, 0);
            }
        }));
    }

    /** Follow redirects with a hard cap so a regression fails instead of hanging. */
    private function followChain(string $uri, int $maxHops = 5): TestResponse
    {
        $response = $this->get($uri);
        $visited = [$uri];

        for ($hop = 0; $hop < $maxHops; $hop++) {
            if (! $response->isRedirect()) {
                return $response;
            }

            $location = (string) $response->headers->get('Location');
            $visited[] = $location;
            $response = $this->get($location);
        }

        $this->fail('Redirect loop detected: '.implode(' -> ', $visited));
    }

    public function test_guest_landing_on_root_does_not_loop_when_the_cache_is_unwritable(): void
    {
        $this->failEveryCacheWrite();

        $response = $this->followChain('/');

        $response->assertOk();
        $response->assertSee(__('auth.login_button'), false);
    }

    public function test_guest_login_page_renders_when_the_translation_cache_is_unwritable(): void
    {
        $this->failEveryCacheWrite();

        $this->get('/login')->assertOk();
    }

    public function test_signed_in_student_does_not_loop_when_the_permission_cache_is_unwritable(): void
    {
        $user = $this->createUser(['email' => 'loop-student@example.com']);
        $this->actingAs($user);

        $this->failEveryCacheWrite();

        $response = $this->followChain('/dashboard');

        $this->assertFalse($response->isRedirect());
        $this->assertNotSame(503, $response->getStatusCode());
    }
}
