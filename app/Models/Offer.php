<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model {
  protected $guarded  = [];
  protected $fillable = ['offer_name', 'offer_id', 'state_id', 'manager_id', 'category_id', 'brand_id', 'product_code', 'complementary_items', 'offer_validity_start', 'offer_validity_end', 'count', 'offer_description', 'offer_banner', 'offer_type', 'offer_value', 'value_type','status','discount_percent','per_user','max_uses'];

  public function offerProducts()
  {
      return $this->hasMany(OfferProduct::class, 'offer_id', 'offer_id');
  }

  public function offerComplementoryProducts()
  {
      return $this->hasMany(OfferCombination::class, 'offer_id', 'offer_id');
  }

}
