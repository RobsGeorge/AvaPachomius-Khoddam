<?php

namespace App\Observability\Adapters;

use App\Observability\Contracts\InfraMetricsAdapter;

/**
 * Portable Linux sampler using /proc and disk free space. No cloud vendor APIs.
 */
class LocalProcFsAdapter implements InfraMetricsAdapter
{
    public function sample(): ?array
    {
        $host = gethostname() ?: 'unknown';

        $load1 = null;
        $load5 = null;
        if (is_readable('/proc/loadavg')) {
            $parts = preg_split('/\s+/', trim((string) file_get_contents('/proc/loadavg'))) ?: [];
            $load1 = isset($parts[0]) ? (float) $parts[0] : null;
            $load5 = isset($parts[1]) ? (float) $parts[1] : null;
        } elseif (function_exists('sys_getloadavg')) {
            $loads = @sys_getloadavg();
            if (is_array($loads)) {
                $load1 = isset($loads[0]) ? (float) $loads[0] : null;
                $load5 = isset($loads[1]) ? (float) $loads[1] : null;
            }
        }

        $memTotal = null;
        $memAvailable = null;
        if (is_readable('/proc/meminfo')) {
            $meminfo = (string) file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
                $memTotal = ((float) $m[1]) / 1024;
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
                $memAvailable = ((float) $m[1]) / 1024;
            }
        }

        $memUsed = ($memTotal !== null && $memAvailable !== null)
            ? max(0, $memTotal - $memAvailable)
            : null;

        $diskUsedPct = null;
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total && $free !== false && $total > 0) {
            $diskUsedPct = round((($total - $free) / $total) * 100, 2);
        }

        return [
            'host' => $host,
            'load_1' => $load1,
            'load_5' => $load5,
            'cpu_pct' => null,
            'mem_used_mb' => $memUsed,
            'mem_total_mb' => $memTotal,
            'disk_used_pct' => $diskUsedPct,
            'php_fpm_active' => null,
            'source' => 'local_proc',
        ];
    }
}
