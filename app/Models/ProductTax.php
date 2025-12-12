<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTax extends Model {
  protected $fillable = ['product_id', 'tax_id', 'tax', 'tax_type'];
}
