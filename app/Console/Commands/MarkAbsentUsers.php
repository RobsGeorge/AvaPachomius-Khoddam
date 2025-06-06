<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Session;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class MarkAbsentUsers extends Command
{
    protected $signature = 'attendance:mark-absent {session_id?}';
    protected $description = 'Mark users as absent for sessions where they have no attendance record';

    public function handle()
    {
        $sessionId = $this->argument('session_id');
        
        if ($sessionId) {
            $sessions = Session::where('session_id', $sessionId)->get();
        } else {
            // Get all sessions from today backwards
            $sessions = Session::where('session_date', '<=', Carbon::today())->get();
        }

        foreach ($sessions as $session) {
            $this->info("Processing session: {$session->session_date}");
            
            // Get all users
            $users = User::all();
            
            // Get existing attendance records for this session
            $existingAttendance = Attendance::where('session_id', $session->session_id)
                ->pluck('user_id')
                ->toArray();
            
            // Find users without attendance records
            $absentUsers = $users->filter(function ($user) use ($existingAttendance) {
                return !in_array($user->id, $existingAttendance);
            });
            
            // Create absent records
            $absentRecords = $absentUsers->map(function ($user) use ($session) {
                return [
                    'user_id' => $user->id,
                    'session_id' => $session->session_id,
                    'status' => 'Absent',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();
            
            if (!empty($absentRecords)) {
                // Insert absent records in chunks to avoid memory issues
                foreach (array_chunk($absentRecords, 100) as $chunk) {
                    Attendance::insert($chunk);
                }
                
                $this->info("Marked {$absentUsers->count()} users as absent for session {$session->session_date}");
            } else {
                $this->info("No absent users to mark for session {$session->session_date}");
            }
        }
        
        $this->info('Process completed successfully!');
    }
} 