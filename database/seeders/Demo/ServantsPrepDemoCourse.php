<?php

namespace Database\Seeders\Demo;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use App\Models\ExamSchedule;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSurvey;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Models\Module;
use App\Models\Session;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\AttendanceCloseService;
use App\Services\ExamGradingService;
use App\Services\FeedbackSurveyService;
use App\Services\ProjectAdminService;
use App\Services\ProjectTeamWorkflowService;
use Illuminate\Support\Carbon;

/**
 * Fills a demo course with a term's worth of servants-preparation activity, shaped like the
 * live Tenant Zero course: three modules of weekly sessions with lectures and materials,
 * closed attendance (present / late / permission / absent), graded and pending assignments,
 * online quizzes graded through ExamGradingService, module feedback surveys answered through
 * FeedbackSurveyService, a weighted gradebook, announcements, and a published team project.
 *
 * Everything is relative to today: weeks 1–9 are in the past, weeks 10–12 are upcoming.
 * Week 9's attendance is recorded but left open so staff can demo closing it; the module 2
 * quiz is graded but unannounced; the module 2 survey is open and student #1 has not
 * answered it. Per-student outcomes are deterministic (hash-based), so reseeding produces
 * the same picture.
 *
 * Callers must bind the course's church in TenantContext first. Nothing here sends mail,
 * WhatsApp, or portal notifications.
 */
class ServantsPrepDemoCourse
{
    public const WEEKS = 12;

    public const PAST_WEEKS = 9;

    private const WEEKS_PER_MODULE = 4;

    private const SESSION_START = '19:00';

    private const TIMEZONE = 'Africa/Cairo';

    private Course $course;

    private User $courseAdmin;

    /** @var list<User> instructor per module (index 0..2) */
    private array $instructors;

    /** @var list<User> */
    private array $students;

    /** @var list<Module> */
    private array $modules = [];

    /** @var array<int, Session> keyed by week number */
    private array $sessions = [];

    /** @var array<int, Lecture> keyed by week number */
    private array $lectures = [];

    private Carbon $firstSessionDate;

    /**
     * @param  list<User>  $instructors  one per module; reused cyclically if fewer
     * @param  list<User>  $students
     */
    public function build(Course $course, User $courseAdmin, array $instructors, array $students): void
    {
        $this->course = $course;
        $this->courseAdmin = $courseAdmin;
        $this->instructors = array_values($instructors);
        $this->students = array_values($students);
        $this->firstSessionDate = Carbon::now(self::TIMEZONE)
            ->startOfDay()
            ->subDays(3 + 7 * (self::PAST_WEEKS - 1));

        $this->seedCurriculum();
        $this->seedGradebook();
        $this->seedAttendance();
        $this->seedAssignments();
        $this->seedQuizzes();
        $this->seedFinalExam();
        $this->seedSurveys();
        $this->seedAnnouncements();
        $this->seedProject();
    }

    // ---- curriculum ----------------------------------------------------------

