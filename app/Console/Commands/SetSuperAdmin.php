<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetSuperAdmin extends Command
{
    protected $signature = 'superadmin:set {email} {--revoke : Remove superadmin status instead of granting it}';

    protected $description = 'Grant or revoke superadmin status for a user by email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $revoke = $this->option('revoke');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $user->is_superadmin = !$revoke;
        $user->save();

        $name = "{$user->first_name} {$user->second_name}";

        if ($revoke) {
            $this->info("Superadmin status removed from: {$name} ({$email})");
        } else {
            $this->info("Superadmin status granted to: {$name} ({$email})");
            $this->line("They can now access /superadmin after logging in.");
        }

        return self::SUCCESS;
    }
}
