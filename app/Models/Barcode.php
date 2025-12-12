<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    use HasFactory;

    protected $table = 'barcodes';

    // Mass assignable fields
    protected $fillable = [
        'barcode',
        'is_warranty',
        'invoice_order_id',
        'invoice_order_details_id'
    ];

    public $timestamps = true;
}
