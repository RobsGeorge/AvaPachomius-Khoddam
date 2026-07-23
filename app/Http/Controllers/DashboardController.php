<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\CourseContextService;
use App\Services\DashboardService;
use App\Services\NotificationFeedService;
use App\Services\StudentRosterService;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(
        StudentRosterService $rosterService,
        AnnouncementService $announcementService,
        NotificationFeedService $notificationFeed,
        CourseContextService $courseContext,
        DashboardService $dashboard,
    ) {
        $todayBirthdays = $this->todaysBirthdays($rosterService);
        $homepageAnnouncements = collect();
        $user = auth()->user();
        $currentCourse = current_course();

        if ($user instanceof User && $user->isStudent()) {
            $homepageAnnouncements = $announcementService->homepageAnnouncements($user);
            if ($currentCourse) {
                $homepageAnnouncements = $homepageAnnouncements->filter(
                    fn ($announcement) => $announcement->course_id === null
                        || (int) $announcement->course_id === (int) $currentCourse->course_id
                )->values();
            }
        }

        $unreadNotificationBadge = $user instanceof User
            ? $notificationFeed->unreadBadgeLabel($user)
            : '';

        $completedCourses = $user instanceof User && $user->isStudent()
            ? $this->announcedCoursesForStudent($user, $currentCourse)
            : collect();

        $showNoCoursesCta = $user instanceof User
            && $courseContext->requiresCourseContext($user)
            && $courseContext->selectableCourses($user)->isEmpty();

        $focusCards = $user instanceof User ? $dashboard->focusCards($user) : [];

        return view('dashboard', compact(
            'todayBirthdays',
            'homepageAnnouncements',
            'unreadNotificationBadge',
            'completedCourses',
            'showNoCoursesCta',
            'focusCards',
        ));
    }

    private function todaysBirthdays(StudentRosterService $rosterService): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $currentCourse = current_course();
        $todayBirthdays = collect();

        if ($currentCourse) {
            return $rosterService->studentsWithBirthdayToday(
                $rosterService->enrolledStudents($currentCourse)
            )->unique('user_id')->values();
        }

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

    private function announcedCoursesForStudent(User $user, ?Course $currentCourse = null): Collection
    {
        $studentCourseIds = app(StudentRosterService::class)
            ->studentEnrolledCourses($user)
            ->pluck('course_id');

        if ($currentCourse) {
            $studentCourseIds = $studentCourseIds->filter(
                fn ($id) => (int) $id === (int) $currentCourse->course_id
            );
        }

        return Course::query()
            ->whereNotNull('grades_announced_at')
            ->whereIn('course_id', $studentCourseIds)
            ->orderByDesc('grades_announced_at')
            ->get();
    }
}
