<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ccavenue extends Model {
  protected $fillable = [
    'order_id', 'tracking_id', 'bank_ref_no', 'order_status', 'failure_message', 'payment_mode', 'card_name', 'status_code', 'status_message', 'currency', 'amount', 'billing_name', 'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'billing_tel', 'billing_email', 'offer_type', 'offer_code', 'discount_value', 'response_code', 'mer_amount',
  ];
}
