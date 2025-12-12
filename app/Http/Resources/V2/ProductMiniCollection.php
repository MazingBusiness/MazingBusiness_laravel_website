<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;

class ProductMiniCollection extends ResourceCollection
{
    protected $userId;

    public function __construct($resource, $userId = null)
    {
        // Ensure you call the parent constructor
        parent::__construct($resource);

        // Assign the additional data
        $this->userId = $userId;
    }
    public function toArray($request)
    {
        // echo $this->userId; die;
        return [
            'data' => $this->collection->map(function($data) {
                $godown_arr = array();

                $line_godown = array("name"=>"Delhi", "stock"=>100);
                $godown_arr[] = $line_godown;
                $line_godown = array("name"=>"Mumbai", "stock"=>200);
                $godown_arr[] = $line_godown;

                $godown_arr = array();

                $stocks = ProductWarehouse::where('product_id', $data->id)->get();

                foreach ($stocks as $stock) {
                    $pw = Warehouse::find($stock->warehouse_id);
                    $line_godown = array("name" => $pw->name, "stock" => $stock->qty);
                    $godown_arr[] = $line_godown;
                }
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'has_discount' => home_base_price($data, false, $this->userId) != home_discounted_base_price($data, false, $this->userId) ,
                    'discount'=>"-".discount_in_percentage($data,$this->userId)."%",
                    'stroked_price' => home_base_price($data, true, $this->userId),
                    'main_price' => home_discounted_base_price($data, true, $this->userId),
                    'rating' => (double) $data->rating,
                    'sales' => (integer) $data->num_of_sale,
                    'links' => [
                        'details' => route('products.show', $data->id),
                    ],
                    'godown' => $godown_arr
                ];
            })
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