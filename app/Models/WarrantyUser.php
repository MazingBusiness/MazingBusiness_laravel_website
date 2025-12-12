<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class WarrantyUser extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'user_id', 'user_type', 'party_code', 'gst', 'phone', 'last_login', 'name', 'update_user_type'
  ];


  // User ke saare claims
    public function claims()
    {
        return $this->hasMany(WarrantyClaim::class, 'warranty_user_id');
    }
  public function sub_order_data() {
    return $this->belongsTo(SubOrder::class, 'id', 'sub_order_id');
  }  

}
