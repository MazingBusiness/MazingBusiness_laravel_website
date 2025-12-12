<?php

namespace App\Models;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StocksExport implements FromCollection, WithMapping, WithHeadings {
  protected $type, $id;

  public function __construct(String $type = null, String $id = null) {
    $this->type = $type;
    $this->id   = $id;
  }

  public function collection() {
    if ($this->type) {
      return ProductWarehouse::with(['product', 'warehouse:id,name', 'seller:id,user_id', 'seller.user:id,name'])->where($this->type . '_id', $this->id)->get();
    } else {
      return ProductWarehouse::with(['product:id,name', 'warehouse:id,name', 'seller:id,user_id', 'seller.user:id,name'])->get();
    }
  }

  public function headings(): array
  {
    $return_array = [
      'id',
      'product_id',
      'name',
      'part_no',
    ];
    if ($this->type == 'warehouse') {
      array_push($return_array, 'qty');
    } elseif ($this->type == 'seller') {
      array_push($return_array, 'seller_stock');
    } else {
      array_push($return_array, 'warehouse', 'warehouse_stock', 'seller', 'seller_stock');
    }
    return $return_array;
  }

  /**
   * @var Product Warehouse $product_warehouse
   */
  public function map($product_warehouse): array
  {
    $return_values = [
      $product_warehouse->id,
      $product_warehouse->product_id,
      $product_warehouse->product->name,
      $product_warehouse->part_no,
    ];
    if ($this->type == 'warehouse') {
      array_push($return_values, $product_warehouse->qty);
    } elseif ($this->type == 'seller') {
      array_push($return_values, $product_warehouse->seller_stock);
    } else {
      array_push($return_values, ($product_warehouse->warehouse) ? $product_warehouse->warehouse->name : '', $product_warehouse->qty, ($product_warehouse->seller) ? $product_warehouse->seller->user->name : '', $product_warehouse->seller_stock);
    }
    return $return_values;
  }
}
