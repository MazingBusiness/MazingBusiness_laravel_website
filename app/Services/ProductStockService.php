<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Utility\ProductUtility;
use Combinations;

class ProductStockService {
  public function store(array $data, $product) {
    $collection = collect($data);

    $options = ProductUtility::get_attribute_options($collection);

    //Generates the combinations of customer choice options
    $combinations = Combinations::makeCombinations($options);

    $variant = '';
    if (count($combinations[0]) > 0) {
      $product->variant_product = 1;
      $product->save();
      foreach ($combinations as $key => $combination) {
        $str                             = ProductUtility::get_combination_string($combination, $collection);
        $product_stock                   = new ProductWarehouse();
        $product_stock->product_id       = $product->id;
        $product_stock->warehouse_id     = $data['warehouse_id'];
        $product_stock->seller_id        = $data['seller_id'];
        $product_stock->part_no          = request()['part_no_' . str_replace('.', '_', $str)];
        $product_stock->model_no         = request()['model_no_' . str_replace('.', '_', $str)];
        $product_stock->seller_sku       = request()['seller_sku_' . str_replace('.', '_', $str)];
        $product_stock->price            = request()['price_' . str_replace('.', '_', $str)];
        $product_stock->carton_price     = request()['carton_price_' . str_replace('.', '_', $str)];
        $product_stock->piece_per_carton = request()['piece_per_carton_' . str_replace('.', '_', $str)];
        $product_stock->qty              = request()['qty_' . str_replace('.', '_', $str)];
        $product_stock->seller_stock     = request()['seller_stock_' . str_replace('.', '_', $str)];
        $product_stock->length           = request()['length_' . str_replace('.', '_', $str)];
        $product_stock->breadth          = request()['breadth_' . str_replace('.', '_', $str)];
        $product_stock->height           = request()['height_' . str_replace('.', '_', $str)];
        $product_stock->weight           = request()['weight_' . str_replace('.', '_', $str)];
        $product_stock->cbm              = request()['cbm_' . str_replace('.', '_', $str)];
        $product_stock->carton_cbm       = request()['carton_cbm_' . str_replace('.', '_', $str)];
        $product_stock->image            = request()['img_' . str_replace('.', '_', $str)];
        $product_stock->save();
      }
    } else {
      unset($collection['colors_active'], $collection['colors'], $collection['choice_no']);
      $qty   = $collection['current_stock'];
      $price = $collection['unit_price'];
      unset($collection['current_stock']);

      $data = $collection->merge(compact('variant', 'qty', 'price'))->toArray();

      ProductWarehouse::create($data);
      Product::find($product->id)->update(['current_stock' => $collection['current_stock'] + $collection['seller_stock']]);
    }
  }

  public function product_duplicate_store($product_stocks, $product_new) {
    foreach ($product_stocks as $key => $stock) {
      $product_stock             = new ProductWarehouse();
      $product_stock->product_id = $product_new->id;
      $product_stock->variant    = $stock->variant;
      $product_stock->price      = $stock->price;
      $product_stock->seller_sku = $stock->seller_sku;
      $product_stock->qty        = $stock->qty;
      $product_stock->save();
    }
  }
}
