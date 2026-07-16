<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-church feature switch (T2). See config/capabilities.php for the catalog.
 */
class ChurchCapability extends Model
{
    protected $table = 'church_capability';

    protected $primaryKey = 'church_capability_id';

    public $timestamps = false;

    protected $fillable = ['church_id', 'capability_key', 'enabled', 'config'];

    protected $casts = ['enabled' => 'boolean', 'config' => 'array'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
