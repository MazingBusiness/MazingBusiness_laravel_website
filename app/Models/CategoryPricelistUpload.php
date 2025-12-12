<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryPricelistUpload extends Model
{
    use HasFactory;

    protected $table = 'category_pricelist_uploads'; // Table name

    protected $fillable = [
        'user_id',
        'file_id',
        'file_url',
    ];

    public $timestamps = true; 

    
}
