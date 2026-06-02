<?php

namespace App\Console\Commands;

use App\Database\LegacySchemaSync;
use Illuminate\Console\Command;

class MigrateDeploy extends Command
{
    protected $signature = 'migrate:deploy {--force : Force the operation to run when in production}';

    protected $description = 'Sync legacy schema, then run pending migrations (production deploy)';

    public function handle(): int
    {
        $this->info('Syncing legacy schema (primary keys and missing columns)...');
        LegacySchemaSync::syncAll();

        $options = [];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        return $this->call('migrate', $options);
    }
}
