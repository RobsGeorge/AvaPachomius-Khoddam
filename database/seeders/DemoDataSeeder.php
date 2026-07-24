<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\ConfessionSlot;
use App\Models\Course;
use App\Models\Event;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\HomeVisit;
use App\Models\MoneyIn;
use App\Models\Person;
use App\Models\Priest;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\ChurchProvisioningService;
use App\Services\CourseRoleAssignmentService;
use App\Services\RoleTemplateService;
use App\Services\ServiceRoleAssignmentService;
use App\Support\Demo\DemoData;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Representative dummy data for a staging / local environment: churches (tenants) each with
 * services, priests, confession slots, courses (instructors + students + sessions +
 * assignments + announcements), home visits, finance records, families, events, and the
 * full role/membership graph — all logged-in-able.
 *
 * Every row is tagged (see App\Support\Demo\DemoData) so `demo:wipe` can remove it exactly.
 * This seeder is pure (no environment guard); the demo:seed command owns the guard.
 */
class DemoDataSeeder extends Seeder
{
    private ChurchProvisioningService $provisioning;

    private RoleTemplateService $roleTemplates;

    private CourseRoleAssignmentService $courseRoles;

    private ServiceRoleAssignmentService $serviceRoles;

    /** @var list<array{church: string, role: string, email: string, password: string}> */
    public array $credentials = [];

    private int $seq = 0;

    public function run(): void
    {
        if (DemoData::exists()) {
            $this->command?->warn('Demo data already present — skipping. Run `php artisan demo:wipe` first to reseed.');

            return;
        }

        $this->provisioning = app(ChurchProvisioningService::class);
        $this->roleTemplates = app(RoleTemplateService::class);
        $this->courseRoles = app(CourseRoleAssignmentService::class);
        $this->serviceRoles = app(ServiceRoleAssignmentService::class);

        // Make the platform prerequisites present (all idempotent).
        Artisan::call('permissions:sync');
        $this->roleTemplates->ensureSystemTemplates();
        $this->roleTemplates->ensureServiceTemplates();
        $this->roleTemplates->ensureChurchTemplates();

        // A platform superadmin (reaches the console host; church-membership exempt).
        $this->makeUser('superadmin', 'Demo', 'Superadmin', 'Platform', 'Superadmin', ['is_superadmin' => true]);

        $this->seedStMark();
        $this->seedStGeorge();

        TenantContext::clear();
    }

