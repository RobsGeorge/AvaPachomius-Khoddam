<?php

namespace App\Models;

use App\Tenancy\BelongsToChurch;
use App\Tenancy\StampsMainChurchWhenDormant;
use Illuminate\Database\Eloquent\Model;

class MoneyIn extends Model
{
    use BelongsToChurch;
    use StampsMainChurchWhenDormant;

    protected $table = 'money_in';

    protected $primaryKey = 'money_in_id';

    public function getRouteKeyName(): string
    {
        return 'money_in_id';
    }

    protected $fillable = [
        'source',
        'category',
        'amount_minor',
        'currency',
        'fx_rate',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'fx_rate' => 'string',
        'received_at' => 'date',
    ];
}
