<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Module;

class Content extends Model
{

    protected $table = 'content';

    protected $primaryKey = 'content_id';

    protected $fillable = ['title', 'content_lcation'];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_content', 'content_id', 'module_id');
    }
}
