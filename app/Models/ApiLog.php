<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiLog extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['log', 'request', 'created_at', 'updated_at', 'api_name'];

}
