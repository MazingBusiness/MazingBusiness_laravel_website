<?php

namespace App\Models;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsDemoExport implements FromCollection, WithMapping, WithHeadings {
  public $max_attributes = 0;

  public function collection() {
    return Category::with('attributes:id,name')->withCount('attributes')->whereHas('attributes')->get();
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
      'est_shipping_days',
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
    for ($i = 1; $i <= $this->collection()->max('attributes_count'); $i++) {
      array_push($return_array, 'attribute_' . $i);
    }
    return $return_array;
  }

  /**
   * @var Category $category
   */
  public function map($category): array {
    $return_row = [
      '',
      '',
      $category->name,
      '',
      '',
      '',
      '',
      '',
      '',
      '',
      0,
      0,
      0,
      0,
      0,
      '',
      '',
      '',
      '',
      0,
      '',
      '',
      '',
      0,
      '',
      0,
      '',
      '',
      '',
      '',
      '',
      '',
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
    ];
    foreach ($category->attributes as $attribute) {
      array_push($return_row, $attribute->name);
    }
    return $return_row;
  }
}
