<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkAsLostItem extends Model
{
    protected $table = 'mark_as_lost_items';

    protected $fillable = [
        'part_no',
        'product_id',
        'item_name',
        'mark_as_lost_qty',
        'warehouse_id',
        'reason',
        'user_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
