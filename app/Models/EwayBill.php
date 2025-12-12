<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EwayBill extends Model
{
    protected $table = 'eway_bills';

    protected $fillable = [
        'invoice_order_id',
        'invoice_no',
        'party_code',
        'zoho_invoice_id',
        'ewaybill_id',
        'ewaybill_number',
        'entity_id',
        'entity_type',
        'entity_number',
        'entity_date',
        'supplier_gstin',
        'customer_name',
        'customer_gstin',
        'ewaybill_status',
        'ewaybill_status_formatted',
        'transporter_id',
        'transporter_name',
        'transporter_registration_id',
        'sub_supply_type',
        'distance',
        'vehicle_number',
        'ship_to_state_code',
        'entity_total',
        'ewaybill_date',
        'ewaybill_start_date',
        'ewaybill_expiry_date',
        'place_of_dispatch',
        'place_of_delivery',
        'irn_no'
    ];

    protected $dates = [
        'entity_date',
        'ewaybill_date',
        'ewaybill_start_date',
        'ewaybill_expiry_date',
    ];

    public $timestamps = true; // ✅ Enable created_at & updated_at

    public function invoice()
    {
        return $this->belongsTo(\App\Models\InvoiceOrder::class, 'invoice_order_id');
    }
}
