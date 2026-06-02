<?php

namespace App\Console\Commands;

use App\Database\LegacyPrimaryKeys;
use App\Database\LegacySchemaPatches;
use Illuminate\Console\Command;

class MigrateDeploy extends Command
{
    protected $signature = 'migrate:deploy {--force : Force the operation to run when in production}';

    protected $description = 'Normalize legacy schema, then run pending migrations (production deploy)';

    public function handle(): int
    {
        $this->info('Normalizing legacy primary key columns...');
        LegacyPrimaryKeys::normalizeAll();

        $options = [];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        return $this->call('migrate', $options);
    }
}
