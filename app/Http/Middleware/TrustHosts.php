<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted (T4 — wildcard + console host).
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $hosts = [
            $this->allSubdomainsOfApplicationUrl(),
        ];

        $console = config('tenancy.console_host');
        if (filled($console)) {
            $hosts[] = '^'.preg_quote($console, '/').'$';
        }

        return $hosts;
    }
}
