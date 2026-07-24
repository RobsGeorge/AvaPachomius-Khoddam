<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BirthdayController;
use App\Http\Controllers\Api\V1\CertificateController;
use App\Http\Controllers\Api\V1\CourseApplicationController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CurriculumController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DesignTokensController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationSettingsController;
use App\Http\Controllers\Api\V1\RegistrationApplicationController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Mobile and machine clients use Sanctum bearer tokens under /api/v1.
| Legacy /api/user remains for Sanctum scaffolding compatibility.
|
| Student feature matrix: docs/mobile/student-feature-matrix.md
|
*/

Route::middleware(['auth:sanctum', 'church.member'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::get('/design-tokens', [DesignTokensController::class, 'show']);

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // church.member runs AFTER auth:sanctum so the token user is resolved and the
    // membership gate actually enforces (see Kernel `api` group note). Without it, a
    // valid token from one church could read another church's church-wide data by
    // hitting that church's host.
    Route::middleware(['auth:sanctum', 'church.member'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        // Profile / prefs (Wave A/B)
        Route::get('/me', [MeController::class, 'show']);
        Route::put('/me/preferences', [MeController::class, 'updatePreferences']);
        Route::post('/me/picture', [MeController::class, 'updatePicture']);
        Route::get('/dashboard', [DashboardController::class, 'show']);

        // Notifications (Wave A/E)
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->whereNumber('notification');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::get('/notification-settings', [NotificationSettingsController::class, 'show']);
        Route::put('/notification-settings', [NotificationSettingsController::class, 'update']);

        // Announcements (Wave A)
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement');
        Route::post('/announcements/{announcement}/dismiss-banner', [AnnouncementController::class, 'dismissBanner'])
            ->whereNumber('announcement');

        // Attendance (Wave A)
        Route::get('/attendance/mine', [AttendanceController::class, 'mine']);

        // Courses / academic reads (Wave B)
        Route::get('/courses', [CourseController::class, 'index']);
        Route::get('/courses/current', [CourseController::class, 'current']);
        Route::post('/courses/current', [CourseController::class, 'setCurrent']);
        Route::delete('/courses/current', [CourseController::class, 'clearCurrent']);
        Route::get('/courses/{course}', [CourseController::class, 'show'])->whereNumber('course');
        Route::get('/courses/{course}/curriculum', [CurriculumController::class, 'show'])->whereNumber('course');
        Route::get('/courses/{course}/sessions', [SessionController::class, 'index'])->whereNumber('course');
        Route::get('/courses/{course}/grades', [GradeController::class, 'show'])->whereNumber('course');
        Route::get('/courses/{course}/final-grades', [GradeController::class, 'finalGrades'])->whereNumber('course');
        Route::get('/courses/{course}/assignments', [AssignmentController::class, 'index'])->whereNumber('course');
        Route::get('/courses/{course}/exams', [ExamController::class, 'index'])->whereNumber('course');
        Route::get('/courses/{course}/application', [CourseApplicationController::class, 'status'])->whereNumber('course');

        // Assignments (Wave C)
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->whereNumber('assignment');
        Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->whereNumber('assignment');
        Route::post('/submissions/{submission}', [AssignmentController::class, 'updateSubmission'])->whereNumber('submission');

        // Certificates (Wave B)
        Route::get('/certificates', [CertificateController::class, 'index']);
        Route::get('/certificates/{uuid}', [CertificateController::class, 'download']);

        // Events (Wave D)
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/mine', [EventController::class, 'myReservations']);
        Route::get('/events/{event}', [EventController::class, 'show'])->whereNumber('event');
        Route::post('/events/{event}/reserve', [EventController::class, 'reserve'])->whereNumber('event');
        Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->whereNumber('event');

        // Applications / services (Wave D)
        Route::get('/available-courses', [CourseApplicationController::class, 'available']);
        Route::get('/registration-application', [RegistrationApplicationController::class, 'status']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services/{service}/apply', [ServiceController::class, 'apply'])->whereNumber('service');
        Route::get('/services/{service}/application', [ServiceController::class, 'applicationStatus'])->whereNumber('service');

        // Feedback (Wave C)
        Route::get('/feedback/surveys', [FeedbackController::class, 'index']);
        Route::get('/feedback/surveys/{survey}', [FeedbackController::class, 'show'])->whereNumber('survey');
        Route::post('/feedback/surveys/{survey}/submit', [FeedbackController::class, 'submit'])->whereNumber('survey');

        // Birthdays (Wave D)
        Route::get('/birthdays', [BirthdayController::class, 'index']);
    });
});
