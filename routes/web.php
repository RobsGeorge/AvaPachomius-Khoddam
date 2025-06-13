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
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\AssignmentController;








Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';


Route::middleware(['auth'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('courses', CourseController::class);
    Route::resource('modules', ModuleController::class);
    Route::resource('contents', ContentController::class);
    Route::resource('sessions', SessionController::class);
    Route::resource('assessments', AssessmentController::class);
    Route::resource('course-assessments', CourseAssessmentController::class);
    Route::resource('user-assessments', UserAssessmentController::class);
    Route::resource('user-course-roles', UserCourseRoleController::class);
});

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

Route::post('otp/send', [OtpController::class, 'sendOtp'])->name('otp.send');


Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);

Route::get('/verify-otp', [OTPController::class, 'showForm'])->name('otp.verify');
Route::post('/verify-otp', [OTPController::class, 'verify']);
Route::post('/resend-otp', [OTPController::class, 'resend'])->name('otp.resend');

Route::get('/set-password/{user_id}', [RegisterController::class, 'showSetPasswordForm'])->name('password.set');
Route::post('/set-password', [RegisterController::class, 'storePassword'])->name('password.store');

Route::middleware(['auth', 'role:admin,instructor'])->group(function () {
    // Page that shows today's sessions after scanning QR
    Route::get('/attendance/sessions', [AttendanceController::class, 'showTodaySessions'])->name('attendance.sessions');

    // Record attendance for a given session
    Route::post('/attendance/record/{session}', [AttendanceController::class, 'recordAttendance'])->name('attendance.record');

    // View all attendance records
    Route::get('/attendance/all', [AttendanceController::class, 'viewAllAttendance'])->name('attendance.all');

    

    // Update attendance status
    Route::get('/attendance/update-status/{id}', [AttendanceController::class, 'updateStatus'])->name('attendance.update-status');

    // Update permission reason
    Route::get('/attendance/update-permission-reason/{id}', [AttendanceController::class, 'updatePermissionReason'])->name('attendance.update-permission-reason');
});

Route::get('/attendance/user-report/{userId}', [AttendanceController::class, 'userReport'])->name('attendance.user-report');

Auth::routes(['verify' => false]);  // disable default verify because we use custom OTP

Route::middleware('auth')->group(function () {
    Route::put('/profile/picture', [ProfileController::class, 'updatePicture'])->name('profile.picture.update');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [LoginController::class, 'showLoginForm'])->name('home');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    // View attendance records for a specific user
    Route::get('/attendance/user/{userId}', [AttendanceController::class, 'viewUserAttendance'])->name('attendance.user');
    
    // Regular user attendance view
    Route::get('/attendance/my', [AttendanceController::class, 'viewMyAttendance'])->name('attendance.my');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users/unverified', [UserManagementController::class, 'index'])->name('admin.users.unverified');
    Route::post('/users/{id}/approve', [UserManagementController::class, 'approve'])->name('admin.users.approve');
});

Route::get('/attendance/mark/{user_id}', [AttendanceController::class, 'mark'])->name('attendance.mark')->middleware('auth');

Route::get('/attendance/date/{date}', [AttendanceController::class, 'viewAttendanceByDate'])->name('attendance.by-date');

Route::post('/attendance/{id}/status', [AttendanceController::class, 'updateStatus'])->name('attendance.update-status');

// Exam routes
Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
Route::get('/exams/dashboard', [ExamController::class, 'dashboard'])->name('exams.dashboard');
Route::get('/exams/admin-dashboard', [ExamController::class, 'adminDashboard'])->name('exams.admin-dashboard');
Route::post('/exams', [ExamController::class, 'store'])->name('exams.store')->middleware(['auth', 'role:instructor,admin']);
Route::put('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update')->middleware(['auth', 'role:instructor,admin']);
Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy')->middleware(['auth', 'role:instructor,admin']);
Route::post('/exams/{exam}/schedule', [ExamController::class, 'scheduleExam'])->name('exams.schedule')->middleware(['auth', 'role:instructor,admin']);
Route::put('/exam-results/{result}', [ExamController::class, 'updateResult'])->name('exam-results.update')->middleware(['auth', 'role:instructor,admin']);

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
