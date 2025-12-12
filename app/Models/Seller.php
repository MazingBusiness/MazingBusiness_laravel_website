<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seller extends Model {

  protected $with = ['user', 'user.warehouse'];
  protected $fillable = ['*'];

  public function user() {
    return $this->belongsTo(User::class);
  }

  public function payments() {
    return $this->hasMany(Payment::class);
  }

  public function shop() {
    return $this->hasOne(Shop::class);
  }

  public function warehouseProducts() {
    return $this->hasMany(ProductWarehouse::class);
  }
}
