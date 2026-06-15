<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\CourseAssessmentController;
use App\Http\Controllers\UserAssessmentController;
use App\Http\Controllers\UserCourseRoleController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamBuilderController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\ExamGradesController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminAuditController;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\ModuleFeedbackController;
use App\Http\Controllers\LectureController;
use App\Http\Controllers\LectureMaterialController;
use App\Http\Controllers\GradeCategoryController;
use App\Http\Controllers\GradeItemController;
use App\Http\Controllers\StudentGradeController;
use App\Http\Controllers\GraduationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\Admin\TranslationController;



Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');

require __DIR__.'/auth.php';


Route::middleware(['auth'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('courses', CourseController::class);
    Route::get('/curriculum', [CurriculumController::class, 'index'])->name('curriculum.index');
    Route::resource('sessions', SessionController::class)->only(['index']);
    Route::resource('assessments', AssessmentController::class);
    Route::resource('course-assessments', CourseAssessmentController::class);
    Route::resource('user-assessments', UserAssessmentController::class);
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'destroy']);
    Route::resource('user-course-roles', UserCourseRoleController::class)->only(['index', 'create', 'store', 'destroy']);
});

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register'])->name('register.store');

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('password/reset', [NewPasswordController::class, 'store'])->name('password.update');

Route::post('otp/send', [OTPController::class, 'sendOtp'])->name('otp.send');

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);

Route::get('/verify-otp', [OTPController::class, 'showForm'])->name('otp.verify');
Route::post('/verify-otp', [OTPController::class, 'verify']);
Route::post('/resend-otp', [OTPController::class, 'resend'])->name('otp.resend');

Route::get('/set-password/{user_id}', [RegisterController::class, 'showSetPasswordForm'])->name('password.set');
Route::post('/set-password', [RegisterController::class, 'storePassword'])->name('password.set.store');

Route::middleware(['auth', 'attendance.staff'])->group(function () {
    Route::get('/attendance/sessions', [AttendanceController::class, 'showTodaySessions'])->name('attendance.sessions');
    Route::post('/attendance/record/{session}', [AttendanceController::class, 'recordAttendance'])->name('attendance.record');
});

Route::middleware(['auth', 'role:admin,instructor'])->group(function () {
    // View all attendance records
    Route::get('/attendance/all', [AttendanceController::class, 'viewAllAttendance'])->name('attendance.all');

    // Attendance report
    Route::get('/attendance/report', [AttendanceController::class, 'attendanceReport'])->name('attendance.report');

    // Update attendance status
    Route::get('/attendance/update-status/{id}', [AttendanceController::class, 'updateStatus'])->name('attendance.update-status');

    // Update permission reason
    Route::get('/attendance/update-permission-reason/{id}', [AttendanceController::class, 'updatePermissionReason'])->name('attendance.update-permission-reason');

    Route::get('/attendance/user-report/{userId}', [AttendanceController::class, 'userReport'])->name('attendance.user-report');
});

Route::middleware('auth')->group(function () {
    Route::put('/profile/picture', [ProfileController::class, 'updatePicture'])->name('profile.picture.update');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [LoginController::class, 'showLoginForm'])->name('home');
    Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');

    // View attendance records for a specific user
    Route::get('/attendance/user/{userId}', [AttendanceController::class, 'viewUserAttendance'])->name('attendance.user');
    
    // Regular user attendance view
    Route::get('/attendance/my', [AttendanceController::class, 'viewMyAttendance'])->name('attendance.my');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users/unverified', [UserManagementController::class, 'index'])->name('users.unverified');
    Route::post('/users/{id}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
    Route::post('/translations', [TranslationController::class, 'store'])->name('translations.store');
    Route::post('/translations/auto', [TranslationController::class, 'autoTranslate'])->name('translations.auto');
    Route::get('/graduation-settings', [GraduationController::class, 'settings'])->name('graduation-settings.index');
    Route::put('/courses/{course}/graduation-settings', [GraduationController::class, 'updateSettings'])->name('graduation-settings.update');
});

Route::get('/attendance/mark/{user_id}', [AttendanceController::class, 'mark'])->name('attendance.mark')->middleware('auth');

Route::get('/attendance/date/{date}', [AttendanceController::class, 'viewAttendanceByDate'])->name('attendance.by-date');

Route::post('/attendance/{id}/status', [AttendanceController::class, 'updateStatus'])->name('attendance.update-status-post');

