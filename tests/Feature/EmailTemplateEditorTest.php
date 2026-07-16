<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseApplicationReviewTemplate;
use App\Models\CourseGraduationEmailTemplate;
use App\Models\EmailTemplateMeta;
use App\Services\EmailLocaleResolver;
use App\Services\EmailTemplateCatalog;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class EmailTemplateEditorTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('course_graduation_email_templates')
            || ! Schema::hasTable('course_application_review_templates')) {
            $this->markTestSkipped('Email template tables not ready.');
        }
    }

    public function test_superadmin_can_open_course_email_templates_hub(): void
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'email-tpl-super@example.com']);

        $this->actingAs($super)
            ->get(route('courses.email-templates.index', $course))
            ->assertOk()
            ->assertSee(__('email_templates.hub_title'), false)
            ->assertSee(__('email_templates.edit_language'), false)
            ->assertSee(__('email_templates.preview'), false);
    }

    public function test_regular_user_cannot_open_course_email_templates_hub(): void
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $user = $this->createUser(['email' => 'email-tpl-denied@example.com']);

        $this->actingAs($user)
            ->get(route('courses.email-templates.index', $course))
            ->assertForbidden();
    }

    public function test_superadmin_can_update_course_template_and_default_locale(): void
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'email-tpl-save@example.com']);

        app(EmailTemplateCatalog::class)->ensureCourseDefaults($course);

        $template = CourseGraduationEmailTemplate::query()
            ->where('course_id', $course->course_id)
            ->where('template_key', CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED)
            ->where('locale', 'en')
            ->first()
            ?? CourseGraduationEmailTemplate::query()
                ->whereNull('course_id')
                ->where('template_key', CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED)
                ->where('locale', 'en')
                ->first();

        $this->assertNotNull($template);

        $this->actingAs($super)
            ->put(route('courses.email-templates.update', $course), [
                'family' => EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
                'edit_locale' => 'en',
                'templates' => [
                    $template->id => [
                        'subject' => 'Graduation ready',
                        'body_html' => '<p>Hello {{student_name}}</p>',
                    ],
                ],
                'defaults' => [
                    CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED => 'en',
                ],
            ])
            ->assertRedirect();

        $saved = CourseGraduationEmailTemplate::query()
            ->where('course_id', $course->course_id)
            ->where('template_key', CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED)
            ->where('locale', 'en')
            ->first();

        $this->assertNotNull($saved);
        $this->assertSame('Graduation ready', $saved->subject);
        $this->assertStringContainsString('Hello {{student_name}}', $saved->body_html);

        if (EmailTemplateMeta::tableReady()) {
            $this->assertSame(
                'en',
                app(EmailTemplateCatalog::class)->defaultLocale(
                    EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
                    CourseGraduationEmailTemplate::KEY_GRADUATION_ANNOUNCED,
                    $course->course_id
                )
            );
        }
    }

    public function test_preview_returns_substituted_html_without_raw_tokens(): void
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $super = $this->createUser(['is_superadmin' => true, 'email' => 'email-tpl-preview@example.com']);

        $response = $this->actingAs($super)
            ->postJson(route('courses.email-templates.preview', $course), [
                'family' => EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
                'subject' => 'Congrats {{student_name}}',
                'body_html' => '<p>Dear {{student_name}}, course {{course_title}}</p>',
            ])
            ->assertOk();

        $this->assertSame('Congrats Example Student', $response->json('subject'));
        $this->assertStringContainsString('Example Student', $response->json('body_html'));
        $this->assertStringNotContainsString('{{student_name}}', $response->json('body_html'));
    }

    public function test_locale_resolver_prefers_user_communication_locale(): void
    {
        if (! EmailTemplateCatalog::userCommunicationLocaleColumnReady()) {
            $this->markTestSkipped('communication_locale column missing.');
        }

        $user = $this->createUser(['email' => 'email-tpl-locale@example.com', 'communication_locale' => 'en']);
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $catalog = app(EmailTemplateCatalog::class);
        $catalog->ensureCourseDefaults($course);
        $catalog->setDefaultLocale(
            EmailTemplateCatalog::FAMILY_COURSE_APPLICATION,
            CourseApplicationReviewTemplate::KEY_RECEIVED,
            'ar',
            $course->course_id
        );

        $locale = app(EmailLocaleResolver::class)->forRecipient(
            $user,
            EmailTemplateCatalog::FAMILY_COURSE_APPLICATION,
            CourseApplicationReviewTemplate::KEY_RECEIVED,
            $course->course_id
        );

        $this->assertSame('en', $locale);
    }

    public function test_locale_resolver_falls_back_to_template_default(): void
    {
        if (! EmailTemplateMeta::tableReady()) {
            $this->markTestSkipped('email_template_meta table missing.');
        }

        $user = $this->createUser(['email' => 'email-tpl-fallback@example.com', 'communication_locale' => null]);
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $catalog = app(EmailTemplateCatalog::class);
        $catalog->ensureCourseDefaults($course);
        $catalog->setDefaultLocale(
            EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
            CourseGraduationEmailTemplate::KEY_CERTIFICATE_ISSUED,
            'en',
            $course->course_id
        );

        $locale = app(EmailLocaleResolver::class)->forRecipient(
            $user,
            EmailTemplateCatalog::FAMILY_COURSE_GRADUATION,
            CourseGraduationEmailTemplate::KEY_CERTIFICATE_ISSUED,
            $course->course_id
        );

        $this->assertSame('en', $locale);
    }

    public function test_user_can_save_communication_locale_in_notification_settings(): void
    {
        if (! EmailTemplateCatalog::userCommunicationLocaleColumnReady()) {
            $this->markTestSkipped('communication_locale column missing.');
        }

        $user = $this->createUser(['email' => 'email-tpl-prefs@example.com']);

        $this->actingAs($user)
            ->put(route('notifications.settings.update'), [
                'communication_locale' => 'en',
                'preferences' => [],
            ])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->communication_locale);

        $this->actingAs($user)
            ->put(route('notifications.settings.update'), [
                'communication_locale' => '',
                'preferences' => [],
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->communication_locale);
    }
}
