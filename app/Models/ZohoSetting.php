<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoSetting extends Model
{
    protected $fillable = ['client_id', 'client_secret', 'redirect_uri', 'organization_id'];
}


?>