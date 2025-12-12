<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class OfferProduct extends Model
{
    use HasFactory;

    protected $table = 'offer_products'; // Ensure this matches your table name
    protected $fillable = [
        'offer_id',
        'part_no',
        'product_id',
        'name',
        'mrp',
        'offer_price',
        'min_qty',
        'discount_type',
        'offer_discount_percent',
        'created_at',
        'updated_at',
    ];
    // If needed, specify the primary key
    protected $primaryKey = 'id';

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'offer_id', 'offer_id');
    }

    public function productDetails()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function reviews() {
        return $this->hasMany(Review::class,'product_id')->where('status', 1);
    }
     
}
