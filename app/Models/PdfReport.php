<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App;

class PdfReport extends Model
{
  protected $fillable = ['id ','user_id','filename', 'path', 'status', 'download_status', 'created_at', 'updated_at'];
}
