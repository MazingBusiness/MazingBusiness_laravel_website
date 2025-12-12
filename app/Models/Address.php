<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model {
  protected $fillable = ['user_id', 'company_name', 'acc_code', 'gstin', 'address', 'address_2', 'city_id', 'city', 'state_id', 'country_id', 'postal_code', 'phone', 'set_default','due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','statement_data','zoho_customer_id'];

  public function user() {
    return $this->belongsTo(User::class);
  }

  public function country() {
    return $this->belongsTo(Country::class);
  }

  public function state() {
    return $this->belongsTo(State::class);
  }

  public function city() {
    return $this->belongsTo(City::class,'city_id');
  }
  
}