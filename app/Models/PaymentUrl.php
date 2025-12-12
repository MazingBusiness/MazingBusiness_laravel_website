<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentUrl extends Model {
  protected $fillable = ['party_code', 'payment_for', 'url', 'qrCodeUrl', 'merchantTranId', 'amount', 'status'];  
}