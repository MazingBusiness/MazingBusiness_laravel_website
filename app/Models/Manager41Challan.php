<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41Challan extends Model
{
    protected $table      = 'manager_41_challans';
    protected $primaryKey = 'id';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'challan_no',
        'challan_date',
        'sub_order_id',
        'user_id',
        'shipping_address',
        'additional_info',
        'shipping_type',
        'place_of_suply',
        'carrier_id',
        'grand_total',
        'warehouse',
        'warehouse_id',
        'remarks',
        'transport_name',
        'transport_id',
        'transport_phone',
        'shipping_address_id',
        'status',
        'invoice_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order_warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function challan_details()
    {
        return $this->hasMany(Manager41ChallanDetail::class, 'challan_id', 'id');
    }

    public function sub_order()
    {
        return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
    }
    
     

    public function address()
    {
        // if you store address_id on challan
        // challans.shipping_address_id -> addresses.id
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }
}