    private function seedCurriculum(): void
    {
        foreach ($this->curriculum() as $m => $module) {
            $row = Module::create([
                'title' => $module['title'],
                'description' => $module['description'],
            ]);
            $this->modules[$m] = $row;

            $firstWeek = $m * self::WEEKS_PER_MODULE + 1;
            $lastWeek = $firstWeek + self::WEEKS_PER_MODULE - 1;
            $ended = $lastWeek <= self::PAST_WEEKS - 1;

            $this->course->modules()->attach($row->module_id, [
                'order_index' => $m + 1,
                'start_date' => $this->weekDate($firstWeek)->toDateString(),
                'end_date' => $this->weekDate($lastWeek)->toDateString(),
                'status' => $ended ? 'ended' : 'active',
                'feedback_open' => $ended,
                'ended_at' => $ended ? $this->weekDate($lastWeek)->setTime(21, 0)->utc() : null,
                'ended_by_user_id' => $ended ? $this->courseAdmin->user_id : null,
            ]);

            $sync = [];
            foreach ($module['lectures'] as $i => [$title, $notes]) {
                $week = $firstWeek + $i;
                $session = Session::create([
                    'course_id' => $this->course->course_id,
                    'module_id' => $row->module_id,
                    'week_number' => $week,
                    'session_title' => $title,
                    'session_date' => $this->weekDate($week)->toDateString(),
                    'session_start_time' => self::SESSION_START,
                    'attendance_grain' => 'session',
                    'notify_students' => false,
                ]);
                $this->sessions[$week] = $session;
                $sync[$session->session_id] = ['week_number' => $week];

                $lecture = Lecture::create([
                    'module_id' => $row->module_id,
                    'session_id' => $session->session_id,
                    'title' => $title,
                    'week_number' => $week,
                    'lecture_date' => $this->weekDate($week)->toDateString(),
                    'notes' => $notes,
                    'order_index' => 1,
                ]);
                $this->lectures[$week] = $lecture;

                if ($week <= self::PAST_WEEKS) {
                    LectureMaterial::create([
                        'lecture_id' => $lecture->lecture_id,
                        'title' => 'ملخص المحاضرة — '.$title,
                        'source_type' => LectureMaterial::SOURCE_EXTERNAL_LINK,
                        'link' => 'https://st-takla.org/',
                    ]);
                }
            }
            $row->sessions()->sync($sync);
        }
    }

    /**
     * @return list<array{title: string, description: string, lectures: list<array{0: string, 1: string}>}>
     */
    private function curriculum(): array
    {
        return [
            [
                'title' => 'اللاهوت العقيدي',
                'description' => 'أساسيات الإيمان الأرثوذكسي: الثالوث، التجسد والفداء، الأسرار، والكنيسة.',
                'lectures' => [
                    ['الثالوث القدوس', 'وحدانية الله وتمايز الأقانيم، مع أمثلة من قانون الإيمان.'],
                    ['التجسد والفداء', 'لماذا تجسد الكلمة؟ الفداء في فكر القديس أثناسيوس.'],
                    ['أسرار الكنيسة السبعة', 'مفهوم السر ونعمة الروح القدس في كل سر.'],
                    ['الكنيسة جسد المسيح', 'سمات الكنيسة الواحدة الجامعة المقدسة الرسولية.'],
                ],
            ],
            [
                'title' => 'دراسة الكتاب المقدس',
                'description' => 'مدخل إلى الكتاب المقدس ودراسة تطبيقية في إنجيل مرقس وسفر الأعمال والمزامير.',
                'lectures' => [
                    ['مدخل إلى الكتاب المقدس', 'الوحي، القانونية، وكيف نقرأ الكتاب في الكنيسة.'],
                    ['إنجيل مرقس', 'مارمرقس كاروز الديار المصرية وملامح إنجيله.'],
                    ['سفر أعمال الرسل', 'الكنيسة الأولى وانتشار البشارة.'],
                    ['المزامير في صلوات الكنيسة', 'المزامير في الأجبية والتسبحة.'],
                ],
            ],
            [
                'title' => 'التربية الكنسية وطرق الخدمة',
                'description' => 'مراحل النمو، تحضير الدرس، إدارة الفصل، والافتقاد.',
                'lectures' => [
                    ['مراحل النمو وخصائصها', 'احتياجات كل مرحلة عمرية وكيف نخدمها.'],
                    ['تحضير درس مدارس الأحد', 'الهدف، المقدمة، القصة، التطبيق، والوسيلة.'],
                    ['إدارة الفصل', 'الانضباط بالمحبة وإشراك المخدومين.'],
                    ['الافتقاد', 'الافتقاد المنزلي والهاتفي ومتابعة الغائبين.'],
                ],
            ],
        ];
    }

    // ---- gradebook -----------------------------------------------------------

