<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ReviewsImport implements ToCollection, WithHeadingRow, WithValidation, ToModel {
  private $rows = 0;

  public function collection(Collection $rows) {
    foreach ($rows as $row) {
      $product = ProductWarehouse::where('part_no', $row['part_no'])->first();
      Review::create([
        'product_id' => $product->product_id,
        'user_id'    => $row['user_id'],
        'rating'     => $row['rating'],
        'comment'    => $row['comment'],
        'status'     => 1,
        'viewed'     => 1,
      ]);
      $rating = Review::where('product_id', $product->product_id)->where('status', 1)->sum('rating') / Review::where('product_id', $product->product_id)->where('status', 1)->count();
      Product::where('id', $product->product_id)->update([
        'rating' => $rating,
      ]);
    }
    flash(translate('Reviews imported successfully'))->success();
  }

  public function model(array $row) {
    ++$this->rows;
  }

  public function rules(): array
  {
    return [
      // Can also use callback validation rules
      'price' => function ($attribute, $value, $onFailure) {
        if (!is_numeric($value)) {
          $onFailure('Unit price is not numeric');
        }
      },
    ];
  }
}
