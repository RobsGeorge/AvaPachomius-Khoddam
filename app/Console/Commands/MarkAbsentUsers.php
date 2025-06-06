<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Session;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class MarkAbsentUsers extends Command
{
    protected $signature = 'attendance:mark-absent {session_id?} {--admin-id=}';
    protected $description = 'Mark users as absent for sessions where they have no attendance record';

    public function handle()
    {
        $sessionId = $this->argument('session_id');
        $adminId = $this->option('admin-id');
        
        if (!$adminId) {
            $this->error('Please provide an admin ID using --admin-id option');
            return 1;
        }
        
        if ($sessionId) {
            $sessions = Session::where('session_id', $sessionId)->get();
        } else {
            // Get all sessions from today backwards
            $sessions = Session::where('session_date', '<=', Carbon::today())->get();
        }

        foreach ($sessions as $session) {
            $this->info("Processing session: {$session->session_date}");
            
            // Get all active users
            $users = User::whereNotNull('user_id')->get();
            
            if ($users->isEmpty()) {
                $this->warn("No users found to process for session {$session->session_date}");
                continue;
            }
            
            // Get existing attendance records for this session
            $existingAttendance = Attendance::where('session_id', $session->session_id)
                ->pluck('user_id')
                ->toArray();
            
            // Find users without attendance records
            $absentUsers = $users->filter(function ($user) use ($existingAttendance) {
                return $user->user_id && !in_array($user->user_id, $existingAttendance);
            });
            
            if ($absentUsers->isEmpty()) {
                $this->info("No absent users to mark for session {$session->session_date}");
                continue;
            }
            
            // Create absent records
            $absentRecords = $absentUsers->map(function ($user) use ($session, $adminId) {
                if (!$user->user_id) {
                    $this->warn("Skipping user with null ID");
                    return null;
                }
                
                return [
                    'user_id' => $user->user_id,
                    'session_id' => $session->session_id,
                    'status' => 'Absent',
                    'taken_by_id' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter() // Remove any null records
            ->values() // Re-index the array
            ->toArray();
            
            if (!empty($absentRecords)) {
                try {
                    // Insert absent records in chunks to avoid memory issues
                    foreach (array_chunk($absentRecords, 100) as $chunk) {
                        Attendance::insert($chunk);
                    }
                    
                    $this->info("Marked {$absentUsers->count()} users as absent for session {$session->session_date}");
                } catch (\Exception $e) {
                    $this->error("Error marking users as absent: " . $e->getMessage());
                }
            } else {
                $this->info("No valid absent records to create for session {$session->session_date}");
            }
        }
        
        $this->info('Process completed successfully!');
        return 0;
    }
} 