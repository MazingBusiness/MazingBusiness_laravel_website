<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41ResetInventoryProduct extends Model
{
    protected $table = 'manager_41_reset_inventory_products';

    // Primary key
    protected $primaryKey = 'id';
    public $incrementing = true;   // BIGINT auto-increment
    protected $keyType = 'int';

    // created_at / updated_at
    public $timestamps = true;

    // Mass-assignable fields
    protected $fillable = [
        'product_id',
        'part_no',
    ];

    /* -------------------------
     | Relationships (optional)
     * -------------------------*/

    // Link by product_id -> products.id
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // If you often link by part_no as well
    public function productByPartNo()
    {
        return $this->belongsTo(Product::class, 'part_no', 'part_no');
    }
}