    /** @var array<string, GradeCategory> */
    private array $categories = [];

    private function seedGradebook(): void
    {
        $rows = [
            'attendance' => ['الحضور', 20],
            'quiz' => ['الاختبارات القصيرة', 30],
            'exam' => ['الامتحان النهائي', 30],
            'project' => ['المشروع', 20],
        ];

        $order = 0;
        foreach ($rows as $type => [$name, $weight]) {
            $this->categories[$type] = GradeCategory::create([
                'course_id' => $this->course->course_id,
                'type' => $type,
                'name' => $name,
                'weight_percentage' => $weight,
                'ordering' => ++$order,
            ]);
        }
    }

    // ---- attendance ----------------------------------------------------------

    private function seedAttendance(): void
    {
        $closer = app(AttendanceCloseService::class);

        for ($week = 1; $week <= self::PAST_WEEKS; $week++) {
            $session = $this->sessions[$week];
            $taker = $this->instructorForWeek($week);
            $start = Carbon::parse($session->session_date->format('Y-m-d').' '.self::SESSION_START, self::TIMEZONE);

            foreach ($this->students as $i => $student) {
                $roll = $this->roll("att-{$i}-{$week}");
                if ($roll >= $this->attendanceRate($i)) {
                    if ($this->roll("perm-{$i}-{$week}") < 40) {
                        $this->recordAttendance($session, $student, $taker, 'Permission', $start->copy()->subHours(2), 'ظرف عائلي');
                    }

                    continue; // closing the session marks the rest Absent
                }

                // Arrival relative to start; beyond the policy grace period becomes Late at close.
                $minutes = $this->roll("late-{$i}-{$week}") < 15 ? 25 + $this->roll("lm-{$i}-{$week}") % 20 : -10 + $this->roll("om-{$i}-{$week}") % 18;
                $this->recordAttendance($session, $student, $taker, 'Present', $start->copy()->addMinutes($minutes));
            }

            if ($week < self::PAST_WEEKS) {
                $closer->closeSession($session, (int) $taker->user_id);
                $session->update(['attendance_closed_at' => $start->copy()->addHours(3)->utc()]);
            }
        }
    }

    private function recordAttendance(Session $session, User $student, User $taker, string $status, Carbon $at, ?string $reason = null): void
    {
        Attendance::create([
            'user_id' => $student->user_id,
            'person_id' => $student->person_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $taker->user_id,
            'status' => $status,
            'permission_reason' => $reason,
            'attendance_time' => $at->copy()->utc(),
            'lock_version' => 0,
        ]);
    }

    // ---- assignments ---------------------------------------------------------

