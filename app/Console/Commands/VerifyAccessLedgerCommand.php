<?php

namespace App\Console\Commands;

use App\Services\AccessLedger\AccessLedgerRepository;
use Illuminate\Console\Command;

class VerifyAccessLedgerCommand extends Command
{
    protected $signature = 'ledger:verify';

    protected $description = 'Walk the access_ledger hash chain and report the first break';

    public function handle(AccessLedgerRepository $ledger): int
    {
        $result = $ledger->verifyChain();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);
        if ($result['broken_at_id'] !== null) {
            $this->line('First broken row: access_ledger_id='.$result['broken_at_id']);
        }

        return self::FAILURE;
    }
}
