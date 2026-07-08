<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentRosterService;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(StudentRosterService $rosterService)
    {
        $todayBirthdays = $this->todaysBirthdays($rosterService);

        return view('dashboard', compact('todayBirthdays'));
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
}
