<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_details';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'make_purchase_order_id',
        'seller_info',
        'part_no',
        'qty',
        'order_no',
        'age',
        'tax',
        'hsncode',
        'received',
        'pre_close',
        'pending',
        'purchase_order_no',
    ];

    public function makePurchaseOrder()
    {
        return $this->belongsTo(MakePurchaseOrder::class, 'make_purchase_order_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'part_no', 'part_no');
    }
}
