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
        $this->assertSame(route('profile'), $response->headers->get('Location'));
        $this->assertSame(__('app.storage_unavailable'), $session->get('error'));
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
