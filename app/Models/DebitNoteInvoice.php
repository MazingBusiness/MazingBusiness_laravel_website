<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNoteInvoice extends Model
{
    use HasFactory;

    protected $table = 'debit_note_invoices';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'debit_note_no',
        // 'zoho_bill_id',
        'zoho_debitnote_id',
        'debit_note_order_no',
        'seller_invoice_no',
        'seller_invoice_date',
        'warehouse_id',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'seller_id',
        'seller_info',
        'addresses_id',
        'debit_note_type',
        'debit_note_number',
        'debit_note_irp_status',
        'busy_exported',
    ];

    protected $casts = [
        'seller_info' => 'array',
    ];

    public function debitNoteInvoiceDetails()
    {
        return $this->hasMany(DebitNoteInvoiceDetail::class, 'debit_note_invoice_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'addresses_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    
}
