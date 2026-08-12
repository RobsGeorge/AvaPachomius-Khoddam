<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfraSample extends Model
{
    protected $table = 'infra_samples';

    protected $fillable = [
        'sampled_at',
        'host',
        'load_1',
        'load_5',
        'cpu_pct',
        'mem_used_mb',
        'mem_total_mb',
        'disk_used_pct',
        'php_fpm_active',
        'source',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'load_1' => 'float',
        'load_5' => 'float',
        'cpu_pct' => 'float',
        'mem_used_mb' => 'float',
        'mem_total_mb' => 'float',
        'disk_used_pct' => 'float',
        'php_fpm_active' => 'integer',
    ];
}
