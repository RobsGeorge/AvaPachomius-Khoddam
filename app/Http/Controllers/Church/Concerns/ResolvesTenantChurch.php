<?php

namespace App\Http\Controllers\Church\Concerns;

use App\Models\Church;
use App\Tenancy\TenantContext;

trait ResolvesTenantChurch
{
    protected function resolveChurch(): Church
    {
        return TenantContext::current() ?? Church::main();
    }
}
