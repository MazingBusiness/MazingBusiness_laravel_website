<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceOrderDetail extends Model
{
    protected $table = 'invoice_order_details';

    protected $fillable = [
        'invoice_order_id',
        'part_no',
        'item_name',
        'hsn_no',
        'gst',
        'billed_qty',
        'rate',
        'billed_amt',
        'challan_no',
        'challan_id',
        'sub_order_id',
        'sub_order_details_id',
        'inventory_status',
        'cgst',
        'sgst',
        'igst',
        'price',
        'gross_amt',
        'is_warranty',
        'barcode',
        'conveince_fee_percentage',
        'conveince_fees'
    ];

    public $timestamps = true;

    public function invoiceOrder()
    {
        return $this->belongsTo(InvoiceOrder::class, 'invoice_order_id');
    }

    public function productData()
    {
        return $this->belongsTo(Product::class, 'part_no', 'part_no');
    }

    public function subOrder()
    {
        return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
    }
}