    /** A fully-populated flagship church. */
    private function seedStMark(): void
    {
        $label = 'St Mark (demo-stmark)';

        // Admin must exist before provisioning (it is assigned church-admin during create()).
        $admin = $this->makeUser('admin.stmark', 'Mina', 'Boutros', $label, 'Church Admin');
        $church = $this->provisionChurch('stmark', 'St Mark Church (Demo)', $admin);

        DB::transaction(function () use ($church, $label, $admin) {
            TenantContext::set($church);

            $priestRole = $this->churchRole($church, 'priest');
            $servantRole = $this->churchRole($church, 'servant');

            // --- Priests + confession slots ---
            foreach ([['priest1.stmark', 'Abanoub', 'Selwanes'], ['priest2.stmark', 'Bishoy', 'Kamal']] as $i => [$local, $first, $second]) {
                $priestUser = $this->makeUser($local, $first, $second, $label, 'Priest');
                $this->provisioning->addMember($church, $priestUser, $priestRole);
                $priest = Priest::create([
                    'user_id' => $priestUser->user_id,
                    'title' => 'Fr.',
                    'status' => Priest::STATUS_ACTIVE,
                ]);

                ConfessionSlot::create([
                    'priest_id' => $priest->priest_id,
                    'starts_at' => now()->addDays(2 + $i)->setTime(17, 0),
                    'ends_at' => now()->addDays(2 + $i)->setTime(19, 0),
                    'capacity' => 8,
                    'location' => 'Confession Room '.($i + 1),
                    'status' => ConfessionSlot::STATUS_OPEN,
                    'notes' => 'Weekly confession',
                ]);
            }

            // --- Services (departments) ---
            $sundaySchool = $this->makeService('Sunday School', 'مدارس الأحد');
            $youth = $this->makeService('Youth Meeting', 'اجتماع الشباب');

            // Service admin over Sunday School.
            $svcAdmin = $this->makeUser('service-admin.stmark', 'Marina', 'Fawzy', $label, 'Service Admin — Sunday School');
            $this->provisioning->addMember($church, $svcAdmin, $servantRole);
            $this->serviceRoles->assign($svcAdmin, $sundaySchool, $this->serviceRoles->adminRoleFor($sundaySchool), true, true);

            // A couple of plain servants (service members).
            foreach ([['servant1.stmark', 'Verena', 'Adel'], ['servant2.stmark', 'Karas', 'Nabil']] as [$local, $first, $second]) {
                $servant = $this->makeUser($local, $first, $second, $label, 'Servant / Service Member');
                $this->provisioning->addMember($church, $servant, $servantRole);
                $this->serviceRoles->assign($servant, $youth, $this->serviceRoles->memberRoleFor($youth), false, true);
            }

            // --- A course with instructor, students, sessions, assignment, announcement ---
            $course = Course::create([
                'service_id' => $sundaySchool->service_id,
                'title' => 'Foundations of Faith',
                'title_en' => 'Foundations of Faith',
                'title_ar' => 'أساسيات الإيمان',
                'description' => 'Introductory servants-preparation course.',
                'year' => (int) date('Y'),
                'status' => Course::STATUS_ACTIVE,
                'passing_percentage' => 60,
                'min_attendance_percentage' => 75,
            ]);
            $courseRoleSet = $this->roleTemplates->cloneTemplatesIntoCourse($course);

            $instructor = $this->makeUser('instructor.stmark', 'George', 'Rizk', $label, 'Course Instructor');
            $this->provisioning->addMember($church, $instructor, $servantRole);
            $this->serviceRoles->assign($instructor, $sundaySchool, $this->serviceRoles->memberRoleFor($sundaySchool), false, true);
            $this->courseRoles->assignOrUpdate($instructor, $course->course_id, $courseRoleSet['instructor']->role_id, false);

            for ($s = 1; $s <= 5; $s++) {
                $student = $this->makeUser("student{$s}.stmark", 'Student'.$s, 'Sameh', $label, 'Student — Foundations of Faith');
                $this->provisioning->addMember($church, $student, $servantRole);
                $this->serviceRoles->assign($student, $sundaySchool, $this->serviceRoles->memberRoleFor($sundaySchool), false, true);
                $this->courseRoles->assignOrUpdate($student, $course->course_id, $courseRoleSet['student']->role_id, false);
            }

            for ($w = 1; $w <= 4; $w++) {
                Session::create([
                    'course_id' => $course->course_id,
                    'week_number' => $w,
                    'session_title' => "Week {$w} — Lesson {$w}",
                    'session_date' => now()->addWeeks($w)->toDateString(),
                    'session_start_time' => '18:00',
                    'notify_students' => false,
                ]);
            }

            Assignment::create([
                'course_id' => $course->course_id,
                'assignment_name' => 'Reflection Paper',
                'assignment_description' => 'Write a one-page reflection on the first lesson.',
                'total_points' => 10,
                'due_date' => now()->addWeeks(2),
                'instructions' => 'Submit as a PDF before the due date.',
            ]);

            Announcement::create([
                'created_by_user_id' => $instructor->user_id,
                'course_id' => $course->course_id,
                'title' => 'Welcome to Foundations of Faith',
                'body' => 'Our first session starts next week. Please review the syllabus.',
                'target_mode' => Announcement::TARGET_COURSE,
                'channels' => [Announcement::CHANNEL_HOMEPAGE],
                'status' => Announcement::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by_user_id' => $instructor->user_id,
            ]);

            // --- Home visits, finance, families, events ---
            HomeVisit::create([
                'assigned_user_id' => $svcAdmin->user_id,
                'subject_name' => 'Habib Family',
                'address' => '12 Church Street, Cairo',
                'scheduled_at' => now()->addDays(3),
                'duration_min' => 60,
                'status' => HomeVisit::STATUS_SCHEDULED,
                'notes' => 'Pastoral home visit.',
            ]);

            MoneyIn::create([
                'source' => 'Sunday Collection',
                'category' => 'donation',
                'amount_minor' => 500000, // 5,000.00 EGP
                'currency' => 'EGP',
                'fx_rate' => '1',
                'received_at' => now()->toDateString(),
                'notes' => 'Weekly offering.',
            ]);

            $family = Family::create(['name' => 'Habib Family']);
            foreach ([['Nabil', 'father'], ['Mariam', 'mother'], ['Youssef', 'son']] as [$first, $rel]) {
                $person = Person::create([
                    'first_name' => $first,
                    'second_name' => 'Habib',
                    'third_name' => 'Demo',
                    'display_name' => $first.' Habib',
                    'date_of_birth' => '1980-06-01',
                    'gender' => in_array($rel, ['mother'], true) ? 'female' : 'male',
                ]);
                FamilyMember::create([
                    'family_id' => $family->family_id,
                    'person_id' => $person->person_id,
                    'role' => $rel,
                ]);
            }

            Event::create([
                'title' => 'Annual Church Trip',
                'description' => 'A weekend spiritual retreat.',
                'location' => 'St Bishoy Monastery',
                'starts_at' => now()->addDays(20),
                'ends_at' => now()->addDays(21),
                'capacity' => 50,
                'visibility' => 'institution',
                'eligible_roles' => [],
                'status' => Event::STATUS_PUBLISHED,
                'check_in_token' => Event::generateCheckInToken(),
                'created_by_id' => $admin->user_id,
            ]);

            TenantContext::clear();
        });
    }