// Exam routes
Route::middleware('auth')->group(function () {
    Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('/exams/schedules/{schedule}/lobby', [ExamAttemptController::class, 'lobby'])->name('exams.attempt.lobby');
    Route::post('/exams/schedules/{schedule}/begin', [ExamAttemptController::class, 'begin'])->name('exams.attempt.begin');
    Route::get('/exams/schedules/{schedule}/start', [ExamAttemptController::class, 'start'])->name('exams.attempt.start');
    Route::get('/exams/schedules/{schedule}/take', [ExamAttemptController::class, 'show'])->name('exams.attempt.show');
    Route::post('/exams/schedules/{schedule}/save', [ExamAttemptController::class, 'save'])->name('exams.attempt.save');
    Route::post('/exams/schedules/{schedule}/submit', [ExamAttemptController::class, 'submit'])->name('exams.attempt.submit');
    Route::post('/exams/schedules/{schedule}/proctor', [ExamAttemptController::class, 'proctor'])->name('exams.attempt.proctor');
    Route::get('/exams/schedules/{schedule}/timer', [ExamAttemptController::class, 'timer'])->name('exams.attempt.timer');
    Route::get('/exams/schedules/{schedule}/confirmation', [ExamAttemptController::class, 'confirmation'])->name('exams.attempt.confirmation');
});

