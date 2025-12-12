<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41SubOrder extends Model
{
    protected $table = 'manager_41_sub_orders';

    protected $fillable = [
        'combined_order_id',
        'order_no',
        're_allocated_sub_order_id',
        'user_id',
        'guest_id',
        'seller_id',
        'sub_order_id',
        'shipping_address_id',
        'shipping_address',
        'billing_address_id',
        'billing_address',
        'additional_info',
        'shipping_type',
        'pickup_point_id',
        'carrier_id',
        'delivery_status',
        'payment_type',
        'manual_payment',
        'manual_payment_data',
        'payment_gateway_status',
        'payment_status',
        'payment_details',
        'grand_total',
        'payable_amount',
        'payment_discount',
        'coupon_discount',
        'code',
        'tracking_code',
        'date',
        'viewed',
        'delivery_viewed',
        'order_from',
        'payment_status_viewed',
        'commission_calculated',
        'applied_offer_id',
        'offer_rewards',
        'order_id',
        'type',
        'warehouse_id',
        'other_details',
        'status',
        'sub_order_user_name',
        'transport_table_id',
        'transport_id',
        'transport_name',
        'transport_phone',
        'transport_remarks',
    ];

    public $timestamps = true;

    // --- Relationships ---

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order_warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sub_order_details()
    {
        return $this->hasMany(Manager41SubOrderDetail::class, 'sub_order_id', 'id');
    }

    // Link to Manager-41 orders by code (like your original)
    public function order()
    {
        return $this->belongsTo(Manager41Order::class, 'code', 'code');
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }
}
