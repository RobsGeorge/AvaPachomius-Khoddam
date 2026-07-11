<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\NotificationFeedService;
use App\Services\StudentRosterService;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(
        StudentRosterService $rosterService,
        AnnouncementService $announcementService,
        NotificationFeedService $notificationFeed
    ) {
        $todayBirthdays = $this->todaysBirthdays($rosterService);
        $homepageAnnouncements = collect();
        $user = auth()->user();

        if ($user instanceof User && $user->isStudent()) {
            $homepageAnnouncements = $announcementService->homepageAnnouncements($user);
        }

        $unreadNotificationCount = $user instanceof User
            ? $notificationFeed->unreadCount($user)
            : 0;

        $completedCourses = $user instanceof User && $user->isStudent()
            ? $this->announcedCoursesForStudent($user)
            : collect();

        return view('dashboard', compact('todayBirthdays', 'homepageAnnouncements', 'unreadNotificationCount', 'completedCourses'));
    }

    private function todaysBirthdays(StudentRosterService $rosterService): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $todayBirthdays = collect();

        if ($user->isStudent() && ! $user->isInstructorOrAdmin()) {
            foreach ($rosterService->studentEnrolledCourses($user) as $course) {
                $todayBirthdays = $todayBirthdays->merge(
                    $rosterService->studentsWithBirthdayToday(
                        $rosterService->enrolledStudents($course)
                    )
                );
            }
        } elseif ($user->isInstructorOrAdmin()) {
            foreach ($rosterService->accessibleCourses($user) as $course) {
                $todayBirthdays = $todayBirthdays->merge(
                    $rosterService->studentsWithBirthdayToday(
                        $rosterService->enrolledStudents($course)
                    )
                );
            }
        }

        return $todayBirthdays->unique('user_id')->values();
    }

    private function announcedCoursesForStudent(User $user): Collection
    {
        $studentCourseIds = app(StudentRosterService::class)
            ->studentEnrolledCourses($user)
            ->pluck('course_id');

        return Course::query()
            ->whereNotNull('grades_announced_at')
            ->whereIn('course_id', $studentCourseIds)
            ->orderByDesc('grades_announced_at')
            ->get();
    }
}
