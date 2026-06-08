<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Remove all demo-tagged data and re-seed fresh demonstration content';

    public function handle(DemoResetService $reset): int
    {
        $reset->wipeDemoData();
        $this->info('Demo data removed.');

        $this->call('db:seed', ['--class' => DemoDatabaseSeeder::class, '--force' => true]);
        $this->info('Demo data re-seeded.');

        return self::SUCCESS;
    }
}
