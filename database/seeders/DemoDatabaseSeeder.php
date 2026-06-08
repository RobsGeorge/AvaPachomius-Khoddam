<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Content;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Models\Module;
use App\Models\Role;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\DemoResetService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(DemoResetService::class)->wipeDemoData();

        $password = Hash::make(config('demo.password', 'Demo2026!'));

        $roles = $this->seedRoles();
        $users = $this->seedUsers($password);
        $course = $this->seedCourse();
        $modules = $this->seedModules($course);
        $sessions = $this->seedSessions($course, $modules);
        $this->seedLectures($modules, $sessions);
        $this->seedContent($modules);
        $this->seedAssignments($users['student']);
        $this->seedGrades($course, $users);
        $this->seedAttendance($users, $sessions);
        $this->seedExams($course, $modules, $users);
        $this->seedUserCourseRoles($course, $roles, $users);
    }

    private function seedRoles(): array
    {
        $definitions = [
            'admin'      => 'Administrator',
            'instructor' => 'Instructor',
            'student'    => 'Student',
        ];

        $roles = [];
        foreach ($definitions as $name => $description) {
            $roles[$name] = Role::firstOrCreate(
                ['role_name' => $name],
                ['role_decription' => substr($description, 0, 25)]
            );
        }

        return $roles;
    }

    private function seedUsers(string $password): array
    {
        $profiles = [
            'admin' => ['first' => 'Demo', 'second' => 'Admin', 'third' => 'User', 'job' => 'Demo Admin', 'mobile' => '01000000001', 'nid' => '29901010101010'],
            'instructor' => ['first' => 'Demo', 'second' => 'Instructor', 'third' => 'User', 'job' => 'Demo Instructor', 'mobile' => '01000000002', 'nid' => '29901010101011'],
            'student' => ['first' => 'Demo', 'second' => 'Student', 'third' => 'User', 'job' => 'Demo Student', 'mobile' => '01000000003', 'nid' => '29901010101012'],
        ];

        $users = [];
        foreach ($profiles as $key => $p) {
            $email = config("demo.users.{$key}.email");
            $users[$key] = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name'     => $p['first'],
                    'second_name'    => $p['second'],
                    'third_name'     => $p['third'],
                    'profile_photo'  => '',
                    'national_id'    => $p['nid'],
                    'mobile_number'  => $p['mobile'],
                    'job'            => $p['job'],
                    'date_of_birth'  => '2000-01-15',
                    'password'       => $password,
                    'is_verified'    => true,
                    'is_superadmin'  => false,
                    'is_demo'        => true,
                ]
            );
        }

        return $users;
    }

    private function seedCourse(): Course
    {
        return Course::updateOrCreate(
            ['title' => config('demo.course_title'), 'year' => (int) date('Y')],
            [
                'description'               => 'Demonstration course with sample modules, lectures, exams, and grades.',
                'passing_percentage'        => 60,
                'min_attendance_percentage' => 75,
                'is_demo'                   => true,
            ]
        );
    }

    private function seedModules(Course $course): array
    {
        $definitions = [
            ['title' => 'Foundations', 'description' => 'Core teachings and spiritual foundations.'],
            ['title' => 'Ministry', 'description' => 'Practical ministry skills and workshops.'],
        ];

        $modules = [];
        foreach ($definitions as $i => $def) {
            $module = Module::updateOrCreate(
                ['title' => $def['title'], 'is_demo' => true],
                ['description' => $def['description']]
            );
            $modules[] = $module;

            $pivot = [
                'start_date'  => now()->subWeeks(4)->toDateString(),
                'end_date'    => now()->addWeeks(8)->toDateString(),
                'order_index' => $i + 1,
                'status'      => 'active',
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('course_module', 'feedback_open')) {
                $pivot['feedback_open'] = $i === 0;
            }

            $course->modules()->syncWithoutDetaching([
                $module->module_id => $pivot,
            ]);
        }

        return $modules;
    }

    private function seedSessions(Course $course, array $modules): array
    {
        $sessions = [];
        $start = now()->subWeeks(3)->startOfWeek();

        foreach ($modules as $moduleIndex => $module) {
            for ($week = 1; $week <= 2; $week++) {
                $sessionDate = $start->copy()->addWeeks($moduleIndex * 2 + $week - 1);
                $session = Session::create([
                    'course_id'     => $course->course_id,
                    'module_id'     => $module->module_id,
                    'week_number'   => $moduleIndex * 2 + $week,
                    'session_title' => substr("W{$week} {$module->title}", 0, 30),
                    'session_date'  => $sessionDate->toDateString(),
                ]);
                $sessions[] = $session;

                if (DB::getSchemaBuilder()->hasTable('module_session')) {
                    DB::table('module_session')->updateOrInsert(
                        ['module_id' => $module->module_id, 'session_id' => $session->session_id],
                        ['week_number' => $session->week_number]
                    );
                }
            }
        }

        return $sessions;
    }

    private function seedLectures(array $modules, array $sessions): void
    {
        foreach ($sessions as $session) {
            $module = collect($modules)->firstWhere('module_id', $session->module_id);
            if (! $module) {
                continue;
            }

            for ($n = 1; $n <= 2; $n++) {
                $lecture = Lecture::create([
                    'module_id'    => $module->module_id,
                    'session_id'   => $session->session_id,
                    'title'        => "Lecture {$n}: {$session->session_title}",
                    'week_number'  => $session->week_number,
                    'lecture_date' => $session->session_date,
                    'video_link'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'slides_link'  => 'https://example.com/demo-slides.pdf',
                    'notes'        => 'Demo lecture notes for preview purposes.',
                    'order_index'  => $n,
                ]);

                LectureMaterial::create([
                    'lecture_id' => $lecture->lecture_id,
                    'title'      => 'Supplementary reading',
                    'link'       => 'https://example.com/demo-reading.pdf',
                ]);
            }
        }
    }

    private function seedContent(array $modules): void
    {
        foreach ($modules as $i => $module) {
            $content = Content::updateOrCreate(
                ['title' => substr("Demo {$module->title}", 0, 30), 'is_demo' => true],
                [
                    'content_location' => 'Main hall',
                    'session_title'    => substr("Session {$module->title}", 0, 30),
                    'session_date'     => now()->subWeeks(2 - $i)->toDateString(),
                    'lecture_name'     => 'Introduction',
                    'speaker_name'     => 'Demo Speaker',
                    'audio_link'       => 'https://example.com/demo-audio.mp3',
                    'slides_link'      => 'https://example.com/demo-content-slides.pdf',
                    'description'      => 'Sample study material for the demo student view.',
                ]
            );

            $module->contents()->syncWithoutDetaching([$content->content_id]);
        }
    }

    private function seedAssignments(User $student): void
    {
        $assignment = Assignment::updateOrCreate(
            ['assignment_name' => 'Demo reflection paper', 'is_demo' => true],
            [
                'assignment_description' => 'Write a short reflection on the foundations module.',
                'total_points'         => 20,
                'due_date'             => now()->addWeeks(2),
                'instructions'         => 'Submit 300–500 words in the text box.',
                'resources'            => 'Review week 1 lectures before writing.',
            ]
        );

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->assignment_id, 'user_id' => $student->user_id],
            [
                'submission_content' => 'This is a demo submission showing how assignments appear to students.',
                'points_earned'      => 18,
                'feedback'           => 'Good work — demo feedback from instructor.',
                'submitted_at'       => now()->subDays(3),
            ]
        );
    }

    private function seedGrades(Course $course, array $users): void
    {
        $categories = [
            ['type' => 'exam', 'name' => 'Exams', 'weight' => 40, 'ordering' => 1],
            ['type' => 'quiz', 'name' => 'Quizzes', 'weight' => 20, 'ordering' => 2],
            ['type' => 'attendance', 'name' => 'Attendance', 'weight' => 20, 'ordering' => 3],
            ['type' => 'project', 'name' => 'Projects', 'weight' => 20, 'ordering' => 4],
        ];

        $studentId = $users['student']->user_id;
        $graderId = $users['instructor']->user_id;

        foreach ($categories as $catDef) {
            $category = GradeCategory::updateOrCreate(
                ['course_id' => $course->course_id, 'name' => $catDef['name']],
                [
                    'type'               => $catDef['type'],
                    'weight_percentage'  => $catDef['weight'],
                    'ordering'           => $catDef['ordering'],
                ]
            );

            $items = match ($catDef['type']) {
                'exam' => [['Midterm exam', 50, 42], ['Final exam', 50, 38]],
                'quiz' => [['Quiz 1', 25, 22], ['Quiz 2', 25, 20]],
                'attendance' => [['Attendance points', 100, 85]],
                'project' => [['Reflection paper', 20, 18]],
                default => [],
            };

            foreach ($items as $order => [$title, $max, $score]) {
                $item = GradeItem::updateOrCreate(
                    ['category_id' => $category->category_id, 'title' => $title],
                    [
                        'max_score'   => $max,
                        'item_date'   => now()->subWeeks(2 - $order)->toDateString(),
                        'description' => 'Demo grade item',
                        'ordering'    => $order + 1,
                    ]
                );

                StudentGrade::updateOrCreate(
                    ['item_id' => $item->item_id, 'user_id' => $studentId],
                    [
                        'score'        => $score,
                        'notes'        => 'Seeded demo grade',
                        'graded_by_id' => $graderId,
                        'graded_at'    => now()->subDays(1),
                    ]
                );
            }
        }
    }

    private function seedAttendance(array $users, array $sessions): void
    {
        $student = $users['student'];
        $staff = $users['instructor'];
        $statuses = ['Present', 'Present', 'Late', 'Absent', 'Present', 'Permission'];

        foreach ($sessions as $i => $session) {
            Attendance::updateOrCreate(
                ['user_id' => $student->user_id, 'session_id' => $session->session_id],
                [
                    'taken_by_id'       => $staff->user_id,
                    'status'            => $statuses[$i % count($statuses)],
                    'permission_reason' => $statuses[$i % count($statuses)] === 'Permission' ? 'Medical' : null,
                    'attendance_time'   => Carbon::parse($session->session_date)->setTime(10, 0),
                ]
            );
        }
    }

    private function seedExams(Course $course, array $modules, array $users): void
    {
        $module = $modules[0];

        $onlineExam = Exam::updateOrCreate(
            ['exam_name' => 'Demo online quiz', 'course_id' => $course->course_id],
            [
                'module_id'         => $module->module_id,
                'exam_type'         => Exam::TYPE_QUIZ,
                'delivery_mode'     => Exam::MODE_ONLINE,
                'duration_minutes'  => 30,
                'study_resources'   => 'Review week 1 lectures.',
                'exam_description'  => 'Sample online quiz for demo purposes.',
                'passing_score'     => 60,
                'is_published'      => true,
                'total_points'      => 10,
                'shuffle_questions' => false,
                'allow_late_entry'  => true,
            ]
        );

        $this->seedExamQuestions($onlineExam);

        $schedule = ExamSchedule::updateOrCreate(
            ['exam_id' => $onlineExam->exam_id, 'scheduled_date' => now()->addDays(3)->setTime(14, 0)],
            ['is_completed' => false]
        );

        $offlineExam = Exam::updateOrCreate(
            ['exam_name' => 'Demo written exam', 'course_id' => $course->course_id],
            [
                'module_id'        => $modules[1]->module_id,
                'exam_type'        => Exam::TYPE_EXAM,
                'delivery_mode'    => Exam::MODE_OFFLINE,
                'duration_minutes' => 90,
                'study_resources'  => 'Course handbook chapters 1–3.',
                'exam_description' => 'Offline exam — grade already recorded for demo student.',
                'passing_score'    => 60,
                'is_published'     => true,
                'total_points'     => 100,
            ]
        );

        $offlineSchedule = ExamSchedule::updateOrCreate(
            ['exam_id' => $offlineExam->exam_id, 'scheduled_date' => now()->subWeeks(1)->setTime(10, 0)],
            ['is_completed' => true]
        );

        ExamResult::updateOrCreate(
            [
                'exam_id'     => $offlineExam->exam_id,
                'user_id'     => $users['student']->user_id,
                'schedule_id' => $offlineSchedule->schedule_id,
            ],
            [
                'score'       => 78.5,
                'status'      => ExamResult::STATUS_GRADED,
                'auto_score'  => null,
                'manual_score'=> 78.5,
                'submitted_at'=> now()->subWeeks(1),
            ]
        );

        unset($schedule);
    }

    private function seedExamQuestions(Exam $exam): void
    {
        ExamQuestion::where('exam_id', $exam->exam_id)->delete();

        $mcq = ExamQuestion::create([
            'exam_id'       => $exam->exam_id,
            'question_type' => ExamQuestion::TYPE_MCQ,
            'prompt'        => 'What is the primary goal of the foundations module?',
            'points'        => 5,
            'order_index'   => 1,
        ]);

        $options = [
            ['Build spiritual foundations', true],
            ['Memorize dates only', false],
            ['Skip attendance', false],
            ['None of the above', false],
        ];
        foreach ($options as $i => [$label, $correct]) {
            ExamQuestionOption::create([
                'question_id' => $mcq->question_id,
                'label'       => $label,
                'is_correct'  => $correct,
                'order_index' => $i + 1,
            ]);
        }

        $tf = ExamQuestion::create([
            'exam_id'       => $exam->exam_id,
            'question_type' => ExamQuestion::TYPE_TRUE_FALSE,
            'prompt'        => 'Regular attendance is important for course completion.',
            'points'        => 5,
            'order_index'   => 2,
        ]);

        foreach ([['True', true], ['False', false]] as $i => [$label, $correct]) {
            ExamQuestionOption::create([
                'question_id' => $tf->question_id,
                'label'       => $label,
                'is_correct'  => $correct,
                'order_index' => $i + 1,
            ]);
        }

        $exam->update(['total_points' => 10]);
    }

    private function seedUserCourseRoles(Course $course, array $roles, array $users): void
    {
        $map = [
            'student'    => 'student',
            'instructor' => 'instructor',
            'admin'      => 'admin',
        ];

        foreach ($map as $userKey => $roleKey) {
            UserCourseRole::updateOrCreate(
                [
                    'user_id'   => $users[$userKey]->user_id,
                    'course_id' => $course->course_id,
                    'role_id'   => $roles[$roleKey]->role_id,
                ],
                []
            );
        }
    }
}