    /** A lighter second church, to demonstrate multi-tenant isolation. */
    private function seedStGeorge(): void
    {
        $label = 'St George (demo-stgeorge)';

        $admin = $this->makeUser('admin.stgeorge', 'Sarabamoun', 'Ayad', $label, 'Church Admin');
        $church = $this->provisionChurch('stgeorge', 'St George Church (Demo)', $admin);

        DB::transaction(function () use ($church, $label) {
            TenantContext::set($church);

            $priestRole = $this->churchRole($church, 'priest');
            $servantRole = $this->churchRole($church, 'servant');

            $priestUser = $this->makeUser('priest1.stgeorge', 'Tadros', 'Wahba', $label, 'Priest');
            $this->provisioning->addMember($church, $priestUser, $priestRole);
            $priest = Priest::create(['user_id' => $priestUser->user_id, 'title' => 'Fr.', 'status' => Priest::STATUS_ACTIVE]);
            ConfessionSlot::create([
                'priest_id' => $priest->priest_id,
                'starts_at' => now()->addDays(4)->setTime(18, 0),
                'ends_at' => now()->addDays(4)->setTime(20, 0),
                'capacity' => 6,
                'location' => 'Confession Room',
                'status' => ConfessionSlot::STATUS_OPEN,
            ]);

            $service = $this->makeService('Deacons Service', 'خدمة الشمامسة');

            $instructor = $this->makeUser('instructor.stgeorge', 'Beshoy', 'Samir', $label, 'Course Instructor');
            $this->provisioning->addMember($church, $instructor, $servantRole);
            $this->serviceRoles->assign($instructor, $service, $this->serviceRoles->adminRoleFor($service), true, true);

            $course = Course::create([
                'service_id' => $service->service_id,
                'title' => 'Introduction to the Liturgy',
                'title_en' => 'Introduction to the Liturgy',
                'title_ar' => 'مقدمة في القداس',
                'description' => 'A foundational course on the liturgical rites.',
                'year' => (int) date('Y'),
                'status' => Course::STATUS_ACTIVE,
                'passing_percentage' => 60,
                'min_attendance_percentage' => 75,
            ]);
            $courseRoleSet = $this->roleTemplates->cloneTemplatesIntoCourse($course);
            $this->courseRoles->assignOrUpdate($instructor, $course->course_id, $courseRoleSet['instructor']->role_id, false);

            for ($s = 1; $s <= 3; $s++) {
                $student = $this->makeUser("student{$s}.stgeorge", 'Learner'.$s, 'Fouad', $label, 'Student — Introduction to the Liturgy');
                $this->provisioning->addMember($church, $student, $servantRole);
                $this->serviceRoles->assign($student, $service, $this->serviceRoles->memberRoleFor($service), false, true);
                $this->courseRoles->assignOrUpdate($student, $course->course_id, $courseRoleSet['student']->role_id, false);
            }

            HomeVisit::create([
                'assigned_user_id' => $instructor->user_id,
                'subject_name' => 'Guirguis Family',
                'address' => '5 Nile Ave, Giza',
                'scheduled_at' => now()->addDays(5),
                'duration_min' => 45,
                'status' => HomeVisit::STATUS_SCHEDULED,
            ]);

            MoneyIn::create([
                'source' => 'Feast Donation',
                'category' => 'donation',
                'amount_minor' => 250000,
                'currency' => 'EGP',
                'fx_rate' => '1',
                'received_at' => now()->toDateString(),
            ]);

            TenantContext::clear();
        });
    }

    // ---- helpers -----------------------------------------------------------

    private function provisionChurch(string $slug, string $name, User $admin): Church
    {
        return $this->provisioning->create([
            'slug' => DemoData::slug($slug),
            'name' => $name,
            'status' => 'active',
            'settings' => ['demo' => true],
            'capabilities' => array_keys((array) config('capabilities')),
        ], [$admin->user_id]);
    }

    private function churchRole(Church $church, string $slug): ?Role
    {
        return Role::where('church_id', $church->church_id)->where('slug', $slug)->first();
    }

    private function makeService(string $en, string $ar): ChurchService
    {
        $service = ChurchService::create([
            'title' => $en,
            'title_en' => $en,
            'title_ar' => $ar,
            'status' => ChurchService::STATUS_ACTIVE,
            'permissions_version' => 0,
        ]);

        // Give the service its service-admin / service-member roles.
        $this->roleTemplates->cloneTemplatesIntoService($service);

        return $service;
    }

    private function makeUser(string $localPart, string $first, string $second, string $churchLabel, string $roleLabel, array $overrides = []): User
    {
        $this->seq++;

        $user = User::create(array_merge([
            'first_name' => $first,
            'second_name' => $second,
            'third_name' => 'Demo',
            'profile_photo' => '',
            'national_id' => sprintf('99%012d', $this->seq),
            // '019' prefix keeps demo mobiles clear of any other numbering scheme.
            'mobile_number' => '019'.str_pad((string) $this->seq, 8, '0', STR_PAD_LEFT),
            'email' => DemoData::email($localPart),
            'job' => $roleLabel,
            'date_of_birth' => '1998-05-15',
            'password' => Hash::make(DemoData::password()),
            'is_verified' => true,
            'is_superadmin' => false,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
        ], $overrides));

        $this->credentials[] = [
            'church' => $churchLabel,
            'role' => $roleLabel,
            'email' => $user->email,
            'password' => DemoData::password(),
        ];

        return $user;
    }
}
