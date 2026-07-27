<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Tests\Support\EventModuleTestCase;

/**
 * Portal-wide CSRF / Page Expired regressions for guests and authenticated users.
 */
class CsrfSessionHardeningTest extends EventModuleTestCase
{
    public function test_session_lifetime_defaults_to_eight_hours(): void
    {
        $configSource = file_get_contents(config_path('session.php'));
        $this->assertIsString($configSource);
        $this->assertStringContainsString("env('SESSION_LIFETIME', 480)", $configSource);

        $example = file_get_contents(base_path('.env.example'));
        $this->assertIsString($example);
        $this->assertMatchesRegularExpression('/^SESSION_LIFETIME=480\s*$/m', $example);
    }

    public function test_guest_can_fetch_csrf_token(): void
    {
        $response = $this->getJson(route('csrf.token'));

        $response->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_authenticated_user_can_fetch_csrf_token(): void
    {
        $user = $this->createUser(['email' => 'csrf-auth@example.com']);

        $response = $this->actingAs($user)->getJson(route('csrf.token'));

        $response->assertOk();
        $this->assertSame(csrf_token(), $response->json('token'));
    }

    public function test_guest_login_html_is_not_store_cached(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('csrf-heal.js', false);
        $response->assertSee('name="csrf-token"', false);
    }

    public function test_authenticated_html_is_not_store_cached(): void
    {
        $user = $this->createUser([
            'email' => 'csrf-dash@example.com',
            'is_superadmin' => true,
        ]);

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('csrf-heal.js', false);
    }

    public function test_token_mismatch_json_returns_fresh_csrf_token(): void
    {
        $request = \Illuminate\Http\Request::create('/theme', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $session = $this->app['session']->driver();
        $session->start();
        $oldToken = $session->token();
        $request->setLaravelSession($session);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(419, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(__('auth.page_expired'), $payload['message'] ?? null);
        $this->assertNotEmpty($payload['csrf_token'] ?? null);
        $this->assertNotSame($oldToken, $payload['csrf_token']);
        $this->assertSame($session->token(), $payload['csrf_token']);
    }

    public function test_token_mismatch_web_regenerates_token_and_avoids_post_url(): void
    {
        $user = $this->createUser(['email' => 'csrf-web@example.com']);
        Auth::login($user);

        $postUrl = url('/profile/picture');
        $request = \Illuminate\Http\Request::create($postUrl, 'POST', [
            '_token' => 'stale',
            '_method' => 'PUT',
        ]);
        // Referer equals the failed POST — must fall back to a safe GET.
        $request->headers->set('referer', $postUrl);
        $session = $this->app['session']->driver();
        $session->start();
        $oldToken = $session->token();
        $request->setLaravelSession($session);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('profile'), $response->headers->get('Location'));
        $this->assertNotSame($oldToken, $session->token());
        $this->assertSame(__('auth.page_expired'), $session->get('warning'));
    }

    public function test_guest_token_mismatch_falls_back_to_login(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'POST', [
            '_token' => 'stale',
            'email' => 'x@example.com',
            'password' => 'secret',
        ]);
        $request->headers->set('referer', url('/login'));
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
    }

    public function test_exam_take_js_reads_csrf_live_not_cached_const(): void
    {
        $source = file_get_contents(public_path('js/exam-take.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('function csrfToken()', $source);
        $this->assertStringNotContainsString(
            "const csrf = document.querySelector('meta[name=\"csrf-token\"]')",
            $source
        );
        $this->assertMatchesRegularExpression("/'X-CSRF-TOKEN':\\s*csrfToken\\(\\)/", $source);
    }

    public function test_csrf_heal_script_is_present_and_covers_resume_hooks(): void
    {
        $source = file_get_contents(public_path('js/csrf-heal.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('/csrf-token', $source);
        $this->assertStringContainsString('visibilitychange', $source);
        $this->assertStringContainsString('pageshow', $source);
        $this->assertStringContainsString('csrf:refreshed', $source);
        $this->assertStringContainsString('419', $source);
        $this->assertStringContainsString('window.KhoddamCsrf', $source);
    }

    public function test_live_quiz_present_loads_csrf_heal(): void
    {
        $source = file_get_contents(resource_path('views/live-quiz/host/present.blade.php'));

        $this->assertStringContainsString('csrf-heal.js', $source);
        $this->assertStringContainsString('csrf:refreshed', $source);
        $this->assertStringContainsString('liveCsrfToken', $source);
    }

    public function test_refreshed_csrf_token_matches_session_and_verify_middleware(): void
    {
        $tokenResponse = $this->getJson(route('csrf.token'));
        $tokenResponse->assertOk();
        $token = $tokenResponse->json('token');

        $this->assertSame(session()->token(), $token);

        $request = \Illuminate\Http\Request::create('/theme', 'POST', ['theme' => 'dark']);
        $request->headers->set('X-CSRF-TOKEN', $token);
        $request->setLaravelSession($this->app['session']->driver());

        $middleware = $this->app->make(\App\Http\Middleware\VerifyCsrfToken::class);
        $tokensMatch = new \ReflectionMethod($middleware, 'tokensMatch');
        $tokensMatch->setAccessible(true);

        $this->assertTrue($tokensMatch->invoke($middleware, $request));

        $stale = \Illuminate\Http\Request::create('/theme', 'POST', ['theme' => 'dark']);
        $stale->headers->set('X-CSRF-TOKEN', 'definitely-not-the-session-token');
        $stale->setLaravelSession($this->app['session']->driver());

        $this->assertFalse($tokensMatch->invoke($middleware, $stale));
    }

    public function test_superadmin_and_student_alike_get_no_store_on_html(): void
    {
        $student = $this->createUser(['email' => 'csrf-student@example.com']);
        $super = $this->createUser([
            'email' => 'csrf-super@example.com',
            'is_superadmin' => true,
        ]);

        foreach ([$student, $super] as $user) {
            $response = $this->actingAs($user)->get(route('profile'));
            $response->assertOk();
            $this->assertStringContainsString(
                'no-store',
                (string) $response->headers->get('Cache-Control'),
                'Expected no-store for user '.$user->email
            );
        }
    }
}
