<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationReviewTemplate extends Model
{
    public const KEY_RECEIVED = 'application_received';

    public const KEY_APPROVED = 'application_approved';

    public const KEY_NEEDS_CORRECTION = 'application_needs_correction';

    public const KEY_REJECTED = 'application_rejected';

    protected $fillable = [
        'template_key',
        'locale',
        'subject',
        'body_html',
    ];

    public static function keys(): array
    {
        return [
            self::KEY_RECEIVED,
            self::KEY_APPROVED,
            self::KEY_NEEDS_CORRECTION,
            self::KEY_REJECTED,
        ];
    }

    public static function placeholders(): array
    {
        return ['name', 'fields_table', 'note', 'portal_url', 'correction_url'];
    }
}
