<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $table = 'purchase_invoices';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'purchase_no',
        'zoho_bill_id',
        'zoho_creditnote_id',
        'purchase_order_no',
        'seller_invoice_no',
        'seller_invoice_date',
        'warehouse_id',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'seller_id',
        'seller_info',
		'addresses_id',
        'purchase_invoice_type',  // ✅ New Column
        'credit_note_number',
        'credit_note_irp_status',
        'busy_exported',
        'invoice_attachment'
        
    ];
    protected $casts = [
        'seller_info' => 'array',
    ];

    // Relationship with MakePurchaseOrder
    public function makePurchaseOrder()
    {
        return $this->belongsTo(MakePurchaseOrder::class, 'purchase_order_no', 'purchase_order_no');
    }

    public function purchaseInvoiceDetails()
    {
        return $this->hasMany(PurchaseInvoiceDetail::class, 'purchase_invoice_id');
    }

    // In PurchaseInvoice.php
    public function address()
    {
        return $this->belongsTo(Address::class, 'addresses_id');
    }
    // In PurchaseInvoice model
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'seller_id', 'seller_id');
    }

}