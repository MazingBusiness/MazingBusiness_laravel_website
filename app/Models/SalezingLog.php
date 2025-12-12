<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class SalezingLog extends Model {

  protected $with = [];

  protected $fillable = [
    'api_name','code','response','status','status_code','created_at','updated_at'
  ];
  
}
