<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GroupCategoryCollection extends ResourceCollection {
  public function toArray($request) {
    return [
      'data' => $this->collection->map(function ($data) {
        return [
          'id'         => $data->id,
          'name'       => 'Group - ' . $data->name,
          'categories' => new CategoryCollection($data->childrenCategories),
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
