<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model {

  protected $with = ['seller'];

  public function seller() {
    return $this->belongsTo(Seller::class);
  }
}
