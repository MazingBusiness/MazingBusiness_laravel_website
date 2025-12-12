<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferCombination extends Model
{
    use HasFactory;

    // Define the table name if it doesn't follow Laravel's convention

    // Specify the fields that are mass assignable
    protected $fillable = [
        'offer_id',
        'free_product_part_no',
        'product_id',
        'free_product_qty',
    ];

    public function offerComplementoryProductDetails()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

   
}
