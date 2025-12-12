<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MakePurchaseOrder extends Model
{
    use HasFactory;

      use HasFactory;

    protected $table = 'make_purchase_orders';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'purchase_order_no',
        'date',
        'seller_id',
        'seller_info',
        'product_invoice',
        'warehouse_id',
        'convert_to_purchase_status',
        'is_closed',
        'force_closed',
        'order_no'
    ];

    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'make_purchase_order_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