Route::middleware(['auth', 'role:instructor,admin'])->group(function () {
    Route::get('/exams/dashboard', [ExamController::class, 'dashboard'])->name('exams.dashboard');
    Route::get('/exams/admin-dashboard', [ExamController::class, 'adminDashboard'])->name('exams.admin-dashboard');
    Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
    Route::put('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
    Route::post('/exams/{exam}/schedule', [ExamController::class, 'scheduleExam'])->name('exams.schedule');
    Route::put('/exam-results/{result}', [ExamController::class, 'updateResult'])->name('exam-results.update');

    Route::get('/exams/{exam}/builder', [ExamBuilderController::class, 'edit'])->name('exams.builder');
    Route::post('/exams/{exam}/questions', [ExamBuilderController::class, 'storeQuestion'])->name('exams.questions.store');
    Route::put('/exams/{exam}/questions/{question}', [ExamBuilderController::class, 'updateQuestion'])->name('exams.questions.update');
    Route::delete('/exams/{exam}/questions/{question}', [ExamBuilderController::class, 'destroyQuestion'])->name('exams.questions.destroy');
    Route::post('/exams/{exam}/publish', [ExamBuilderController::class, 'publish'])->name('exams.publish');

    Route::get('/exams/{exam}/grades', [ExamGradesController::class, 'show'])->name('exams.grades');
    Route::post('/exams/{exam}/grades/offline', [ExamGradesController::class, 'storeOffline'])->name('exams.grades.offline');
    Route::put('/exams/{exam}/grades/{result}', [ExamGradesController::class, 'updateManual'])->name('exams.grades.update');
    Route::post('/exams/{exam}/grades/{result}/clear-cheater', [ExamGradesController::class, 'clearCheater'])->name('exams.grades.clear-cheater');
});

// Assignment routes
Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
Route::get('/assignments/dashboard', [AssignmentController::class, 'dashboard'])->name('assignments.dashboard')->middleware(['auth', 'role:instructor,admin']);
Route::get('/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create')->middleware(['auth', 'role:instructor,admin']);
Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store')->middleware(['auth', 'role:instructor,admin']);
Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
Route::get('/assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit')->middleware(['auth', 'role:instructor,admin']);
Route::put('/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update')->middleware(['auth', 'role:instructor,admin']);
Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy')->middleware(['auth', 'role:instructor,admin']);
Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('assignments.submit');
Route::put('/assignment-submissions/{submission}/update', [AssignmentController::class, 'updateSubmission'])->name('assignments.update-submission');
Route::post('/assignment-submissions/{submission}/grade', [AssignmentController::class, 'grade'])->name('assignments.grade')->middleware(['auth', 'role:instructor,admin']);

// Content feedback routes
Route::get('/contents/{content}/feedback', [ContentController::class, 'showFeedbackForm'])->name('contents.feedback');
Route::post('/contents/{content}/feedback', [ContentController::class, 'storeFeedback'])->name('contents.store-feedback');

// Curriculum — student read-only views
Route::middleware('auth')->group(function () {
    Route::get('/courses/{course}/curriculum',      [CurriculumController::class, 'show'])->name('curriculum.show');
    Route::get('/courses/{course}/grades',          [StudentGradeController::class, 'show'])->name('grades.show');
    Route::get('/courses/{course}/modules/{module}/feedback', [ModuleFeedbackController::class, 'show'])->name('module-feedback.show');
    Route::post('/courses/{course}/modules/{module}/feedback', [ModuleFeedbackController::class, 'store'])->name('module-feedback.store');
});

// Curriculum & grades — admin/instructor management
Route::middleware(['auth', 'role:admin,instructor'])->group(function () {
    Route::resource('modules', ModuleController::class);
    Route::resource('sessions', SessionController::class)->except(['index']);

    Route::get('/courses/{course}/curriculum/manage',           [CurriculumController::class, 'admin'])->name('curriculum.admin');
    Route::post('/courses/{course}/modules/attach',             [CurriculumController::class, 'attachModule'])->name('curriculum.attach-module');
    Route::post('/courses/{course}/modules/create-attach',      [CurriculumController::class, 'createAndAttachModule'])->name('curriculum.create-attach-module');
    Route::delete('/courses/{course}/modules/{module}/detach',  [CurriculumController::class, 'detachModule'])->name('curriculum.detach-module');
    Route::put('/courses/{course}/modules/{module}/settings',   [CurriculumController::class, 'updateModuleSettings'])->name('curriculum.update-module');
    Route::post('/courses/{course}/modules/{module}/end',       [CurriculumController::class, 'endModule'])->name('curriculum.end-module');
    Route::post('/lectures',                                    [LectureController::class, 'store'])->name('lectures.store');
    Route::get('/lectures/{lecture}/edit',                      [LectureController::class, 'edit'])->name('lectures.edit');
    Route::put('/lectures/{lecture}',                           [LectureController::class, 'update'])->name('lectures.update');
    Route::delete('/lectures/{lecture}',                        [LectureController::class, 'destroy'])->name('lectures.destroy');
    Route::post('/lecture-materials',                           [LectureMaterialController::class, 'store'])->name('lecture-materials.store');
    Route::delete('/lecture-materials/{material}',              [LectureMaterialController::class, 'destroy'])->name('lecture-materials.destroy');

    // Grades management
    Route::get('/courses/{course}/grades/manage',               [GradeCategoryController::class, 'admin'])->name('grades.admin');
    Route::get('/courses/{course}/grades/report',               [StudentGradeController::class, 'courseReport'])->name('grades.report');
    Route::post('/courses/{course}/grade-categories',           [GradeCategoryController::class, 'store'])->name('grade-categories.store');
    Route::put('/grade-categories/{category}',                  [GradeCategoryController::class, 'update'])->name('grade-categories.update');
    Route::delete('/grade-categories/{category}',               [GradeCategoryController::class, 'destroy'])->name('grade-categories.destroy');
    Route::post('/grade-categories/{category}/items',           [GradeItemController::class, 'store'])->name('grade-items.store');
    Route::put('/grade-items/{item}',                           [GradeItemController::class, 'update'])->name('grade-items.update');
    Route::delete('/grade-items/{item}',                        [GradeItemController::class, 'destroy'])->name('grade-items.destroy');
    Route::get('/grade-items/{item}/scores',                    [StudentGradeController::class, 'itemScores'])->name('grade-items.scores');
    Route::post('/grade-items/{item}/scores',                   [StudentGradeController::class, 'bulkSave'])->name('grade-items.scores.save');

    Route::get('/graduation',                                   [GraduationController::class, 'index'])->name('graduation.index');
    Route::get('/courses/{course}/graduation',                  [GraduationController::class, 'show'])->name('graduation.show');
});

// Superadmin routes — accessible only by users with is_superadmin = true
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/',                          [SuperAdminController::class, 'index'])->name('index');
    Route::get('/audit',                     [SuperAdminAuditController::class, 'index'])->name('audit.index');
    Route::post('/sessions/flush-all',       [SuperAdminController::class, 'flushAllSessions'])->name('sessions.flush-all');
    Route::post('/assignments',              [SuperAdminController::class, 'store'])->name('store');
    Route::delete('/assignments/{id}',       [SuperAdminController::class, 'destroy'])->name('destroy');
    Route::post('/roles',                    [SuperAdminController::class, 'storeRole'])->name('roles.store');
    Route::delete('/roles/{id}',             [SuperAdminController::class, 'destroyRole'])->name('roles.destroy');
    Route::post('/courses',                  [SuperAdminController::class, 'storeCourse'])->name('courses.store');
    Route::delete('/courses/{id}',           [SuperAdminController::class, 'destroyCourse'])->name('courses.destroy');
});

// Legacy URL redirects (old content/curriculum routes)
Route::middleware('auth')->group(function () {
    Route::redirect('/contents', '/curriculum');
    Route::redirect('/contents/create', '/curriculum');
    Route::get('/courses/{course}/content', fn (string $course) => redirect()->route('curriculum.show', $course));
    Route::get('/courses/{course}/content/manage', fn (string $course) => redirect()->route('curriculum.admin', $course));
});
