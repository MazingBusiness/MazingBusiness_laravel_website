<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseAddon extends Model
{
    use HasFactory;

    protected $table = 'purchase_addons';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'purchase_invoice_id',
        'name',
        'hsncode',
        'amount',
        'tax',
        'gst',
    ];


    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the purchase invoice that owns the addon.
     */
    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
