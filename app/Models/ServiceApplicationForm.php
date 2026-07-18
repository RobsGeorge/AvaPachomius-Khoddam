<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceApplicationForm extends Model
{
    use BelongsToChurch;

    protected $table = 'service_application_forms';

    protected $primaryKey = 'service_application_form_id';

    protected $fillable = [
        'service_id',
        'title',
        'instructions',
        'default_role_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ChurchService::class, 'service_id', 'service_id');
    }

    public function defaultRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'default_role_id', 'role_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ServiceApplication::class, 'form_id', 'service_application_form_id');
    }
}
