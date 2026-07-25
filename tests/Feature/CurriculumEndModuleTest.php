<?php

namespace Tests\Feature;

use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Tests\Support\EventModuleTestCase;

class CurriculumEndModuleTest extends EventModuleTestCase
{
    public function test_staff_can_end_module_and_open_feedback_via_post(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'end-module-instructor@example.com']);
        $course = $this->createCourse(['title' => 'End Module Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $module = Module::create(['title' => 'Week 1', 'description' => 'Intro']);
        $course->modules()->attach($module->module_id, [
            'status' => 'active',
            'feedback_open' => false,
        ]);

        $this->actingAs($instructor)
            ->post(route('curriculum.end-module', [$course->course_id, $module->module_id]))
            ->assertRedirect(route('curriculum.admin', $course->course_id))
            ->assertSessionHas('success');

        $pivot = DB::table('course_module')
            ->where('course_id', $course->course_id)
            ->where('module_id', $module->module_id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertSame('ended', $pivot->status);
        $this->assertTrue((bool) $pivot->feedback_open);
        $this->assertNotNull($pivot->ended_at);
        $this->assertSame((int) $instructor->user_id, (int) $pivot->ended_by_user_id);
    }

    public function test_end_module_with_put_method_spoof_does_not_match_route(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'end-module-spoof@example.com']);
        $course = $this->createCourse(['title' => 'Spoof End Module Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $module = Module::create(['title' => 'Week 2', 'description' => 'More']);
        $course->modules()->attach($module->module_id, [
            'status' => 'active',
            'feedback_open' => false,
        ]);

        $this->actingAs($instructor)
            ->post(route('curriculum.end-module', [$course->course_id, $module->module_id]), [
                '_method' => 'PUT',
            ])
            ->assertStatus(405);

        $pivot = DB::table('course_module')
            ->where('course_id', $course->course_id)
            ->where('module_id', $module->module_id)
            ->first();

        $this->assertFalse((bool) $pivot->feedback_open);
        $this->assertSame('active', $pivot->status);
    }

    public function test_curriculum_admin_end_module_control_is_not_nested_in_put_form(): void
    {
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'end-module-admin-ui@example.com']);
        $course = $this->createCourse(['title' => 'UI End Module Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $module = Module::create(['title' => 'Week 3', 'description' => 'UI']);
        $course->modules()->attach($module->module_id, [
            'status' => 'active',
            'feedback_open' => false,
        ]);

        $html = $this->actingAs($instructor)
            ->get(route('curriculum.admin', $course->course_id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('formaction="', $html);

        $endAction = route('curriculum.end-module', [$course->course_id, $module->module_id], false);
        $this->assertMatchesRegularExpression(
            '/<form[^>]*method="POST"[^>]*action="[^"]*'.preg_quote($endAction, '/').'"[^>]*>\s*<input[^>]*name="_token"/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="_method"[^>]*value="PUT".{0,800}'.preg_quote($endAction, '/').'/s',
            $html
        );
    }
}
