<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingWebhookEvent extends Model
{
    protected $table = 'billing_webhook_event';

    protected $primaryKey = 'billing_webhook_event_id';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload_hash',
        'processed_at',
        'error',
    ];

    protected $casts = ['processed_at' => 'datetime'];
}
