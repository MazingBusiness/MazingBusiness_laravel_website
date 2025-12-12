<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41CombinedOrder extends Model
{
    protected $table       = 'manager_41_combined_orders';
    protected $primaryKey  = 'id';
    public    $incrementing = true;   // BIGINT auto-increment OK
    protected $keyType     = 'int';
    public    $timestamps  = true;

    protected $fillable = [
        'user_id',
        'shipping_address',
        'grand_total',
    ];

    /* Relationships */

    // Combined order belongs to a user (customer)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // One combined order has many 41-manager orders
    public function orders()
    {
        return $this->hasMany(Manager41Order::class, 'combined_order_id', 'id');
    }
}
