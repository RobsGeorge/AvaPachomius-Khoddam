<?php

namespace App\Console\Commands;

use App\Services\Maturity\EmancipationService;
use Illuminate\Console\Command;

class EmancipateAtMajorityCommand extends Command
{
    protected $signature = 'maturity:emancipate-at-majority';

    protected $description = 'End active guardian_of edges when the ward reaches organization age_of_majority (history, not deletion).';

    public function handle(EmancipationService $emancipation): int
    {
        $result = $emancipation->run();

        $this->info(sprintf(
            'Emancipation scan complete: ended=%d skipped=%d',
            $result['ended'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
