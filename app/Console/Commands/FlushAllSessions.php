<?php

namespace App\Console\Commands;

use App\Services\ForceLogoutService;
use Illuminate\Console\Command;

class FlushAllSessions extends Command
{
    protected $signature = 'sessions:flush-all {--include-me : Also log out the current shell/session context if applicable}';

    protected $description = 'Force logout all users by clearing stored sessions and remember-me tokens';

    public function handle(): int
    {
        if (! $this->confirm('This will log out every user on the platform. Continue?', false)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $result = ForceLogoutService::logoutAllUsers();

        $this->info("Session driver: {$result['driver']}");
        $this->info("Sessions cleared: {$result['sessions_cleared']}");
        $this->info("Remember-me tokens cleared: {$result['remember_tokens_cleared']}");

        return self::SUCCESS;
    }
}
