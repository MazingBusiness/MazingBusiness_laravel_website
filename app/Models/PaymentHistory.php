<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentHistory extends Model {
  protected $fillable = ['user_id', 'party_code', 'bill_number', 'merchantId', 'terminalId', 'subMerchantId', 'OriginalBankRRN', 'merchantTranId', 'amount', 'success', 'message', 'status', 'qrCodeUrl', 'refId', 'merchantName', 'vpa', 'api_name', 'payment_for'];

  
}