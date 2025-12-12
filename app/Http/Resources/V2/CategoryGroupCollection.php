<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryGroupCollection extends ResourceCollection
{
    public function toArray($request)
    {
        // return [
        //     'data' => $this->collection->map(function ($data) {
        //         $banner = '';
        //         if (uploaded_asset($data->banner)) {
        //             $banner = uploaded_asset($data->banner);
        //         }
        //         return [
        //             'id' => $data->id,
        //             'name' => $data->name,
        //             'banner' => $banner,
        //         ];
        //     })
        // ];

        return [
            'data' => $this->collection->map(function ($data) {
                $banner = '';
                if (uploaded_asset($data->banner)) {
                    $banner = uploaded_asset($data->banner);
                }
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'banner' => $banner,
                ];
            })->values()->all()  // Use values() to reset keys and all() to convert the collection to an array
        ];
        
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
