<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * F-06 — serves the authenticated user's personal calendar (sessions, exams,
 * events) as a downloadable iCalendar (.ics) file.
 */
class CalendarController extends Controller
{
    public function download(CalendarService $calendar): Response
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        $ics = $calendar->icsForUser($user);
        $filename = 'khedma-calendar-'.$user->user_id.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
