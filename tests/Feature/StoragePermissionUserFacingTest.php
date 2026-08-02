<?php

namespace Tests\Feature;

use App\Exceptions\StoragePermissionException;
use ErrorException;
use Illuminate\Support\Facades\Auth;
use Tests\Support\EventModuleTestCase;

/**
 * Users must never see a raw 500 for deploy/www-data storage permission races.
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
        $this->assertSame(
            'If this keeps happening, contact support or your church administrator.',
            __('app.storage_unavailable_contact', [], 'en')
        );
    }

    public function test_handler_web_flashes_error_toast_instead_of_500(): void
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

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(__('app.storage_unavailable'), $session->get('error'));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_handler_falls_back_to_dedicated_page_when_session_unavailable(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'GET');
        // No session bound — flash redirect must not be used.

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(503, $response->getStatusCode());
        $html = $response->getContent();
        $this->assertStringContainsString(__('app.storage_unavailable_title'), $html);
        $this->assertStringContainsString(__('app.storage_unavailable'), $html);
        $this->assertStringContainsString(__('app.storage_unavailable_contact'), $html);
        $this->assertStringNotContainsString('Server Error', $html);
    }

    public function test_authenticated_user_redirect_targets_profile_fallback(): void
    {
        $user = $this->createUser(['email' => 'storage-perm@example.com']);
        Auth::login($user);

        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        // previous == current → fallback to profile
        $request->headers->set('referer', url('/dashboard'));

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new ErrorException('chmod(): Operation not permitted'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('profile').'?storage_retry=1', $response->headers->get('Location'));
        $this->assertSame(__('app.storage_unavailable'), $session->get('error'));
    }

    /**
     * Reproduces the prod "too many redirects" report: a guest whose session file
     * has a broken permission (post-deploy chmod/chown race) fails storage writes
     * on *every* request, not just this one. `tryFlashRedirect()` bounces to the
     * referer/previous URL (or the login/profile fallback) hoping that page will
     * render fine — but if the underlying permission problem persists, that page
     * fails identically and bounces right back, forming a genuine browser-visible
     * infinite redirect loop between two URLs. Other users (healthy session
     * files) never hit this exception at all, so they're unaffected.
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

        if ($response->getStatusCode() === 302) {
            $this->assertNotSame(
                $request->fullUrl(),
                $response->headers->get('Location'),
                'Redirecting a broken /login request back to /login forms an infinite loop.'
            );

            return;
        }

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString(__('app.storage_unavailable_title'), $response->getContent());
    }

    /**
     * Full two-hop reproduction: the guest's stale, frozen `_previous.url` (never
     * updated because writes keep failing) points at some other protected page.
     * Request 1 (GET /dashboard) fails and bounces to /login. If the browser then
     * follows that redirect and the *same* persistent permission problem fires
     * again on GET /login, the fix must show the dedicated error page instead of
     * bouncing back to /dashboard — capping the chain at one hop instead of an
     * unbounded ping-pong between the two URLs.
     */
    public function test_persistent_failure_across_two_requests_does_not_ping_pong(): void
    {
        $exception = fn () => StoragePermissionException::fromThrowable(
            new ErrorException('file_put_contents(...): Failed to open stream: Permission denied')
        );
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        // Request 1: guest hits a protected page; their frozen previous URL
        // (stale from before storage broke) is the same page, so it falls back
        // to the login route.
        $request1 = \Illuminate\Http\Request::create(url('/dashboard'), 'GET');
        $session1 = $this->app['session']->driver();
        $session1->start();
        $session1->put('_previous.url', url('/dashboard'));
        $request1->setLaravelSession($session1);

        $response1 = $handler->render($request1, $exception());
        $this->assertSame(302, $response1->getStatusCode());
        $firstHop = $response1->headers->get('Location');
        $this->assertStringContainsString('storage_retry=1', $firstHop);

        // Request 2: the browser follows that redirect. The underlying problem
        // is persistent, so the *same* exception fires again on the new URL.
        $request2 = \Illuminate\Http\Request::create($firstHop, 'GET');
        $session2 = $this->app['session']->driver();
        $session2->start();
        $session2->put('_previous.url', url('/dashboard'));
        $request2->setLaravelSession($session2);

        $response2 = $handler->render($request2, $exception());

        $this->assertSame(503, $response2->getStatusCode(), 'Second failure on the same broken request must stop bouncing and show the dedicated page.');
        $this->assertStringContainsString(__('app.storage_unavailable_title'), $response2->getContent());
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
