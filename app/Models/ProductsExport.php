<?php

namespace App\Models;

use App\Models\Product;
use Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, WithMapping, WithHeadings {
  protected $type, $id;

  public function __construct(String $type = null, String $id = null) {
    $this->type = $type;
    $this->id   = $id;
  }

  public function collection() {
    $user  = Auth::user();
    $query = ProductWarehouse::with(['product', 'product.category:id,name', 'product.brand:id,name', 'warehouse:id,name', 'seller:id,user_id', 'seller.user:id,name']);
    if ($this->type) {
      $query = $query->where($this->type . '_id', $this->id);
    }
    if ($this->type && $user->user_type == 'staff' && $user->warehouse_id && $this->type != 'warehouse') {
      $query = $query->where('warehouse_id', $user->warehouse_id);
    }
    return $query->get();
  }

  public function headings(): array {
    $return_array = [
      'id',
      'product_id',
      'category',
      'brand',
      'name',
      'part_no',
      'video_provider',
      'video_link',
      'generic_name',
      'tags',
      'description',
      'published',
      'cash_on_delivery',
      'featured',
      'min_qty',
      'low_stock_quantity',
      'discount',
      'discount_type',
      'discount_start_date',
      'discount_end_date',
      'meta_title',
      'meta_description',
      'warehouse',
      'warehouse_stock',
      'seller',
      'seller_stock',
      'hsncode',
      'model_no',
      'seller_sku',
      'sz_category',
      'sz_group',
      'sz_manual_price',
      'price',
      'carton_price',
      'piece_per_carton',
      'length',
      'breadth',
      'height',
      'cbm',
      'carton_cbm',
      'weight',
    ];
    for ($i = 1; $i <= Category::withCount('attributes')->get()->max('attributes_count'); $i++) {
      array_push($return_array, 'attribute_' . $i);
    }
    return $return_array;
  }

  /**
   * @var Product Warehouse $product_warehouse
   */
  public function map($product_warehouse): array {
    $return_values = [
      $product_warehouse->id,
      $product_warehouse->product_id,
      ($product_warehouse->product->category) ? $product_warehouse->product->category->name : '',
      ($product_warehouse->product->brand) ? $product_warehouse->product->brand->name : '',
      $product_warehouse->product->name,
      $product_warehouse->part_no,
      $product_warehouse->product->video_provider,
      $product_warehouse->product->video_link,
      $product_warehouse->product->generic_name,
      $product_warehouse->product->tags,
      $product_warehouse->product->description,
      $product_warehouse->product->published,
      $product_warehouse->product->cash_on_delivery,
      $product_warehouse->product->featured,
      $product_warehouse->product->min_qty,
      $product_warehouse->product->low_stock_quantity,
      $product_warehouse->product->discount,
      $product_warehouse->product->discount_type,
      $product_warehouse->product->discount_start_date,
      $product_warehouse->product->discount_end_date,
      $product_warehouse->product->meta_title,
      $product_warehouse->product->meta_description,
      ($product_warehouse->warehouse) ? $product_warehouse->warehouse->name : '',
      $product_warehouse->qty,
      ($product_warehouse->seller) ? (($product_warehouse->seller->user) ? $product_warehouse->seller->user->name : '') : '',
      $product_warehouse->seller_stock,
      $product_warehouse->hsncode,
      $product_warehouse->model_no,
      $product_warehouse->seller_sku,
      $product_warehouse->sz_category,
      $product_warehouse->sz_group,
      $product_warehouse->sz_manual_price,
      $product_warehouse->price,
      $product_warehouse->carton_price,
      $product_warehouse->piece_per_carton,
      $product_warehouse->length,
      $product_warehouse->breadth,
      $product_warehouse->height,
      $product_warehouse->cbm,
      $product_warehouse->carton_cbm,
      $product_warehouse->weight,
    ];
    // foreach (json_decode($product_warehouse->product->choice_options) as $attribute) {
      // array_push($return_values, ($attribute->values) ? $attribute->values[0] : '');
    // }
    return $return_values;
  }
}
