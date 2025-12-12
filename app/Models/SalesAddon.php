<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesAddon extends Model
{
    use HasFactory;

    protected $table = 'sales_addons';

    protected $fillable = [
        'sales_invoice_id',
        'name',
        'hsncode',
        'amount',
        'tax',
        'gst',
    ];

    public $timestamps = true;

    /**
     * Relationship: This addon belongs to an order invoice.
     */
    public function orderInvoice()
    {
        return $this->belongsTo(InvoiceOrder::class, 'sales_invoice_id');
    }
}
