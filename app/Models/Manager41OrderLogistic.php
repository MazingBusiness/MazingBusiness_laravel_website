<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41OrderLogistic extends Model
{
    protected $table = 'manager_41_order_logistics';
    public $timestamps = true;

    protected $fillable = [
        'challan_id',           // link back to Manager41Challan
        'challan_no',
        'party_code',
        'order_no',
        'lr_no',
        'invoice_no',
        'transport_name',
        'lr_date',
        'no_of_boxes',
        'payment_type',
        'lr_amount',
        'attachment',           // comma-separated URLs (images)
        'invoice_copy_upload',  // single URL (pdf/image)
        'wa_is_processed',
        'add_status',
    ];

    protected $casts = [
        'wa_is_processed' => 'boolean',
        'lr_date' => 'date',
        'lr_amount' => 'decimal:2',
    ];

    // Helpful accessor for attachments as array
    public function getAttachmentListAttribute()
    {
        return $this->attachment ? explode(',', $this->attachment) : [];
    }

    public function challan()
    {
        return $this->belongsTo(Manager41Challan::class, 'challan_id', 'id');
    }

    // If you want to fetch address using party_code -> acc_code
    public function address()
    {
        return $this->hasOne(Address::class, 'acc_code', 'party_code');
    }
}
