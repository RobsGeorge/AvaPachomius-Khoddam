<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * Remove every row created by demo:seed, by marker only (demo church ids, demo user emails,
 * their tokens). Real data is never touched. Guarded on production unless --force.
 */
class DemoWipeCommand extends Command
{
    protected $signature = 'demo:wipe {--force : Bypass the production guard and confirmation}';

    protected $description = 'Remove all demo data (churches with the demo slug prefix, users in the demo email domain, and their scoped rows).';

    public function handle(): int
    {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run on production without --force.');

            return self::FAILURE;
        }

        if (! DemoData::exists()) {
            $this->info('No demo data found — nothing to wipe.');

            return self::SUCCESS;
        }

        $churches = DemoData::churchIds()->count();
        $users = DemoData::userIds()->count();

        if (! $this->option('force')
            && ! $this->confirm("This will delete {$churches} demo church(es) and {$users} demo user(s) and all their data. Continue?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = DemoData::wipe();

        $this->info('Demo data removed:');
        $this->table(
            ['Scope', 'Rows deleted'],
            collect($deleted)->map(fn ($n, $k) => [$k, $n])->values()->all()
        );

        return self::SUCCESS;
    }
}
