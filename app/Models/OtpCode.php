<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $table      = 'otp_code';
    protected $primaryKey = 'user_id';
    public    $incrementing = false;   // user_id is a FK, not auto-increment
    protected $keyType    = 'integer';
    public    $timestamps = false;

    protected $fillable = ['user_id', 'code', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
