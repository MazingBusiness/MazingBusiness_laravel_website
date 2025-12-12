<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class ProductApi2 extends Model {
  protected $table = 'products_api2';

  protected $fillable = [
    'part_no', 'name', 'group', 'category', 'closing_stock', 'list_price', 'godown'
  ];
}
