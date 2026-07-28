<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Models\MediaAsset;
use App\Models\Module;
use App\Models\Session;
use App\Services\ChurchStorageQuotaService;
use App\Services\CourseContextService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

class CurriculumMediaTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('curriculum');
        config(['curriculum.disk' => 'curriculum']);
    }

    public function test_instructor_can_upload_hosted_slides_and_student_can_download(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->put(route('lectures.update', $lecture->lecture_id), [
                'session_id' => $session->session_id,
                'title' => $lecture->title,
                'slides_source' => 'hosted_file',
                'slides_file' => UploadedFile::fake()->create('slides.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('lectures.edit', $lecture->lecture_id))
            ->assertSessionHas('success');

        $lecture->refresh();
        $this->assertNotNull($lecture->slides_media_id);
        $this->assertNull($lecture->slides_link);

        $media = MediaAsset::findOrFail($lecture->slides_media_id);
        Storage::disk('curriculum')->assertExists($media->path);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('curriculum.media.download', $media->media_id))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_guest_and_unenrolled_user_cannot_download_curriculum_media(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();
        $outsider = $this->createUser(['email' => 'curriculum-outsider@example.com']);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->put(route('lectures.update', $lecture->lecture_id), [
                'session_id' => $session->session_id,
                'title' => $lecture->title,
                'slides_source' => 'hosted_file',
                'slides_file' => UploadedFile::fake()->create('private.pdf', 80, 'application/pdf'),
            ])
            ->assertRedirect();

        $media = MediaAsset::firstOrFail();

        auth()->logout();

        $this->get(route('curriculum.media.download', $media->media_id))
            ->assertRedirect();

        $this->actingAs($outsider)
            ->get(route('curriculum.media.download', $media->media_id))
            ->assertForbidden();
    }

    public function test_instructor_can_add_hosted_additional_material(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('lecture-materials.store'), [
                'lecture_id' => $lecture->lecture_id,
                'title' => 'Handout',
                'source_type' => LectureMaterial::SOURCE_HOSTED_FILE,
                'file' => UploadedFile::fake()->create('handout.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect(route('lectures.edit', $lecture->lecture_id))
            ->assertSessionHas('success');

        $material = LectureMaterial::where('lecture_id', $lecture->lecture_id)->first();
        $this->assertNotNull($material);
        $this->assertTrue($material->isHostedFile());
        $this->assertNotNull($material->media_id);
    }

    public function test_upload_rejected_when_church_storage_quota_exceeded(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();

        $church = Church::main();
        app(ChurchStorageQuotaService::class)->setQuotaBytes($church, 1024);
        $church->update([
            'settings' => array_merge($church->settings ?? [], ['storage_used_bytes' => 900]),
        ]);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->put(route('lectures.update', $lecture->lecture_id), [
                'session_id' => $session->session_id,
                'title' => $lecture->title,
                'slides_source' => 'hosted_file',
                'slides_file' => UploadedFile::fake()->create('too-big.pdf', 300, 'application/pdf'),
            ])
            ->assertSessionHasErrors('slides_file');

        $this->assertDatabaseMissing('media_assets', ['original_filename' => 'too-big.pdf']);
    }

    public function test_curriculum_show_displays_hosted_slides_download_link(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->put(route('lectures.update', $lecture->lecture_id), [
                'session_id' => $session->session_id,
                'title' => 'Slides lecture',
                'slides_source' => 'hosted_file',
                'slides_file' => UploadedFile::fake()->create('week1.pdf', 40, 'application/pdf'),
            ]);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $media = MediaAsset::firstOrFail();

        $this->actingAs($student)
            ->get(route('curriculum.show', $course->course_id))
            ->assertOk()
            ->assertSee(route('curriculum.media.download', $media->media_id, false))
            ->assertSee('Slides lecture');
    }

    public function test_deleting_hosted_material_removes_media_asset(): void
    {
        [$course, $instructor, $student, $lecture, $session] = $this->curriculumFixture();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('lecture-materials.store'), [
                'lecture_id' => $lecture->lecture_id,
                'title' => 'Temp',
                'source_type' => LectureMaterial::SOURCE_HOSTED_FILE,
                'file' => UploadedFile::fake()->create('temp.pdf', 30, 'application/pdf'),
            ]);

        $material = LectureMaterial::firstOrFail();
        $mediaId = $material->media_id;
        $path = MediaAsset::find($mediaId)->path;

        $this->actingAs($instructor)
            ->delete(route('lecture-materials.destroy', $material->material_id))
            ->assertRedirect();

        $this->assertDatabaseMissing('lecture_materials', ['material_id' => $material->material_id]);
        $this->assertSoftDeleted('media_assets', ['media_id' => $mediaId]);
        Storage::disk('curriculum')->assertMissing($path);
    }

  /** @return array{0:\App\Models\Course,1:\App\Models\User,2:\App\Models\User,3:Lecture,4:Session} */
    private function curriculumFixture(): array
    {
        $course = $this->createCourse(['title' => 'Media Course', 'status' => 'active']);
        $instructorRole = $this->courseRoleWithPermissions($course, 'media-instructor', [
            'curriculum.manage',
            'curriculum.view',
        ]);
        $studentRole = $this->courseRoleWithPermissions($course, 'media-student', [
            'curriculum.view',
        ]);
        $instructor = $this->createUser(['email' => 'media-instructor@example.com']);
        $student = $this->createUser(['email' => 'media-student@example.com']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Module 1', 'description' => 'Test']);
        $course->modules()->attach($module->module_id);

        $session = Session::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'session_title' => 'Session 1',
            'week_number' => 1,
            'session_date' => now()->toDateString(),
        ]);

        $lecture = Lecture::create([
            'module_id' => $module->module_id,
            'session_id' => $session->session_id,
            'title' => 'Lecture 1',
            'week_number' => 1,
        ]);

        return [$course, $instructor, $student, $lecture, $session];
    }
}
