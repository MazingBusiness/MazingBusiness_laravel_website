<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceOrder extends Model
{
    protected $table = 'invoice_orders';

    protected $fillable = [
        'party_code',
        'invoice_no',
        'warehouse_id',
        'user_id',
        'party_info',
        'transport_name',
        'transport_id',
        'challan_no',
        'challan_id',
        'sub_order_id',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'rewards_from',
        'rewards_discount',
        'btr_received_status',
        'shipping_address_id',
        'einvoice_status',

        'irn_no',
        'ack_number',
        'ack_date',
        'qr_link',
        'invoice_cancel_status',
    
		'grand_total',
        'busy_exported',
        'is_warranty',
        'conveince_fee_percentage',
        'conveince_fee_payment_check'
    ];

    protected $casts = [
        'party_info' => 'array', // Automatically handles JSON encoding/decoding
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function invoice_products()
    {
        return $this->hasMany(InvoiceOrderDetail::class, 'invoice_order_id');
    }

    public function address()
    {
        return $this->hasOne(Address::class, 'acc_code', 'party_code');
    }

    public function shipping_address()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id', 'id');
    }

    public function ewaybill()
    {
        return $this->hasOne(EwayBill::class, 'invoice_order_id');
    }

}
