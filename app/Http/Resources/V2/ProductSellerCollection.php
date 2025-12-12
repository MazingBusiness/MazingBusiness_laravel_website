<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductSellerCollection extends ResourceCollection {
  public function toArray($request) {
    return [
      'data' => $this->collection->map(function ($data) {

        return [
          'product_id'           => $data->product_id,
          'part_no'              => $data->part_no,
          'item_name'  => $data->product ? $data->product->getTranslation('name') : '',
          'alias_name' => $data->product ? $data->product->getTranslation('alias_name') : '',
          'billing_name'         => $data->print_name,
          'hsncode'         => $data->hsncode,
          'weight'         => $data->weight,
          'cbm'         => $data->cbm,
          'SZ_Manual_Price_list' => $data->sz_manual_price,
          'SZ_Group'             => $data->sz_group,
          'SZ_Category'          => $data->sz_category,
          'piece_per_carton'            => $data->piece_per_carton,
          'import_duty'           => $data->product ? $data->product->import_duty : '',
          'rate_of_gst'            => '18',
          'seller_stock'            => $data->seller_stock > 0 ? true : false,
          'seller_item'            => (bool) true,
          'seller_name'            => ($data->seller) ? (($data->seller->user) ? $data->seller->user->name : '') : '',
          'image'         => $data->product ? uploaded_asset($data->product->photos) : '',
          'unit_of_measurement'         => $data->product ? $data->product->unit : '',
          'mrp'         => $data->mrp,
        ];
      }),
    ];
  }

  public function with($request) {
    return [
      'success' => true,
      'status'  => 200,
    ];
  }
}
