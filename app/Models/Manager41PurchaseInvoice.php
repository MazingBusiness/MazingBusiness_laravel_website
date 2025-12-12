<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manager41PurchaseInvoice extends Model
{
    use HasFactory;

    protected $table = 'manager_41_purchase_invoices';
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
        'purchase_invoice_type',
        'credit_note_number',
        'credit_note_irp_status',
        'busy_exported',
        'invoice_attachment',
    ];

    // 🔧 yeh line add karo
    protected $casts = [
        'seller_info' => 'array',
    ];

    public function makePurchaseOrder()
    {
        return $this->belongsTo(MakePurchaseOrder::class, 'purchase_order_no', 'purchase_order_no');
    }

    public function purchaseInvoiceDetails()
    {
        return $this->hasMany(Manager41PurchaseInvoiceDetail::class, 'purchase_invoice_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'addresses_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'seller_id', 'seller_id');
    }
}