    private function seedAssignments(): void
    {
        $graded = Assignment::create([
            'course_id' => $this->course->course_id,
            'assignment_name' => 'تأمل في سر التجسد',
            'assignment_description' => 'اكتب تأملاً في صفحة واحدة عن معنى التجسد في حياة الخادم.',
            'total_points' => 10,
            'due_date' => $this->weekDate(3)->setTime(23, 59)->utc(),
            'instructions' => 'التسليم كنص مكتوب داخل المنصة قبل الموعد.',
            'delivery_mode' => Assignment::MODE_ONLINE,
        ]);

        $pending = Assignment::create([
            'course_id' => $this->course->course_id,
            'assignment_name' => 'دراسة أصحاح من إنجيل مرقس',
            'assignment_description' => 'اختر أصحاحاً من إنجيل مرقس واكتب ثلاث نقاط للتأمل وتطبيقاً عملياً للخدمة.',
            'total_points' => 20,
            'due_date' => $this->weekDate(7)->setTime(23, 59)->utc(),
            'instructions' => 'نص مكتوب أو ملف PDF.',
            'delivery_mode' => Assignment::MODE_ONLINE,
        ]);

        Assignment::create([
            'course_id' => $this->course->course_id,
            'assignment_name' => 'تحضير درس مدارس أحد',
            'assignment_description' => 'حضّر درساً كاملاً لمرحلة ابتدائي: الهدف، المقدمة، القصة، التطبيق، والوسيلة.',
            'total_points' => 20,
            'due_date' => $this->weekDate(11)->setTime(23, 59)->utc(),
            'instructions' => 'استخدم نموذج تحضير الدرس المرفق في محاضرة الأسبوع العاشر.',
            'delivery_mode' => Assignment::MODE_ONLINE,
        ]);

        foreach ($this->students as $i => $student) {
            if ($this->roll("asg1-{$i}") < 90) {
                $points = min(10, max(4, (int) round($this->ability($i) / 10 + ($this->roll("asg1p-{$i}") % 3) - 1)));
                $submittedAt = $this->weekDate(3)->setTime(18, 0)->subHours($this->roll("asg1t-{$i}") % 48)->utc();
                AssignmentSubmission::create([
                    'assignment_id' => $graded->assignment_id,
                    'user_id' => $student->user_id,
                    'submission_content' => 'التجسد هو إعلان محبة الله للإنسان؛ فالخادم مدعو أن يحمل هذه المحبة لمخدوميه في الافتقاد والصلاة والقدوة.',
                    'submitted_at' => $submittedAt,
                    'points_earned' => $points,
                    'feedback' => $points >= 8 ? 'تأمل عميق ومرتب، أحسنت.' : 'فكرة جيدة، تحتاج إلى ربط أوضح بالتطبيق العملي.',
                    'graded_at' => $this->weekDate(4)->setTime(12, 0)->utc(),
                ]);
            }

            if ($this->roll("asg2-{$i}") < 80) {
                AssignmentSubmission::create([
                    'assignment_id' => $pending->assignment_id,
                    'user_id' => $student->user_id,
                    'submission_content' => 'مرقس ٤: مثل الزارع. التأمل: القلب المستعد، الصبر على الثمر، والاستمرار في البذر. التطبيق: متابعة كل مخدوم بحسب حالته.',
                    'submitted_at' => $this->weekDate(7)->setTime(20, 0)->subHours($this->roll("asg2t-{$i}") % 72)->utc(),
                ]);
            }
        }
    }

    // ---- quizzes & exams ------------------------------------------------------

    private function seedQuizzes(): void
    {
        $quizzes = [
            0 => ['اختبار قصير — اللاهوت العقيدي', 4, true],
            1 => ['اختبار قصير — دراسة الكتاب المقدس', 8, false],
        ];

        $item = 0;
        foreach ($quizzes as $m => [$name, $week, $announced]) {
            $exam = $this->makeObjectiveExam($m, $name, Exam::TYPE_QUIZ, 15, $this->quizQuestions($m), $announced ? $this->weekDate($week + 1)->setTime(10, 0)->utc() : null);
            $schedule = ExamSchedule::create([
                'exam_id' => $exam->exam_id,
                'scheduled_date' => $this->weekDate($week)->setTime(20, 30)->utc(),
                'is_completed' => true,
            ]);

            $gradeItem = GradeItem::create([
                'category_id' => $this->categories['quiz']->category_id,
                'title' => $name,
                'max_score' => 100,
                'item_date' => $this->weekDate($week)->toDateString(),
                'ordering' => ++$item,
            ]);

            $this->takeExam($exam, $schedule, $week, $announced ? $gradeItem : null);
        }
    }

