<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchUser;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\ChurchHost;
use App\Support\ChurchPlace;
use App\Tenancy\BelongsToChurch;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\EventModuleTestCase;

class ChurchApplicationTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('church-registration.store');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'requested_name' => 'St Mark Church',
            'requested_short_name' => 'St Mark',
            'place_district' => 'Smouha',
            'place_governorate' => 'Alexandria',
            'place_country_code' => 'EG',
            'contact_name' => 'Father John',
            'contact_email' => 'contact@stmark.example',
            'contact_mobile' => '01001234567',
            'message' => 'We would like to join the platform.',
        ], $overrides);
    }

    private function pendingApplication(array $overrides = []): ChurchApplication
    {
        return ChurchApplication::create(array_merge([
            'requested_name' => 'Pending Church',
            'requested_short_name' => 'Pending',
            'place_district' => 'Maadi',
            'place_governorate' => 'Cairo',
            'place_country_code' => 'EG',
            'contact_name' => 'Contact Person',
            'contact_email' => 'pending@example.com',
            'contact_mobile' => '01009876543',
            'message' => null,
            'status' => ChurchApplication::STATUS_PENDING,
            'public_token' => ChurchApplication::mintPublicToken(),
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ], $overrides));
    }

    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'church-app-super@example.com',
            'registration_completed' => true,
        ]);
    }

    /** @return array{0: Church, 1: User} */
    private function churchAdmin(): array
    {
        $church = Church::main();
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => 'church-app-admin@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles['church-admin']->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }

    public function test_guest_can_submit_church_registration(): void
    {
        $this->post(route('church-registration.store'), $this->validPayload())
            ->assertRedirect(route('church-registration.thanks'));

        $this->assertDatabaseHas('church_applications', [
            'requested_name' => 'St Mark Church',
            'contact_email' => 'contact@stmark.example',
            'status' => ChurchApplication::STATUS_UNVERIFIED,
        ]);

        $row = ChurchApplication::query()->first();
        $this->assertNotNull($row->submitted_at);
        $this->assertNotNull($row->public_token);
        $this->assertNull($row->email_verified_at);
    }

    public function test_honeypot_filled_submission_creates_no_row(): void
    {
        $this->post(route('church-registration.store'), $this->validPayload([
            'website' => 'https://spam.example',
        ]))->assertRedirect(route('church-registration.thanks'));

        $this->assertSame(0, ChurchApplication::query()->count());
    }

    public function test_honeypot_whitespace_only_website_creates_no_row(): void
    {
        $this->post(route('church-registration.store'), $this->validPayload([
            'website' => '   ',
            'contact_email' => 'whitespace-honey@example.com',
        ]))->assertRedirect(route('church-registration.thanks'));

        $this->assertSame(0, ChurchApplication::query()->count());
    }

    public function test_store_route_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('church-registration.store');
        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_non_superadmin_cannot_access_review_queue(): void
    {
        $plain = $this->createUser(['email' => 'church-app-plain@example.com']);
        [, $churchAdmin] = $this->churchAdmin();
        $application = $this->pendingApplication();

        foreach ([$plain, $churchAdmin] as $user) {
            $this->actingAs($user)
                ->get(route('superadmin.church-applications.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('superadmin.church-applications.show', $application))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('superadmin.church-applications.approve', $application))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('superadmin.church-applications.reject', $application), [
                    'admin_note' => 'Not for you.',
                ])
                ->assertForbidden();
        }
    }

    public function test_superadmin_can_list_and_view_applications(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication(['requested_name' => 'Visible Church']);

        $this->actingAs($super)
            ->get(route('superadmin.church-applications.index'))
            ->assertOk()
            ->assertSee('Visible Church', false);

        $this->actingAs($super)
            ->get(route('superadmin.church-applications.show', $application))
            ->assertOk()
            ->assertSee('Visible Church', false)
            ->assertSee($application->contact_email, false)
            ->assertSee(__('countries.EG'), false);
    }

    public function test_superadmin_can_approve_application(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication();

        $this->actingAs($super)
            ->post(route('superadmin.church-applications.approve', $application))
            ->assertRedirect(route('superadmin.church-applications.show', $application));

        $application->refresh();
        $this->assertSame(ChurchApplication::STATUS_APPROVED, $application->status);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame($super->user_id, $application->reviewed_by_user_id);

        $this->actingAs($super)
            ->get(route('superadmin.church-applications.show', $application))
            ->assertOk()
            ->assertSee(__('church_applications.create_church'), false)
            ->assertSee('superadmin/churches/create?name=Pending', false)
            ->assertSee('place_country_code=EG', false);
    }

    public function test_reject_requires_admin_note(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication();

        $this->actingAs($super)
            ->from(route('superadmin.church-applications.show', $application))
            ->post(route('superadmin.church-applications.reject', $application))
            ->assertRedirect(route('superadmin.church-applications.show', $application))
            ->assertSessionHasErrors('admin_note');

        $this->assertSame(ChurchApplication::STATUS_PENDING, $application->fresh()->status);
    }

    public function test_reject_rejects_whitespace_only_admin_note(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication();

        $this->actingAs($super)
            ->from(route('superadmin.church-applications.show', $application))
            ->post(route('superadmin.church-applications.reject', $application), [
                'admin_note' => '   ',
            ])
            ->assertRedirect(route('superadmin.church-applications.show', $application))
            ->assertSessionHasErrors('admin_note');

        $this->assertSame(ChurchApplication::STATUS_PENDING, $application->fresh()->status);
    }

    public function test_superadmin_can_reject_with_admin_note(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication();

        $this->actingAs($super)
            ->post(route('superadmin.church-applications.reject', $application), [
                'admin_note' => 'Incomplete contact details.',
            ])
            ->assertRedirect(route('superadmin.church-applications.index'));

        $application->refresh();
        $this->assertSame(ChurchApplication::STATUS_REJECTED, $application->status);
        $this->assertSame('Incomplete contact details.', $application->admin_note);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame($super->user_id, $application->reviewed_by_user_id);

        $log = ActivityLog::query()->where('route_name', 'church_application.rejected')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($application->church_application_id, $log->request_input['church_application_id'] ?? null);
        $this->assertSame($application->contact_email, $log->request_input['contact_email'] ?? null);
        $this->assertSame($application->contact_name, $log->request_input['contact_name'] ?? null);
        $this->assertSame('Incomplete contact details.', $log->request_input['admin_note'] ?? null);
        $this->assertSame(ChurchApplication::STATUS_REJECTED, $log->request_input['status'] ?? null);
    }

    public function test_approve_audit_log_includes_contact_snapshot(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication([
            'requested_name' => 'Audit Church',
            'contact_email' => 'audit-approve@example.com',
            'contact_mobile' => '01001112233',
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.church-applications.approve', $application), [
                'admin_note' => 'Looks good.',
            ])
            ->assertRedirect();

        $log = ActivityLog::query()->where('route_name', 'church_application.approved')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($application->church_application_id, $log->request_input['church_application_id'] ?? null);
        $this->assertSame('Audit Church', $log->request_input['requested_name'] ?? null);
        $this->assertSame('audit-approve@example.com', $log->request_input['contact_email'] ?? null);
        $this->assertSame('01001112233', $log->request_input['contact_mobile'] ?? null);
        $this->assertSame('Looks good.', $log->request_input['admin_note'] ?? null);
        $this->assertSame(ChurchApplication::STATUS_APPROVED, $log->request_input['status'] ?? null);
    }

    public function test_missing_each_required_field_redirects_with_session_error(): void
    {
        foreach (['requested_name', 'contact_name', 'contact_email', 'contact_mobile'] as $field) {
            ChurchApplication::query()->delete();

            $payload = $this->validPayload();
            unset($payload[$field]);

            $this->from(route('church-registration'))
                ->post(route('church-registration.store'), $payload)
                ->assertRedirect(route('church-registration'))
                ->assertSessionHasErrors($field);

            $this->assertSame(0, ChurchApplication::query()->count(), "Missing {$field} must not create a row");
        }
    }

    public function test_malformed_email_and_invalid_country_and_max_lengths_are_rejected(): void
    {
        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'contact_email' => 'not-an-email',
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors('contact_email');

        $this->assertFalse(in_array('XX', config('countries'), true));

        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'place_country_code' => 'XX',
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors('place_country_code');

        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'requested_name' => str_repeat('A', ChurchPlace::NAME_MAX + 1),
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors('requested_name');

        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'message' => str_repeat('m', 5001),
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors('message');

        $this->assertSame(0, ChurchApplication::query()->count());
    }

    public function test_omitting_nullable_message_and_short_name_still_succeeds(): void
    {
        $payload = $this->validPayload();
        unset($payload['message'], $payload['requested_short_name']);

        $this->post(route('church-registration.store'), $payload)
            ->assertRedirect(route('church-registration.thanks'));

        $row = ChurchApplication::query()->first();
        $this->assertNotNull($row);
        $this->assertNull($row->message);
        $this->assertNull($row->requested_short_name);
    }

    public function test_stored_xss_payload_is_escaped_on_superadmin_show(): void
    {
        $xss = '<script>alert(1)</script>';

        $this->post(route('church-registration.store'), $this->validPayload([
            'requested_name' => $xss,
            'contact_name' => $xss,
            'message' => $xss,
            'contact_email' => 'xss@example.com',
        ]))->assertRedirect(route('church-registration.thanks'));

        $application = ChurchApplication::query()->first();
        $this->assertNotNull($application);
        $this->assertSame($xss, $application->requested_name);
        $this->assertSame($xss, $application->contact_name);
        $this->assertSame($xss, $application->message);

        $html = $this->actingAs($this->superadmin())
            ->get(route('superadmin.church-applications.show', $application))
            ->assertOk()
            ->getContent();

        // Layout ships real <script> tags; assert the attacker payload is escaped.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_honeypot_key_absent_behaves_like_legitimate_submission(): void
    {
        $payload = $this->validPayload(['contact_email' => 'no-website-key@example.com']);
        $this->assertArrayNotHasKey('website', $payload);

        $this->post(route('church-registration.store'), $payload)
            ->assertRedirect(route('church-registration.thanks'));

        $this->assertDatabaseHas('church_applications', [
            'contact_email' => 'no-website-key@example.com',
            'status' => ChurchApplication::STATUS_UNVERIFIED,
        ]);
    }

    public function test_store_throttle_actually_returns_429_on_31st_request(): void
    {
        $last = null;
        for ($i = 1; $i <= 31; $i++) {
            $last = $this->post(route('church-registration.store'), $this->validPayload([
                'contact_email' => "throttle{$i}@example.com",
                'requested_name' => "Throttle Church {$i}",
            ]));
        }

        $this->assertNotNull($last);
        $this->assertSame(429, $last->getStatusCode());
        $this->assertLessThanOrEqual(30, ChurchApplication::query()->count());
    }

    public function test_store_rejects_missing_csrf_token(): void
    {
        $middleware = app(VerifyCsrfToken::class);
        $exceptProp = new \ReflectionProperty($middleware, 'except');
        $exceptProp->setAccessible(true);
        foreach ($exceptProp->getValue($middleware) as $uri) {
            $this->assertFalse(
                Str::contains((string) $uri, 'register-church'),
                "church-registration.store must not be CSRF-exempt (found except entry: {$uri})"
            );
        }

        // Laravel skips VerifyCsrfToken while runningUnitTests(); force-enable so a
        // future $except entry (or silent middleware removal) is a loud regression.
        $this->app->instance(VerifyCsrfToken::class, new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        // JSON Accept → Handler returns HTTP 419 (web HTML gets a friendly redirect).
        $response = $this->postJson(route('church-registration.store'), $this->validPayload([
            'contact_email' => 'csrf-missing@example.com',
        ]));

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame(0, ChurchApplication::query()->count());
    }

    public function test_cannot_approve_or_reject_non_pending_application(): void
    {
        $super = $this->superadmin();

        $approved = $this->pendingApplication(['contact_email' => 'reapprove@example.com']);
        $this->actingAs($super)
            ->post(route('superadmin.church-applications.approve', $approved))
            ->assertRedirect();

        $approved->refresh();
        $this->assertSame(ChurchApplication::STATUS_APPROVED, $approved->status);
        $reviewedAt = $approved->reviewed_at?->toDateTimeString();

        $this->actingAs($super)
            ->from(route('superadmin.church-applications.show', $approved))
            ->post(route('superadmin.church-applications.approve', $approved))
            ->assertRedirect(route('superadmin.church-applications.show', $approved))
            ->assertSessionHasErrors('application');

        $approved->refresh();
        $this->assertSame(ChurchApplication::STATUS_APPROVED, $approved->status);
        $this->assertSame($reviewedAt, $approved->reviewed_at?->toDateTimeString());

        $this->actingAs($super)
            ->from(route('superadmin.church-applications.show', $approved))
            ->post(route('superadmin.church-applications.reject', $approved), [
                'admin_note' => 'Too late.',
            ])
            ->assertRedirect(route('superadmin.church-applications.show', $approved))
            ->assertSessionHasErrors('application');

        $this->assertSame(ChurchApplication::STATUS_APPROVED, $approved->fresh()->status);

        $rejected = $this->pendingApplication(['contact_email' => 'rereject@example.com']);
        $this->actingAs($super)
            ->post(route('superadmin.church-applications.reject', $rejected), [
                'admin_note' => 'First reject.',
            ])
            ->assertRedirect(route('superadmin.church-applications.index'));

        $this->actingAs($super)
            ->from(route('superadmin.church-applications.show', $rejected))
            ->post(route('superadmin.church-applications.reject', $rejected), [
                'admin_note' => 'Second reject.',
            ])
            ->assertRedirect(route('superadmin.church-applications.show', $rejected))
            ->assertSessionHasErrors('application');

        $rejected->refresh();
        $this->assertSame(ChurchApplication::STATUS_REJECTED, $rejected->status);
        $this->assertSame('First reject.', $rejected->admin_note);
    }

    public function test_guest_is_redirected_to_login_for_review_routes(): void
    {
        $application = $this->pendingApplication();

        $this->get(route('superadmin.church-applications.index'))
            ->assertRedirect(route('login'));

        $this->get(route('superadmin.church-applications.show', $application))
            ->assertRedirect(route('login'));

        $this->post(route('superadmin.church-applications.approve', $application))
            ->assertRedirect(route('login'));

        $this->post(route('superadmin.church-applications.reject', $application), [
            'admin_note' => 'Nope.',
        ])->assertRedirect(route('login'));
    }

    public function test_nonexistent_application_show_returns_404(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/superadmin/church-applications/999999')
            ->assertNotFound();
    }

    public function test_church_application_is_not_tenant_scoped(): void
    {
        $this->assertFalse(
            in_array(BelongsToChurch::class, class_uses_recursive(ChurchApplication::class), true),
            'ChurchApplication must not use BelongsToChurch'
        );

        $second = $this->createChurch([
            'slug' => 'regpanel-second',
            'name' => 'Registration Panel Second',
            'status' => 'active',
        ]);

        TenantContext::set($second);
        $this->assertTrue(TenantContext::enforced());

        $application = $this->pendingApplication([
            'requested_name' => 'Visible Under Other Tenant',
            'contact_email' => 'tenant-scope@example.com',
        ]);

        $this->assertSame(
            1,
            ChurchApplication::query()->whereKey($application->getKey())->count(),
            'Application must remain queryable while TenantContext is set to another church'
        );

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.church-applications.index'))
            ->assertOk()
            ->assertSee('Visible Under Other Tenant', false);

        TenantContext::clear();
        auth()->logout();

        config([
            'tenancy.enabled' => true,
            'tenancy.base_domain' => 'localhost',
            'tenancy.console_host' => 'admin.localhost',
            'app.url' => 'http://localhost',
        ]);

        $tenantChurch = $this->createChurch([
            'slug' => 'regpanel-host',
            'name' => 'Registration Host Church',
            'status' => 'active',
        ]);

        $response = $this->post(ChurchHost::url($tenantChurch, '/register-church'), $this->validPayload([
            'contact_email' => 'from-subdomain@example.com',
            'requested_name' => 'Submitted On Subdomain',
        ]));

        $response->assertRedirect();
        $this->assertSame(
            '/register-church/thanks',
            parse_url((string) $response->headers->get('Location'), PHP_URL_PATH)
        );

        $this->assertDatabaseHas('church_applications', [
            'contact_email' => 'from-subdomain@example.com',
            'requested_name' => 'Submitted On Subdomain',
            'status' => ChurchApplication::STATUS_UNVERIFIED,
        ]);
    }

    public function test_approve_create_church_prefill_renders_query_values(): void
    {
        $super = $this->superadmin();
        $application = $this->pendingApplication([
            'requested_name' => 'Prefill Saint Mary',
            'requested_short_name' => 'St Mary Prefill',
            'place_district' => 'Heliopolis',
            'place_governorate' => 'Cairo',
            'place_country_code' => 'EG',
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.church-applications.approve', $application))
            ->assertRedirect();

        $application->refresh();
        $query = array_filter([
            'name' => $application->requested_name,
            'short_name' => $application->requested_short_name,
            'place_district' => $application->place_district,
            'place_governorate' => $application->place_governorate,
            'place_country_code' => $application->place_country_code,
        ]);

        $html = $this->actingAs($super)
            ->get(route('superadmin.churches.create', $query))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="name"[^>]*value="'.preg_quote($application->requested_name, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="short_name"[^>]*value="'.preg_quote($application->requested_short_name, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="place_district"[^>]*value="'.preg_quote($application->place_district, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="place_governorate"[^>]*value="'.preg_quote($application->place_governorate, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/value="EG"[^>]*selected|selected[^>]*value="EG"/',
            $html
        );
    }

    public function test_arabic_locale_renders_labels_without_missing_keys(): void
    {
        app()->setLocale('ar');

        $create = $this->get(route('church-registration'))->assertOk()->getContent();
        $this->assertStringContainsString('تسجيل كنيستك', $create);
        $this->assertStringContainsString('اسم الكنيسة', $create);
        $this->assertStringContainsString('اسم جهة الاتصال', $create);
        $this->assertStringContainsString('لن يُقبل الطلب تلقائيًا', $create);
        $this->assertStringContainsString('الاسم المختصر', $create);
        $this->assertStringNotContainsString('church_applications.public_title', $create);
        $this->assertStringNotContainsString('church_applications.requested_name', $create);

        $thanks = $this->get(route('church-registration.thanks'))->assertOk()->getContent();
        $this->assertStringContainsString('استلمنا طلبك', $thanks);
        $this->assertStringContainsString('تحقق من بريدك الإلكتروني', $thanks);

        $application = $this->pendingApplication(['requested_name' => 'كنيسة الاختبار']);
        $show = $this->actingAs($this->superadmin())
            ->get(route('superadmin.church-applications.show', $application))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('اسم الكنيسة', $show);
        $this->assertStringContainsString('قيد المراجعة', $show);
        $this->assertStringContainsString('كنيسة الاختبار', $show);
        $this->assertStringContainsString(__('countries.EG'), $show);
        $this->assertStringNotContainsString('church_applications.status_pending_review', $show);
        $this->assertStringNotContainsString('church_applications.admin_title', $show);
    }

    public function test_guest_validation_uses_localized_field_messages(): void
    {
        $this->from(route('church-registration'))
            ->post(route('church-registration.store'), $this->validPayload([
                'requested_name' => '',
                'contact_email' => 'not-an-email',
            ]))
            ->assertRedirect(route('church-registration'))
            ->assertSessionHasErrors(['requested_name', 'contact_email']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            __('church_applications.requested_name'),
            $errors->first('requested_name')
        );
        $this->assertSame(
            __('church_applications.validation_email'),
            $errors->first('contact_email')
        );
    }

    public function test_duplicate_contact_email_applications_are_allowed(): void
    {
        // No unique index on contact_email / requested_name — intentional today.
        // Product question: should duplicates be blocked? Surface in PR; do not add unasked.
        $email = 'duplicate-allowed@example.com';

        $this->post(route('church-registration.store'), $this->validPayload([
            'contact_email' => $email,
            'requested_name' => 'First Duplicate Church',
        ]))->assertRedirect(route('church-registration.thanks'));

        $this->post(route('church-registration.store'), $this->validPayload([
            'contact_email' => $email,
            'requested_name' => 'Second Duplicate Church',
        ]))->assertRedirect(route('church-registration.thanks'));

        $this->assertSame(
            2,
            ChurchApplication::query()->where('contact_email', $email)->count()
        );
    }
}
