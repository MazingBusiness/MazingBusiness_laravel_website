<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model {
  protected $casts = [
    'markup' => 'array',
  ];

  protected $fillable = ['name', 'address', 'city', 'state', 'pincode', 'service_states', 'phone', 'markup', 'inhouse_saleszing_id', 'seller_saleszing_id','user_id','zoho_branch_id','eway_address_id'];

  public function sellers() {
    return $this->hasMany(Seller::class);
  }

  public function products() {
    return $this->belongsToMany(Product::class);
  }

  public function city() {
    return $this->belongsTo(City::class);
  }

  public function state() {
    return $this->belongsTo(State::class);
  }

  // All addresses for this warehouse's user (optional helper)
    public function getAddress()
    {
        // hasOne returns 1 row; the orderBy controls which one
        return $this->hasOne(Address::class, 'user_id', 'user_id')
                    ->orderBy('acc_code','ASC');
    }
}
