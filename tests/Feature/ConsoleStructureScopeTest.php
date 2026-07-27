<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchService;
use App\Models\Course;
use Tests\Support\EventModuleTestCase;

class ConsoleStructureScopeTest extends EventModuleTestCase
{
    public function test_console_service_create_requires_church(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
        ]);

        $church = Church::main();
        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'console-svc-scope@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('admin.services.store'), [
                'title' => 'Missing Church Service',
            ])
            ->assertSessionHasErrors('church_id');

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('admin.services.store'), [
                'title' => 'Scoped Console Service',
                'church_id' => $church->church_id,
            ])
            ->assertRedirect(route('admin.services.index'));

        $service = ChurchService::query()->withoutTenancy()->where('title', 'Scoped Console Service')->first();
        $this->assertNotNull($service);
        $this->assertSame((int) $church->church_id, (int) $service->church_id);
    }

    public function test_console_course_create_requires_church_and_matching_service(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
        ]);

        $church = Church::main();
        $otherChurch = Church::query()->where('church_id', '!=', $church->church_id)->first();
        if (! $otherChurch) {
            $otherChurch = Church::query()->create([
                'slug' => 'console-scope-other',
                'name' => 'Other Console Church',
                'status' => 'active',
            ]);
        }

        $service = $this->createService(['title' => 'Console Scope Service']);
        $service->church_id = $church->church_id;
        $service->save();

        $otherService = $this->createService(['title' => 'Other Church Service']);
        $otherService->church_id = $otherChurch->church_id;
        $otherService->save();

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'console-course-scope@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('superadmin.courses.store'), [
                'title' => 'No Church Course',
                'description' => 'Test',
                'year' => 2026,
                'default_session_start_time' => '09:00',
                'service_id' => $service->service_id,
            ])
            ->assertSessionHasErrors('church_id');

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('superadmin.courses.store'), [
                'title' => 'Mismatch Course',
                'description' => 'Test',
                'year' => 2026,
                'default_session_start_time' => '09:00',
                'church_id' => $church->church_id,
                'service_id' => $otherService->service_id,
            ])
            ->assertSessionHasErrors('service_id');

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->post(route('superadmin.courses.store'), [
                'title' => 'Scoped Console Course',
                'description' => 'Test',
                'year' => 2026,
                'default_session_start_time' => '09:00',
                'church_id' => $church->church_id,
                'service_id' => $service->service_id,
            ])
            ->assertRedirect(route('superadmin.courses'));

        $course = Course::query()->withoutTenancy()->where('title', 'Scoped Console Course')->first();
        $this->assertNotNull($course);
        $this->assertSame((int) $church->church_id, (int) $course->church_id);
        $this->assertSame((int) $service->service_id, (int) $course->service_id);
    }

    public function test_console_services_index_groups_by_church(): void
    {
        config([
            'tenancy.enabled' => true,
            'tenancy.console_host' => 'admin.test',
            'tenancy.base_domain' => 'test',
        ]);

        $church = Church::main();
        $service = $this->createService(['title' => 'Grouped Console Service']);
        $service->church_id = $church->church_id;
        $service->save();

        $super = $this->createUser([
            'is_superadmin' => true,
            'email' => 'console-svc-group@example.com',
            'registration_completed' => true,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'admin.test'])
            ->actingAs($super)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee($church->name, false)
            ->assertSee($service->localizedTitle(), false);
    }
}
