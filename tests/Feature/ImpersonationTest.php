<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImpersonationService;
use App\Services\RolePreviewService;
use Illuminate\Validation\ValidationException;
use Tests\Support\EventModuleTestCase;

class ImpersonationTest extends EventModuleTestCase
{
    public function test_superadmin_can_impersonate_registered_user(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'impersonate-super@example.com',
            'registration_completed' => true,
        ]);
        $target = $this->createUser([
            'email' => 'impersonate-target@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super)
            ->post(route('superadmin.impersonate'), ['user_id' => $target->user_id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($target->user_id, auth()->id());
        $this->assertSame($super->user_id, session(ImpersonationService::SESSION_KEY));
    }

    public function test_cannot_impersonate_while_role_preview_is_active(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'impersonate-preview-super@example.com',
            'registration_completed' => true,
        ]);
        $target = $this->createUser([
            'email' => 'impersonate-preview-target@example.com',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['status' => \App\Models\Course::STATUS_ACTIVE]);
        $role = $this->courseRoleWithPermissions($course, 'student', ['exam.view']);

        RolePreviewService::startCourseRole($super, $course, $role, request());

        $this->actingAs($super)
            ->post(route('superadmin.impersonate'), ['user_id' => $target->user_id])
            ->assertSessionHasErrors('user');
    }

    public function test_cannot_impersonate_self(): void
    {
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'impersonate-self@example.com',
            'registration_completed' => true,
        ]);

        $this->actingAs($super);

        $this->expectException(ValidationException::class);
        ImpersonationService::start($super, $super, request());
    }
}
