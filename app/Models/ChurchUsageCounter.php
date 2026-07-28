<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchUsageCounter extends Model
{
    protected $table = 'church_usage_counter';

    protected $primaryKey = 'church_usage_counter_id';

    protected $fillable = ['church_id', 'feature_key', 'period_key', 'used_amount'];

    protected $casts = ['used_amount' => 'integer'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
