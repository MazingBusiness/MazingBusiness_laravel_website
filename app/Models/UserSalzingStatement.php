<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSalzingStatement extends Model {
  protected $fillable = ['user_id', 'zoho_customer_id', 'acc_code','due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','statement_data'];

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