<?php

namespace Tests\Unit\Events;

use App\Services\EventModuleTestRunner;
use Tests\Support\EventModuleTestCase;

class EventModuleTestRunnerTest extends EventModuleTestCase
{
    public function test_unavailable_reason_when_test_directory_missing(): void
    {
        $runner = new EventModuleTestRunner();

        $reason = $runner->unavailableReason('tests/does-not-exist');

        $this->assertNotNull($reason);
    }
}
