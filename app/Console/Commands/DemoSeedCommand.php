<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Seed demonstration users, course, modules, exams, grades, and attendance';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => DemoDatabaseSeeder::class, '--force' => true]);
        $this->info('Demo data seeded. Enable DEMO_ENABLED=true and visit /demo');

        return self::SUCCESS;
    }
}
