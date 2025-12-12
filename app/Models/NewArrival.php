<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewArrival extends Model
{
    use HasFactory;

    protected $table = 'new_arrivals'; 

    protected $primaryKey = 'id'; 

    public $timestamps = true; 

    protected $fillable = [
        'user_id',
        'file_id',
        'file_url',
    ];
}
