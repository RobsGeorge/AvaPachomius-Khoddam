<?php

namespace Tests\Feature;

use App\Exceptions\StoragePermissionException;
use ErrorException;
use Illuminate\Support\Facades\Auth;
use Tests\Support\EventModuleTestCase;

/**
 * Users must never see a raw 500 for deploy/www-data storage permission races,
 * and never a redirect: the retry target runs on the same broken storage, which
 * produced an ERR_TOO_MANY_REDIRECTS loop between `/` and `/login` in production.
 */
class StoragePermissionUserFacingTest extends EventModuleTestCase
{
    public function test_handler_json_returns_503_with_contact_message(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(503, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('storage_unavailable', $payload['error'] ?? null);
        $this->assertSame(__('app.storage_unavailable'), $payload['message'] ?? null);
        $this->assertSame('60', $response->headers->get('Retry-After'));
        $this->assertSame(
            'If this keeps happening, contact support or your church administrator.',
            __('app.storage_unavailable_contact', [], 'en')
        );
    }

    public function test_handler_web_renders_terminal_page_instead_of_redirect(): void
    {
        $request = \Illuminate\Http\Request::create('/profile', 'GET');
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->headers->set('referer', route('login'));

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render(
                $request,
                StoragePermissionException::fromThrowable(
                    new ErrorException('file_put_contents(...): Failed to open stream: Permission denied')
                )
            );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
        $this->assertStringContainsString(__('app.storage_unavailable'), $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_handler_falls_back_to_dedicated_page_when_session_unavailable(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'GET');
        // No session bound — the page must still render without touching the session.

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(503, $response->getStatusCode());
        $html = $response->getContent();
        $this->assertStringContainsString(__('app.storage_unavailable_title'), $html);
        $this->assertStringContainsString(__('app.storage_unavailable'), $html);
        $this->assertStringContainsString(__('app.storage_unavailable_contact'), $html);
        $this->assertStringNotContainsString('Server Error', $html);
    }

    public function test_authenticated_user_also_gets_terminal_page(): void
    {
        $user = $this->createUser(['email' => 'storage-perm@example.com']);
        Auth::login($user);

        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->headers->set('referer', url('/dashboard'));

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
    }

    /**
     * A guest whose storage writes fail on /login itself must not be sent anywhere:
     * the only candidate targets are the page that just failed or another page
     * served by the same broken storage.
     */
    public function test_guest_failing_on_login_itself_does_not_redirect_to_itself(): void
    {
        $request = \Illuminate\Http\Request::create(route('login'), 'GET');
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        // Fresh guest hit, no prior page — no referer, no stored previous URL.

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render(
                $request,
                StoragePermissionException::fromThrowable(
                    new ErrorException('file_put_contents(...): Failed to open stream: Permission denied')
                )
            );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
        $this->assertStringContainsString(__('app.storage_unavailable_title'), $response->getContent());
    }

    /**
     * Prod reproduction: the visitor's stale, frozen `_previous.url` (never updated
     * because writes keep failing) points at another protected page, so the old
     * referer/previous-URL bounce ping-ponged between two URLs. Every request in a
     * persistent failure must terminate on its own.
     */
    public function test_persistent_failure_across_two_requests_does_not_ping_pong(): void
    {
        $exception = fn () => StoragePermissionException::fromThrowable(
            new ErrorException('file_put_contents(...): Failed to open stream: Permission denied')
        );
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        foreach ([url('/dashboard'), route('login')] as $url) {
            $request = \Illuminate\Http\Request::create($url, 'GET');
            $session = $this->app['session']->driver();
            $session->start();
            $session->put('_previous.url', url('/dashboard'));
            $request->setLaravelSession($session);

            $response = $handler->render($request, $exception());

            $this->assertSame(503, $response->getStatusCode(), "{$url} must not bounce during a persistent storage failure.");
            $this->assertNull($response->headers->get('Location'));
            $this->assertStringContainsString(__('app.storage_unavailable_title'), $response->getContent());
        }
    }

    public function test_arabic_copy_is_localized(): void
    {
        app()->setLocale('ar');

        $request = \Illuminate\Http\Request::create('/login', 'GET');
        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('مشكلة مؤقتة', $response->getContent());
        $this->assertStringContainsString('مسؤول الكنيسة', $response->getContent());
    }
}
