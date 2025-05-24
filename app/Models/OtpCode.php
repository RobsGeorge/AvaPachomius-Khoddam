<?php
// app/Models/OtpCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public $timestamps = false;

    protected $table = 'otp_code';

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'code', 'expires_at'];

    protected $dates = ['created_at'];
}
    
        
?>