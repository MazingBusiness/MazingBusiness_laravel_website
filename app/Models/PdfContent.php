<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfContent extends Model
{
    // Table name (optional if you follow naming convention)
    protected $table = 'pdf_contents';

    // Allow mass assignment
    protected $fillable = [
        'pdf_type',
        'url',
        'content_type',
        'content_products',
        'placement_type',
        'no_of_poster',
    ];

    // Timestamps enable (default true, but explicitly likh diya)
    public $timestamps = true;
}
