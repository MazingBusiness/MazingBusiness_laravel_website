<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41Order extends Model
{
    protected $table       = 'manager_41_orders';
    protected $primaryKey  = 'id';
    public    $incrementing = true;
    protected $keyType     = 'int';
    public    $timestamps  = true;

    protected $fillable = [
        'combined_order_id',
        'user_id',
        'guest_id',
        'seller_id',
        'address_id',
        'shipping_address',
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
        'delete_status',
    ];

    /* =========================
     |  Relationships (mirrors Order)
     |=========================*/

    // Order Details (41 manager table)
    public function orderDetails()
    {
        return $this->hasMany(Manager41OrderDetail::class, 'order_id', 'id');
    }

    public function refund_requests()
    {
        return $this->hasMany(RefundRequest::class, 'order_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shop()
    {
        // same as Order: Shop.user_id == seller_id
        return $this->hasOne(Shop::class, 'user_id', 'seller_id');
    }

    public function pickup_point()
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id', 'id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }

    public function affiliate_log()
    {
        return $this->hasMany(AffiliateLog::class, 'order_id', 'id');
    }

    public function club_point()
    {
        return $this->hasMany(ClubPoint::class, 'order_id', 'id');
    }

    
    public function delivery_boy()
    {
        return $this->belongsTo(User::class, 'assign_delivery_boy', 'id');
    }

    public function proxy_cart_reference_id()
    {
        // same pattern as Order
        return $this->hasMany(ProxyPayment::class, 'order_id', 'id')->select('reference_id');
    }

    public function order_approval()
    {
        // same as Order: code -> code
        return $this->hasOne(OrderApproval::class, 'code', 'code');
    }

    public function sub_order()
    {
        // same as Order: code -> code
        return $this->hasMany(SubOrder::class, 'code', 'code');
    }

    // (Optional) अगर 41 combined orders यूज़ कर रहे हैं:
    public function combined_order()
    {
        return $this->belongsTo(Manager41CombinedOrder::class, 'combined_order_id', 'id');
    }
}
