<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pincode extends Model {
    protected $fillable = ['pincode', 'city', 'state'];
    // Disable timestamps if not present in the database table
    public $timestamps = false;
}
