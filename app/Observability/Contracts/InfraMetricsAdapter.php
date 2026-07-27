<?php

namespace App\Observability\Contracts;

/**
 * Provider-agnostic host metrics. Implementations must not assume Hostinger.
 *
 * @phpstan-type InfraSample array{
 *     host: string,
 *     load_1: ?float,
 *     load_5: ?float,
 *     cpu_pct: ?float,
 *     mem_used_mb: ?float,
 *     mem_total_mb: ?float,
 *     disk_used_pct: ?float,
 *     php_fpm_active: ?int,
 *     source: string,
 * }
 */
interface InfraMetricsAdapter
{
    /**
     * @return InfraSample|null  Null when sampling is unavailable on this host.
     */
    public function sample(): ?array;
}
