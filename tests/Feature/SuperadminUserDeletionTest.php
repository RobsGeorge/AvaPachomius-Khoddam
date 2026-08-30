<?php

namespace Tests\Feature;

use App\Mail\AccountDeletedMail;
use App\Models\ActivityLog;
use App\Models\ChurchUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class SuperadminUserDeletionTest extends EventModuleTestCase
{
    private function superadmin(): User
    {
        return $this->createUser([
            'is_superadmin' => true,
            'email' => 'users-delete-super@example.com',
            'first_name' => 'Super',
            'second_name' => 'Admin',
            'third_name' => 'Op',
        ]);
    }

    public function test_guest_and_student_cannot_open_the_page(): void
    {
        $this->get(route('superadmin.users.index'))->assertRedirect();

        $student = $this->createUser(['email' => 'users-delete-student@example.com']);
        $this->actingAs($student)
            ->get(route('superadmin.users.index'))
            ->assertForbidden();
    }

    public function test_superadmin_sees_the_page_on_the_console(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertSee(__('user_deletion.title'))
            ->assertSee(__('user_deletion.search_hint'));
    }

    public function test_superadmin_hub_links_to_user_deletion(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('user_deletion.nav'));
    }

    public function test_search_matches_name_church_and_service(): void
    {
        $admin = $this->superadmin();
        $church = $this->createChurch(['name' => 'St Mark Deletion Lab', 'slug' => 'st-mark-del']);
        $service = $this->createService([
            'title' => 'Servants Prep Delete',
            'title_en' => 'Servants Prep Delete',
            'church_id' => $church->church_id,
        ]);

        $mina = $this->createUser([
            'first_name' => 'Mina',
            'second_name' => 'Fawzy',
            'third_name' => 'Kamel',
            'email' => 'mina.delete@example.com',
        ]);
        $other = $this->createUser([
            'first_name' => 'George',
            'second_name' => 'Other',
            'third_name' => 'Person',
            'email' => 'george.delete@example.com',
        ]);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $mina->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $this->assignServiceRole($mina, $service, allowCross: true);

        $this->actingAs($admin)
            ->get(route('superadmin.users.index', ['name' => 'Mina']))
            ->assertOk()
            ->assertSee('mina.delete@example.com')
            ->assertDontSee('george.delete@example.com');

        $this->actingAs($admin)
            ->get(route('superadmin.users.index', ['church_id' => $church->church_id]))
            ->assertOk()
            ->assertSee('mina.delete@example.com')
            ->assertDontSee('george.delete@example.com');

        $this->actingAs($admin)
            ->get(route('superadmin.users.index', ['service_id' => $service->service_id]))
            ->assertOk()
            ->assertSee('mina.delete@example.com')
            ->assertDontSee('george.delete@example.com');

        $this->assertNotNull($other->fresh());
    }

    public function test_soft_delete_hides_login_and_writes_audit(): void
    {
        Mail::fake();
        $admin = $this->superadmin();
        $target = $this->createUser([
            'email' => 'soft-target@example.com',
            'first_name' => 'Soft',
            'second_name' => 'Target',
        ]);

        $this->actingAs($admin)
            ->post(route('superadmin.users.soft-delete', $target->user_id), [
                'notify_email' => 0,
                'notify_whatsapp' => 0,
            ])
            ->assertRedirect(route('superadmin.users.index', [
                'name' => 'soft-target@example.com',
                'include_deleted' => 1,
            ]));

        $this->assertSoftDeleted('user', ['user_id' => $target->user_id]);
        $this->assertNull(User::query()->find($target->user_id));
        $this->assertFalse(Auth::attempt(['email' => 'soft-target@example.com', 'password' => 'password']));

        Mail::assertNothingSent();

        $this->assertTrue(
            ActivityLog::query()
                ->where('route_name', 'superadmin.users.soft-delete')
                ->where('user_id', $admin->user_id)
                ->exists()
        );
    }

    public function test_soft_delete_sends_email_and_whatsapp_when_requested(): void
    {
        Mail::fake();
        Config::set('notifications.whatsapp.api_url', 'https://graph.example.test/v1');
        Config::set('notifications.whatsapp.api_token', 'test-token');
        Config::set('notifications.whatsapp.phone_number_id', '123456');
        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.DEL']]], 200),
        ]);

        $admin = $this->superadmin();
        $target = $this->createUser([
            'email' => 'notify-target@example.com',
            'mobile_number' => '01099990001',
            'first_name' => 'Notify',
            'second_name' => 'Target',
        ]);

        $this->actingAs($admin)
            ->post(route('superadmin.users.soft-delete', $target->user_id), [
                'notify_email' => 1,
                'notify_whatsapp' => 1,
            ])
            ->assertRedirect();

        Mail::assertSent(
            AccountDeletedMail::class,
            fn (AccountDeletedMail $mail) => $mail->hasTo('notify-target@example.com') && $mail->permanent === false
        );

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '123456/messages')
                && ($request['messaging_product'] ?? null) === 'whatsapp';
        });
    }

    public function test_hard_delete_requires_warning_confirmation(): void
    {
        $admin = $this->superadmin();
        $target = $this->createUser(['email' => 'hard-guard@example.com']);

        $this->actingAs($admin)
            ->from(route('superadmin.users.confirm', $target->user_id))
            ->post(route('superadmin.users.hard-delete', $target->user_id), [
                'confirmation' => 'wrong@example.com',
                'acknowledge' => 1,
            ])
            ->assertRedirect(route('superadmin.users.confirm', $target->user_id))
            ->assertSessionHasErrors('confirmation');

        $this->assertNotNull(User::query()->find($target->user_id));
    }

    public function test_hard_delete_removes_the_row_after_confirmation(): void
    {
        Mail::fake();
        $admin = $this->superadmin();
        $target = $this->createUser([
            'email' => 'hard-target@example.com',
            'first_name' => 'Hard',
            'second_name' => 'Target',
        ]);

        $this->actingAs($admin)
            ->get(route('superadmin.users.confirm', $target->user_id))
            ->assertOk()
            ->assertSee(__('user_deletion.hard_warning'))
            ->assertSee('hard-target@example.com');

        $this->actingAs($admin)
            ->post(route('superadmin.users.hard-delete', $target->user_id), [
                'confirmation' => 'hard-target@example.com',
                'acknowledge' => 1,
                'notify_email' => 1,
            ])
            ->assertRedirect(route('superadmin.users.index'));

        $this->assertDatabaseMissing('user', ['user_id' => $target->user_id]);
        Mail::assertSent(AccountDeletedMail::class);

        $this->assertTrue(
            ActivityLog::query()
                ->where('route_name', 'superadmin.users.hard-delete')
                ->where('user_id', $admin->user_id)
                ->exists()
        );
    }

    public function test_cannot_delete_self_or_last_superadmin(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->post(route('superadmin.users.soft-delete', $admin->user_id))
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)
            ->post(route('superadmin.users.hard-delete', $admin->user_id), [
                'confirmation' => $admin->email,
                'acknowledge' => 1,
            ])
            ->assertSessionHasErrors('user');

        $this->assertNotNull($admin->fresh());
    }
}
