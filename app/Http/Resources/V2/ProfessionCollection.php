<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProfessionCollection extends ResourceCollection {
  public function toArray($request) {
    return [
      'data' => $this->collection->map(function ($data) {
        return [
          'name'  => ucfirst($data->type),
          'logo'  => uploaded_asset($data->banner),
          'links' => [
            'products' => route('api.products.search', ['name' => $data->type]),
          ],
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