    private function seedFinalExam(): void
    {
        $exam = $this->makeObjectiveExam(2, 'الامتحان النهائي', Exam::TYPE_EXAM, 45, array_merge(
            $this->quizQuestions(0),
            $this->quizQuestions(1),
            $this->quizQuestions(2),
        ), null);

        ExamSchedule::create([
            'exam_id' => $exam->exam_id,
            'scheduled_date' => $this->weekDate(self::WEEKS)->setTime(20, 30)->utc(),
            'is_completed' => false,
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: list<string>, 3: int}>  $questions  [type, prompt, options, correctIndex]
     */
    private function makeObjectiveExam(int $moduleIndex, string $name, string $type, int $minutes, array $questions, ?Carbon $announcedAt): Exam
    {
        $exam = Exam::create([
            'course_id' => $this->course->course_id,
            'module_id' => $this->modules[$moduleIndex]->module_id,
            'exam_name' => $name,
            'exam_type' => $type,
            'delivery_mode' => Exam::MODE_ONLINE,
            'duration_minutes' => $minutes,
            'exam_description' => 'أسئلة اختيار من متعدد وصواب وخطأ على محاضرات الوحدة.',
            'passing_score' => 60,
            'is_published' => true,
            'total_points' => 0,
            'shuffle_questions' => true,
            'allow_late_entry' => false,
            'results_announced_at' => $announcedAt,
            'results_announced_by_user_id' => $announcedAt ? $this->courseAdmin->user_id : null,
        ]);

        $total = 0;
        foreach ($questions as $order => [$qType, $prompt, $options, $correct]) {
            $points = $qType === ExamQuestion::TYPE_MCQ ? 2 : 1;
            $total += $points;
            $question = ExamQuestion::create([
                'exam_id' => $exam->exam_id,
                'question_type' => $qType,
                'prompt' => $prompt,
                'points' => $points,
                'order_index' => $order + 1,
            ]);
            foreach ($options as $o => $label) {
                ExamQuestionOption::create([
                    'question_id' => $question->question_id,
                    'label' => $label,
                    'is_correct' => $o === $correct,
                    'order_index' => $o + 1,
                ]);
            }
        }

        $exam->update(['total_points' => $total]);

        return $exam->fresh('questions.options');
    }

    private function takeExam(Exam $exam, ExamSchedule $schedule, int $week, ?GradeItem $gradeItem): void
    {
        $grading = app(ExamGradingService::class);
        $startedAt = Carbon::parse($schedule->scheduled_date)->utc();

        foreach ($this->students as $i => $student) {
            if ($this->roll("exam-{$exam->exam_id}-skip-{$i}") < 8) {
                continue;
            }

            $answers = [];
            foreach ($exam->questions as $question) {
                $options = $question->options->values();
                $correct = $options->search(fn (ExamQuestionOption $o) => $o->is_correct);
                $pick = $this->roll("exam-{$exam->exam_id}-q{$question->question_id}-{$i}") < $this->ability($i)
                    ? $correct
                    : ($correct + 1) % $options->count();
                $answers[(string) $question->question_id] = $options[$pick]->option_id;
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $exam->exam_id,
                'schedule_id' => $schedule->schedule_id,
                'user_id' => $student->user_id,
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'answers_json' => $answers,
                'started_at' => $startedAt,
                'checklist_acknowledged_at' => $startedAt,
            ]);

            $result = $grading->submitAttempt($attempt);
            $finishedAt = $startedAt->copy()->addMinutes(6 + $this->roll("exam-fin-{$i}") % 8);
            $attempt->update(['submitted_at' => $finishedAt]);
            $result->update(['submitted_at' => $finishedAt]);

            if ($gradeItem) {
                StudentGrade::create([
                    'item_id' => $gradeItem->item_id,
                    'user_id' => $student->user_id,
                    'score' => $result->score,
                    'graded_by_id' => $this->instructorForWeek($week)->user_id,
                    'graded_at' => $this->weekDate($week + 1)->setTime(10, 0)->utc(),
                ]);
            }
        }
    }

    /** @return list<array{0: string, 1: string, 2: list<string>, 3: int}> */
    private function quizQuestions(int $moduleIndex): array
    {
        $tf = ['صح', 'خطأ'];

        return match ($moduleIndex) {
            0 => [
                [ExamQuestion::TYPE_MCQ, 'كم عدد أقانيم الثالوث القدوس؟', ['ثلاثة', 'اثنان', 'واحد', 'أربعة'], 0],
                [ExamQuestion::TYPE_MCQ, 'من هو القديس الذي كتب "تجسد الكلمة"؟', ['أثناسيوس الرسولي', 'كيرلس الكبير', 'يوحنا ذهبي الفم', 'باسيليوس الكبير'], 0],
                [ExamQuestion::TYPE_MCQ, 'كم عدد أسرار الكنيسة؟', ['سبعة', 'خمسة', 'ثلاثة', 'اثنا عشر'], 0],
                [ExamQuestion::TYPE_TRUE_FALSE, 'سر المعمودية يُمارس مرة واحدة فقط في حياة الإنسان.', $tf, 0],
                [ExamQuestion::TYPE_TRUE_FALSE, 'الكنيسة في قانون الإيمان "واحدة جامعة مقدسة رسولية".', $tf, 0],
            ],
            1 => [
                [ExamQuestion::TYPE_MCQ, 'كم عدد أسفار العهد الجديد؟', ['٢٧ سفراً', '٢٤ سفراً', '٣٩ سفراً', '٦٦ سفراً'], 0],
                [ExamQuestion::TYPE_MCQ, 'من هو كاروز الديار المصرية؟', ['مارمرقس الرسول', 'بطرس الرسول', 'يوحنا الحبيب', 'لوقا الطبيب'], 0],
                [ExamQuestion::TYPE_MCQ, 'من كاتب سفر أعمال الرسل؟', ['لوقا', 'بولس', 'بطرس', 'يعقوب'], 0],
                [ExamQuestion::TYPE_TRUE_FALSE, 'الأجبية تحتوي على مزامير السواعي.', $tf, 0],
                [ExamQuestion::TYPE_TRUE_FALSE, 'إنجيل مرقس هو أطول الأناجيل الأربعة.', $tf, 1],
            ],
            default => [
                [ExamQuestion::TYPE_MCQ, 'ما أول عنصر في تحضير الدرس؟', ['تحديد الهدف', 'اختيار الوسيلة', 'كتابة القصة', 'التطبيق'], 0],
                [ExamQuestion::TYPE_MCQ, 'ما الهدف الأساسي من الافتقاد؟', ['رعاية المخدوم ومحبته', 'تسجيل الغياب', 'جمع الاشتراكات', 'توزيع الهدايا'], 0],
                [ExamQuestion::TYPE_TRUE_FALSE, 'الانضباط في الفصل يتحقق بالمحبة والحزم معاً.', $tf, 0],
            ],
        };
    }

    // ---- feedback surveys -------------------------------------------------------

    private function seedSurveys(): void
    {
        $service = app(FeedbackSurveyService::class);

        foreach ([0 => 'closed', 1 => 'open'] as $m => $state) {
            $lastWeek = ($m + 1) * self::WEEKS_PER_MODULE;
            $survey = FeedbackSurvey::create([
                'course_id' => $this->course->course_id,
                'module_id' => $this->modules[$m]->module_id,
                'title' => 'تقييم وحدة '.$this->modules[$m]->title,
                'description' => 'رأيك يساعدنا على تحسين الوحدات القادمة.',
                'created_by_user_id' => $this->courseAdmin->user_id,
                'status' => FeedbackSurvey::STATUS_OPEN,
                'is_mandatory' => false,
                'is_anonymous' => false,
                'opened_at' => $this->weekDate($lastWeek)->setTime(21, 0)->utc(),
            ]);

            $lectureTitles = [];
            for ($w = $lastWeek - self::WEEKS_PER_MODULE + 1; $w <= $lastWeek; $w++) {
                $lectureTitles[] = $this->lectures[$w]->title;
            }

            $questions = [
                FeedbackQuestion::create([
                    'survey_id' => $survey->survey_id, 'question_type' => FeedbackQuestion::TYPE_RATING,
                    'scope' => FeedbackQuestion::SCOPE_INSTRUCTOR, 'target_user_id' => $this->instructors[$m % count($this->instructors)]->user_id,
                    'label' => 'تقييمك لأسلوب المحاضِر', 'order_index' => 1, 'is_required' => true, 'config' => ['max_rating' => 5],
                ]),
                FeedbackQuestion::create([
                    'survey_id' => $survey->survey_id, 'question_type' => FeedbackQuestion::TYPE_RATING,
                    'scope' => FeedbackQuestion::SCOPE_GENERAL,
                    'label' => 'مدى استفادتك من محتوى الوحدة', 'order_index' => 2, 'is_required' => true, 'config' => ['max_rating' => 5],
                ]),
                FeedbackQuestion::create([
                    'survey_id' => $survey->survey_id, 'question_type' => FeedbackQuestion::TYPE_MCQ,
                    'scope' => FeedbackQuestion::SCOPE_GENERAL,
                    'label' => 'أكثر محاضرة استفدت منها', 'order_index' => 3, 'is_required' => true,
                    'config' => ['choices' => $lectureTitles, 'allow_multiple' => false, 'allow_other' => false],
                ]),
                FeedbackQuestion::create([
                    'survey_id' => $survey->survey_id, 'question_type' => FeedbackQuestion::TYPE_TEXT,
                    'scope' => FeedbackQuestion::SCOPE_GENERAL,
                    'label' => 'اقتراحات للتحسين', 'order_index' => 4, 'is_required' => false,
                ]),
            ];

            $suggestions = ['نحتاج وقتاً أطول للأسئلة.', 'أمثلة عملية أكثر من الخدمة.', 'المحاضرات ممتازة، شكراً.', ''];
            foreach ($this->students as $i => $student) {
                $threshold = $state === 'closed' ? 85 : 55;
                if ($i === 0 && $state === 'open') {
                    continue;
                }
                if ($this->roll("survey-{$m}-{$i}") >= $threshold) {
                    continue;
                }

                $service->submit($survey, $student, [
                    $questions[0]->question_id => 3 + $this->roll("sv-a-{$m}-{$i}") % 3,
                    $questions[1]->question_id => 3 + $this->roll("sv-b-{$m}-{$i}") % 3,
                    $questions[2]->question_id => $lectureTitles[$this->roll("sv-c-{$m}-{$i}") % count($lectureTitles)],
                    $questions[3]->question_id => $suggestions[$this->roll("sv-d-{$m}-{$i}") % count($suggestions)],
                ]);
            }

            if ($state === 'closed') {
                $survey->update([
                    'status' => FeedbackSurvey::STATUS_CLOSED,
                    'closed_at' => $this->weekDate($lastWeek + 2)->setTime(21, 0)->utc(),
                ]);
            }
        }
    }

    // ---- announcements & project ------------------------------------------------

    private function seedAnnouncements(): void
    {
        $rows = [
            [1, $this->courseAdmin, 'مرحباً بكم في إعداد الخدام', 'نبدأ رحلة السنة الأولى بنعمة المسيح. المحاضرات أسبوعياً الساعة السابعة مساءً. برجاء الالتزام بالحضور ومتابعة المنصة.'],
            [5, $this->instructors[0], 'إعلان نتيجة الاختبار القصير الأول', 'تم إعلان نتيجة اختبار وحدة اللاهوت العقيدي. يمكنكم مراجعة الدرجات من صفحة الاختبارات.'],
            [9, $this->courseAdmin, 'إطلاق مشروع الخدمة الميدانية', 'تم فتح التسجيل في فرق المشروع. اختر فريقك قبل الموعد المحدد وراجع المراحل والتسليمات.'],
        ];

        foreach ($rows as [$week, $author, $title, $body]) {
            $at = $this->weekDate($week)->setTime(12, 0)->utc();
            Announcement::create([
                'created_by_user_id' => $author->user_id,
                'course_id' => $this->course->course_id,
                'title' => $title,
                'body' => $body,
                'target_mode' => Announcement::TARGET_COURSE,
                'channels' => [Announcement::CHANNEL_HOMEPAGE],
                'status' => Announcement::STATUS_PUBLISHED,
                'published_at' => $at,
                'published_by_user_id' => $author->user_id,
            ]);
        }
    }

    private function seedProject(): void
    {
        $admin = app(ProjectAdminService::class);
        $due = $this->weekDate(self::WEEKS)->setTime(23, 59)->utc();

        $assessment = $admin->createAssessment([
            'course_id' => (int) $this->course->course_id,
            'module_id' => (int) $this->modules[2]->module_id,
            'title' => 'مشروع الخدمة الميدانية',
            'description' => 'مشروع جماعي يطبق فيه الخدام ما تعلموه في خدمة حقيقية، مع مراحل وتسليمات مشتركة.',
            'min_team_size' => 3,
            'max_team_size' => 7,
            'max_points' => 100,
            'passing_percent' => 50,
            'join_closes_at' => $this->weekDate(10)->setTime(23, 59)->utc()->toDateTimeString(),
            'submission_due_at' => $due->toDateTimeString(),
            'sync_to_gradebook' => true,
            'subprojects' => [
                ['title' => 'زيارة المرضى', 'brief_main_title' => 'زيارة المرضى في المستشفى', 'brief_audience' => 'المرضى ودار الرعاية', 'brief_environment' => 'مستشفى بالتنسيق مع أب الاعتراف', 'brief_purpose' => 'زيارتان وتقرير موثق'],
                ['title' => 'خدمة المسنين', 'brief_main_title' => 'رعاية المسنين', 'brief_audience' => 'كبار السن في الرعية', 'brief_environment' => 'دار مسنين أو بيوت الرعية', 'brief_purpose' => 'خطة رعاية شهرية'],
                ['title' => 'يوم نشاط للأطفال', 'brief_main_title' => 'يوم روحي ترفيهي', 'brief_audience' => 'أطفال مدارس الأحد', 'brief_environment' => 'قاعة الكنيسة', 'brief_purpose' => 'برنامج وميزانية وكشف متطوعين'],
            ],
            'phases' => ProjectTeamWorkflowService::canonicalPhasePayload($due->toDateTimeString()),
            'deliverables' => ProjectTeamWorkflowService::canonicalDeliverablePayload($due->toDateTimeString()),
            'criteria' => [
                ['title' => 'الالتزام بالخطة', 'max_points' => 30],
                ['title' => 'جودة التنفيذ', 'max_points' => 40],
                ['title' => 'التقرير', 'max_points' => 30],
            ],
        ], $this->courseAdmin);

        $admin->publish($assessment, true);
    }

    // ---- helpers -------------------------------------------------------------------

    private function weekDate(int $week): Carbon
    {
        return $this->firstSessionDate->copy()->addWeeks($week - 1);
    }

    private function instructorForWeek(int $week): User
    {
        $module = intdiv($week - 1, self::WEEKS_PER_MODULE);

        return $this->instructors[$module % count($this->instructors)];
    }

    /** Deterministic 0–99 roll so a reseed reproduces the same outcomes. */
    private function roll(string $key): int
    {
        return crc32($this->course->title.'|'.$key) % 100;
    }

    /** Percent chance (60–99) of attending a given session. */
    private function attendanceRate(int $studentIndex): int
    {
        return 60 + $this->roll("rate-{$studentIndex}") % 40;
    }

    /** Percent chance (45–99) of answering a question correctly. */
    private function ability(int $studentIndex): int
    {
        return 45 + $this->roll("ability-{$studentIndex}") % 55;
    }
}
