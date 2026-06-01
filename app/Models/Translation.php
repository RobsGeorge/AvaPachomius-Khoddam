<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = ['group', 'key', 'locale', 'value'];

    public static function cacheKey(string $locale): string
    {
        return "translations.db.{$locale}";
    }
}
