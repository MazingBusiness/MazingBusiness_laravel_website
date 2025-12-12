<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ShortAddressCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($data) {
            return [
                'id' => $data->id,
                'address' => $data->address,
                'city' => $data->city->name,
                'state' => $data->state->name,
                'pincode' => $data->postal_code,
                'transport' => null,
            ];
        });
    }
}
