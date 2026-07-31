<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnsureSchedulerCronCommand extends Command
{
    protected $signature = 'scheduler:ensure-cron
        {--php=php8.2 : PHP binary used by cron}
        {--dry-run : Print the cron line without writing crontab}
        {--force : Rewrite the matching schedule:run line even if one already exists}';

    protected $description = 'Ensure the OS crontab runs php artisan schedule:run every minute for this app';

    public function handle(): int
    {
        $line = $this->buildCronLine((string) $this->option('php'));

        if ($this->option('dry-run')) {
            $this->line($line);

            return self::SUCCESS;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->error('Crontab installation is only supported on Linux. Use --dry-run to preview the line.');

            return self::FAILURE;
        }

        if (! $this->crontabAvailable()) {
            $this->error('crontab command is not available on this host.');

            return self::FAILURE;
        }

        $existing = $this->currentCrontab();

        $lines = array_values(array_filter(
            preg_split("/\r\n|\n|\r/", $existing) ?: [],
            fn (string $row) => trim($row) !== ''
        ));

        // Match quoted (escapeshellarg) and legacy unquoted `cd /path &&` lines.
        $withoutMarker = array_values(array_filter(
            $lines,
            fn (string $row) => ! $this->isExistingSchedulerLine($row)
        ));

        $alreadyPresent = count($withoutMarker) !== count($lines);

        if ($alreadyPresent && ! $this->option('force')) {
            $this->info('Scheduler cron already installed.');
            $this->line($line);

            return self::SUCCESS;
        }

        $withoutMarker[] = $line;
        $payload = implode("\n", $withoutMarker)."\n";

        if (! $this->writeCrontab($payload)) {
            $this->error('Failed to write crontab.');

            return self::FAILURE;
        }

        $this->info($alreadyPresent ? 'Scheduler cron updated.' : 'Scheduler cron installed.');
        $this->line($line);

        return self::SUCCESS;
    }

    public function buildCronLine(string $php = 'php8.2', ?string $basePath = null, ?string $logPath = null): string
    {
        $base = $basePath ?? base_path();
        $log = $logPath ?? storage_path('logs/scheduler-cron.log');

        return sprintf(
            '* * * * * cd %s && %s artisan schedule:run >> %s 2>&1',
            $this->shellEscape($base),
            $this->shellEscape($php),
            $this->shellEscape($log)
        );
    }

    public function cronLine(): string
    {
        return $this->buildCronLine((string) $this->option('php'));
    }

    public function cronMarker(): string
    {
        return 'cd '.$this->shellEscape(base_path()).' &&';
    }

    /**
     * True when a crontab row already schedules this app's artisan schedule:run,
     * whether the path is shell-quoted or legacy-unquoted.
     */
    public function isExistingSchedulerLine(string $row): bool
    {
        $base = base_path();
        if (! str_contains($row, 'artisan schedule:run')) {
            return false;
        }

        return str_contains($row, 'cd '.$this->shellEscape($base).' &&')
            || str_contains($row, 'cd '.$base.' &&')
            || str_contains($row, 'cd "'.$base.'" &&');
    }

    private function shellEscape(string $value): string
    {
        return escapeshellarg($value);
    }

    private function crontabAvailable(): bool
    {
        $whiches = [];
        exec('command -v crontab 2>/dev/null', $whiches, $code);

        return $code === 0 && ! empty($whiches);
    }

    private function currentCrontab(): string
    {
        $output = [];
        $code = 0;
        exec('crontab -l 2>/dev/null', $output, $code);

        if ($code !== 0) {
            return '';
        }

        return implode("\n", $output);
    }

    private function writeCrontab(string $payload): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'khedma-cron-');
        if ($tmp === false) {
            return false;
        }

        file_put_contents($tmp, $payload);
        $code = 0;
        exec('crontab '.escapeshellarg($tmp).' 2>&1', $out, $code);
        @unlink($tmp);

        if ($code !== 0) {
            foreach ($out as $line) {
                $this->error($line);
            }

            return false;
        }

        return true;
    }
}
