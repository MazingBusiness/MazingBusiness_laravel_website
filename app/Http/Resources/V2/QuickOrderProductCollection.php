<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Models\Upload;

class QuickOrderProductCollection extends ResourceCollection
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
        return [
            'data' => $this->collection->map(function ($data) {
                $baseUrl = env('UPLOADS_BASE_URL', url('public'));
                $thumbnail_img = '';
                // if (uploaded_asset($data->thumbnail_img)) {
                //     $thumbnail_img = uploaded_asset($data->thumbnail_img);
                // }
                $thumbnail_image = Upload::where('id', $data->thumbnail_img)->value('file_name');
                if ($thumbnail_image) {
                    $thumbnail_img = $baseUrl . '/' . $thumbnail_image;
                }

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
                    'id'    => $data->id,
                    'name'  => $data->name,
                    'group'  => $data->group_name,
                    'category'  => $data->category_name,
                    'thumbnail_img'  => $thumbnail_img,
                    'home_base_price'  => single_price($data->home_base_price),
                    'home_discounted_base_price'  => single_price($data->home_discounted_base_price),
                    'bulk_price'  => home_bulk_discounted_price($data,true, $this->userId)['price'],
                    'bulk_qty'  => home_bulk_qty($data)['bulk_qty'],
                    'bulk_text'  => "Purchase ".home_bulk_qty($data)['bulk_qty']." or more and get each for ".home_bulk_discounted_price($data,true, $this->userId)['price']." instead of ".single_price($data->home_discounted_base_price),
                    'godown' => $godown_arr
                ];
            })->values()
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