<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebitNoteInvoiceDetail extends Model
{
    use HasFactory;

    protected $table = 'debit_note_invoice_details';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'debit_note_invoice_id',
        'debit_note_no',
        'debit_note_order_no',
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

    public function debitNoteInvoice()
    {
        return $this->belongsTo(DebitNoteInvoice::class, 'debit_note_invoice_id');
    }
}
