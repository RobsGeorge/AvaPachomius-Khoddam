<?php

namespace Tests\Feature\Tenancy;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\UserNotification;
use App\Services\AnnouncementService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * Regression cover for the announcement 500 + missing student notifications.
 *
 * Root cause: `announcements` (and sibling comms tables) joined
 * config('tenancy.tenant_tables') after the church_id add-column migrations had
 * already run on staging/production, so the column was missing there while
 * BelongsToChurch still tried to stamp it on every insert. Fresh test databases
 * always have the column, so we reproduce the drifted schema explicitly.
 */
class AnnouncementChurchIdDriftTest extends EventModuleTestCase
{
    private const MIGRATION = 'database/migrations/2026_08_03_000001_reconcile_tenant_church_id.php';

    protected function tearDown(): void
    {
        TenantContext::clear();
        config(['tenancy.enabled' => false]);
        parent::tearDown();
    }

    /** Drop church_id from announcements the way a long-lived un-migrated DB has it. */
    private function simulateDrift(): void
    {
        // SQLite refuses DROP COLUMN while an index still references the column,
        // so drop the index first. Use the column-array form so Laravel resolves
        // the index name per driver (do not hardcode announcements_church_id_index).
        Schema::table('announcements', function ($t) {
            $t->dropIndex(['church_id']);
            $t->dropColumn('church_id');
        });
        $this->assertFalse(Schema::hasColumn('announcements', 'church_id'));
    }

    private function runReconcile(): void
    {
        (require base_path(self::MIGRATION))->up();
    }

    public function test_reconcile_migration_heals_drifted_announcements_church_id(): void
    {
        // Faithful production state: MULTI_TENANT=false, but ResolveTenant still binds
        // Tenant Zero, so BelongsToChurch stamps church_id on every insert.
        config(['tenancy.enabled' => false]);
        $main = Church::main();
        TenantContext::set($main);

        $author = $this->createUser(['email' => 'drift-author@example.com']);
        $payload = [
            'title' => 'Drift check',
            'body' => 'body',
            'target_mode' => Announcement::TARGET_COURSE,
            'course_id' => null,
            'channels' => [],
        ];

        // With the column present the stamp lands: BelongsToChurch writes church_id
        // on every insert. That is exactly why the drifted schema 500s in production
        // (MySQL: "Unknown column 'church_id'") -- the stamp targets a column that a
        // long-lived DB never received.
        $probe = app(AnnouncementService::class)->createDraft($author, $payload);
        $this->assertSame((int) $main->church_id, (int) $probe->church_id);

        // Reproduce that drifted schema, then let the reconcile migration heal it.
        $this->simulateDrift();
        $this->runReconcile();
        $this->assertTrue(Schema::hasColumn('announcements', 'church_id'));

        // Creation works again and still stamps Tenant Zero.
        $announcement = app(AnnouncementService::class)->createDraft($author, $payload);
        $this->assertSame((int) $main->church_id, (int) $announcement->church_id);
    }

    public function test_reconcile_backfills_null_church_id_so_enforced_tenancy_sees_legacy_rows(): void
    {
        $author = $this->createUser(['email' => 'legacy-author@example.com']);

        // A legacy row left with NULL church_id (created before the column/backfill existed).
        DB::table('announcements')->insert([
            'created_by_user_id' => $author->user_id,
            'title' => 'Legacy announcement',
            'body' => 'body',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => json_encode([]),
            'status' => Announcement::STATUS_PUBLISHED,
            'church_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runReconcile();

        $mainId = (int) Church::main()->church_id;
        $this->assertSame(0, DB::table('announcements')->whereNull('church_id')->count());
        $this->assertSame(
            $mainId,
            (int) DB::table('announcements')->where('title', 'Legacy announcement')->value('church_id')
        );

        // Bug 2 angle: under enforced tenancy the backfilled row is now visible
        // (a NULL-church_id row would have been filtered out by the global scope).
        config(['tenancy.enabled' => true]);
        TenantContext::set(Church::main());
        $this->assertNotNull(Announcement::where('title', 'Legacy announcement')->first());
    }

    public function test_published_announcement_notifies_students_under_enforced_tenancy(): void
    {
        config(['tenancy.enabled' => true]);
        TenantContext::set(Church::main());

        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'notify-instr@example.com']);
        $student = $this->createUser(['email' => 'notify-stud@example.com', 'profile_photo' => 'p.jpg']);
        $course = $this->createCourse(['title' => 'Notify Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $service = app(AnnouncementService::class);
        $announcement = $service->createDraft($instructor, [
            'title' => 'Schedule update',
            'body' => 'Please review.',
            'target_mode' => Announcement::TARGET_COURSE,
            'course_id' => $course->course_id,
            'channels' => [Announcement::CHANNEL_HOMEPAGE => true],
        ]);
        $service->publish($announcement, $instructor);

        $this->assertDatabaseHas('announcement_deliveries', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        $notifications = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', UserNotification::TYPE_ADMIN_ANNOUNCEMENT)
            ->count();

        $this->assertGreaterThan(0, $notifications, 'Student did not receive an announcement notification.');
    }
}
