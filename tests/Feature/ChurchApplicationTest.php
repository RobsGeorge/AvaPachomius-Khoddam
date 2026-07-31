<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchApplication;
use App\Models\ChurchUser;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

class ChurchApplicationTest extends EventModuleTestCase
{
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
            'status' => ChurchApplication::STATUS_PENDING,
        ]);

        $row = ChurchApplication::query()->first();
        $this->assertNotNull($row->submitted_at);
    }

    public function test_honeypot_filled_submission_creates_no_row(): void
    {
        $this->post(route('church-registration.store'), $this->validPayload([
            'website' => 'https://spam.example',
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
            ->assertSee($application->contact_email, false);
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
    }
}
