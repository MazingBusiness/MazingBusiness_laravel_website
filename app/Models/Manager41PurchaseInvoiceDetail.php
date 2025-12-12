<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manager41PurchaseInvoiceDetail extends Model
{
    use HasFactory;

    protected $table = 'manager_41_purchase_invoice_details';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'purchase_invoice_id',
        'purchase_invoice_no',
        'purchase_order_no',
        'part_no',
        'cgst',
        'sgst',
        'igst',
        'gross_amt',
        'price',
        'tax',
        'qty',
        'order_no',
        'hsncode',
        'inventory_status',
    ];

    public function purchaseInvoice()
    {
        return $this->belongsTo(Manager41PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'part_no', 'part_no');
    }
}
