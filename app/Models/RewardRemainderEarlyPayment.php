<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardRemainderEarlyPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_code',
        'warehouse_id',
        'manager_id',
        'invoice_no',
        'invoice_date',
        'invoice_amount',
        'payment_applied',
        'remaining_amount',
        'payment_status',
        'reminder_sent',
        'is_processed',
        'msg_id'
    ];

    // Accessor for formatted invoice date
    public function getFormattedInvoiceDateAttribute()
    {
        return \Carbon\Carbon::parse($this->invoice_date)->format('Y-m-d');
    }


     // Latest WA status for THIS row's msg_id
    public function latestCloudResponse()
    {
        // Laravel 9+: latestOfMany('id') — latest row by id
        return $this->hasOne(CloudResponse::class, 'msg_id', 'msg_id')->latestOfMany('id');
        // Laravel < 9: ->orderByDesc('id')
    }

    // Convenience accessor: $model->wa_status
    public function getWaStatusAttribute()
    {
        return optional($this->latestCloudResponse)->status;
    }

    
}
