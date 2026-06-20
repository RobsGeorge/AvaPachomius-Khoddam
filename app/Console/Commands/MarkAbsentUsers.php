<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Services\AttendanceCloseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkAbsentUsers extends Command
{
    protected $signature = 'attendance:mark-absent {session_id?} {--admin-id=}';

    protected $description = 'Close session attendance and mark missing enrolled students as absent';

    public function handle(AttendanceCloseService $service): int
    {
        $sessionId = $this->argument('session_id');
        $closedByUserId = $this->option('admin-id') ?: config('attendance.system_user_id');

        if (! $closedByUserId) {
            $this->error('Provide --admin-id or set ATTENDANCE_SYSTEM_USER_ID in the environment.');

            return 1;
        }

        $closedByUserId = (int) $closedByUserId;

        if ($sessionId) {
            $session = Session::find($sessionId);

            if (! $session) {
                $this->error("Session {$sessionId} not found.");

                return 1;
            }

            $result = $service->closeSession($session, $closedByUserId);

            if ($result['already_closed']) {
                $this->warn("Session {$sessionId} attendance was already closed.");

                return 0;
            }

            $this->info("Session {$sessionId} closed. Marked {$result['absent_marked']} students absent.");

            return 0;
        }

        $timezone = config('attendance.timezone', 'Africa/Cairo');
        $yesterday = Carbon::now($timezone)->subDay()->startOfDay();
        $totalAbsent = $service->closeSessionsForDate($yesterday, $closedByUserId);

        $this->info("Closed open sessions for {$yesterday->toDateString()}. Marked {$totalAbsent} absent records.");

        return 0;
    }
}
