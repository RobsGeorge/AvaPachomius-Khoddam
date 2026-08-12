<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoData;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * Populate a staging / local environment with the demo dataset. Guarded: refuses to run
 * on production and unless DEMO_SEED_ENABLED=true (config('demo.enabled')). Use --force to
 * override the guards, --fresh to wipe any existing demo data first.
 */
class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed {--fresh : Wipe existing demo data before seeding} {--force : Bypass environment/enabled guards and confirmation}';

    protected $description = 'Seed representative demo data (churches, services, priests, courses, students, admins, roles) for staging/testing.';

    public function handle(): int
    {
        if (! $this->passesGuards()) {
            return self::FAILURE;
        }

        if (DemoData::exists()) {
            if ($this->option('fresh')) {
                $this->warn('Existing demo data found — wiping it first (--fresh).');
                DemoData::wipe();
            } else {
                $this->error('Demo data already exists. Re-run with --fresh to wipe and reseed, or run demo:wipe.');

                return self::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm('Seed demo data into ['.App::environment().']?', true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $seeder = app(DemoDataSeeder::class);
        $seeder->setContainer(app());
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('Demo data seeded. Login credentials (shared password shown per row):');
        $this->reportCredentials($seeder->credentials);

        return self::SUCCESS;
    }

    private function passesGuards(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (App::environment('production')) {
            $this->error('Refusing to seed demo data on production. (Use --force only if you are certain.)');

            return false;
        }

        if (! config('demo.enabled')) {
            $this->error('Demo seeding is disabled. Set DEMO_SEED_ENABLED=true in this environment\'s .env and run `php artisan config:clear`.');

            return false;
        }

        return true;
    }

    /** @param list<array{church: string, role: string, email: string, password: string}> $rows */
    private function reportCredentials(array $rows): void
    {
        if (empty($rows)) {
            $this->warn('No credentials were produced (demo data may already have existed).');

            return;
        }

        $this->table(['Church', 'Role', 'Email', 'Password'], $rows);
        $this->line($this->loginHints());

        // Also persist a copy you can open later.
        $markdown = $this->credentialsMarkdown($rows);
        Storage::disk('local')->put('demo-credentials.md', $markdown);
        $this->info('A copy was written to storage/app/demo-credentials.md');
    }

    private function loginHints(): string
    {
        $base = config('tenancy.base_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: '<your-base-domain>';
        $console = config('tenancy.console_host');

        $lines = [PHP_EOL.'Where to log in:'];
        $lines[] = "  • St Mark church portal:  https://".DemoData::slug('stmark').".{$base}";
        $lines[] = "  • St George church portal: https://".DemoData::slug('stgeorge').".{$base}";
        if ($console) {
            $lines[] = "  • Superadmin console:      https://{$console}";
        }
        $lines[] = '  (Subdomains require wildcard DNS + a wildcard TLS cert for *.'.$base.' — see docs/demo-data.md.)';

        return implode(PHP_EOL, $lines);
    }

    /** @param list<array{church: string, role: string, email: string, password: string}> $rows */
    private function credentialsMarkdown(array $rows): string
    {
        $out = "# Demo credentials\n\nGenerated ".now()->toDateTimeString()."\n\n";
        $out .= "| Church | Role | Email | Password |\n|---|---|---|---|\n";
        foreach ($rows as $r) {
            $out .= "| {$r['church']} | {$r['role']} | {$r['email']} | {$r['password']} |\n";
        }

        return $out.rtrim($this->loginHints())."\n";
    }
}
