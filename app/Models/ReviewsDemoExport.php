<?php

namespace App\Models;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReviewsDemoExport implements WithMapping, WithHeadings {

  public function headings(): array
  {
    return ['part_no', 'user_id', 'rating', 'comment'];
  }

  /**
   * @var Category $category
   */
  public function map($category): array
  {
    return ['', '', '', ''];
  }
}
