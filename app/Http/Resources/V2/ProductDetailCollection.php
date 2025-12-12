<?php

namespace App\Http\Resources\V2;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Review;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductDetailCollection extends ResourceCollection
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
        $precision        = 2;
        $calculable_price = home_discounted_base_price($data, false, $this->userId);
        $calculable_price = number_format($calculable_price, $precision, '.', '');
        $calculable_price = floatval($calculable_price);
        // $calculable_price = round($calculable_price, 2);
        $photo_paths = get_images_path($data->photos);

        $photos = [];

        if (!empty($photo_paths)) {
          for ($i = 0; $i < count($photo_paths); $i++) {
            if ($photo_paths[$i] != "") {
              $item            = array();
              $item['variant'] = "";
              $item['path']    = $photo_paths[$i];
              $photos[]        = $item;
            }
          }
        }

        foreach ($data->stocks as $stockItem) {
          if ($stockItem->image != null && $stockItem->image != "") {
            $item            = array();
            $item['variant'] = $stockItem->variant;
            $item['path']    = uploaded_asset($stockItem->image);
            $photos[]        = $item;
          }
        }

        $brand = [
          'id'   => 0,
          'name' => "",
          'logo' => "",
        ];

        if ($data->brand != null) {
          $brand = [
            'id'   => $data->brand->id,
            'name' => $data->brand->getTranslation('name'),
            'logo' => uploaded_asset(Brand::where('id', $data->brand->id)->value('logo')),
          ];
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
          'id'                      => (int) $data->id,
          'name'                    => $data->getTranslation('name'),
          'added_by'                => $data->added_by,
          'seller_id'               => $data->user->id,
          // 'shop_id'                 => $data->added_by == 'admin' ? 0 : $data->user->shop->id,
          // 'shop_name'               => $data->added_by == 'admin' ? translate('In House Product') : $data->user->shop->name,
          // 'shop_logo'               => $data->added_by == 'admin' ? uploaded_asset(get_setting('header_logo')) : uploaded_asset($data->user->shop->logo) ?? "",
          'photos'                  => $photos,
          'thumbnail_image'         => uploaded_asset($data->thumbnail_img),
          'tags'                    => explode(',', $data->tags),
          // 'price_high_low' => (double)explode('-', home_discounted_base_price($data, false))[0] == (double)explode('-', home_discounted_price($data, false))[1] ? format_price((double)explode('-', home_discounted_price($data, false))[0]) : "From " . format_price((double)explode('-', home_discounted_price($data, false))[0]) . " to " . format_price((double)explode('-', home_discounted_price($data, false))[1]),
          'choice_options'          => $this->convertToChoiceOptions($data),
          'colors'                  => json_decode($data->colors) ?? [],
          'has_discount'            => home_base_price($data, false,$this->userId) != home_discounted_base_price($data, false, $this->userId),
          'discount'                => "-" . discount_in_percentage($data) . "%",
          'stroked_price'           => home_base_price($data,true,$this->userId),
          'main_price'              => home_discounted_base_price($data,true, $this->userId),
          'calculable_price'        => $calculable_price,
          'currency_symbol'         => currency_symbol(),
          //'current_stock'           => (int) $data->stocks->first()->qty,
          'unit'                    => $data->unit ?? "",
          'rating'                  => (float) $data->rating,
          'rating_count'            => (int) Review::where(['product_id' => $data->id])->count(),
          'earn_point'              => (float) $data->earn_point,
          'description'             => $data->getTranslation('description'),
          'video_link'              => $data->video_link != null ? $data->video_link : "",
          'brand'                   => $brand,
          'link'                    => route('product', $data->slug),
          'min_qty'                 => $data->min_qty,
          'piece_per_carton'        => qty_per_carton($data),
          // 'carton_status'           => $data->stocks->first()->qty >= qty_per_carton($data) ? true : false,
          'carton_price'            => home_base_carton_price($data),
          'calculable_carton_price' => round(home_base_carton_price($data, false), 2),
          'in_stock'                => $data->current_stock,
          'estimated_shipping_days' => get_estimated_shipping_days($data),
          'category_id'             => $data->category_id,
          'category_name'           => Category::findOrfail($data->category_id)->value('name'),
          'group_category_name'     => $this->getCategoryGroupName($data),
          'godown' => $godown_arr
        ];
      })
    ];
  }

  public function with($request)
  {
    return [
      'success' => true,
      'status'  => 200,
    ];
  }

  protected function convertToChoiceOptions($data)
  {
    $result = array();
    if ($data) {
        $category_id = $data->category_id;
        $result = [];  // Ensure $result is initialized
    
        foreach (json_decode($data->choice_options) as $key => $choice) {
            $attribute = Attribute::find($choice->attribute_id);
            $item = [
                'name' => $choice->attribute_id,
                'title' => $attribute->getTranslation('name'),
                'attribute_type' => $attribute->type,
                'options' => []
            ];
    
            if ($attribute->type == 'variant') {
                $attrs = Product::select('choice_options')->where('category_id', $category_id)->get();
                $list = [head($choice->values)];
    
                foreach ($attrs as $attr) {
                    $attrOptions = collect(json_decode($attr->choice_options))->where('attribute_id', $attribute->id)->first();
                    if ($attrOptions) {
                        $list = array_merge($list, $attrOptions->values);
                    }
                }
                $item['options'] = array_values(array_unique($list));
            } else {
                $item['options'] = $choice->values;
            }
    
            if (head($choice->values) != null) {
                array_push($result, $item);
            }
        }
    }
    return $result;
  }

  protected function convertPhotos($data)
  {
    $result = array();
    foreach ($data as $key => $item) {
      array_push($result, uploaded_asset($item));
    }
    return $result;
  }

  protected function getCategoryGroupName($data)
  {
    $category_id         = Category::where('id', $data->category_id)->first('category_group_id');
    $group_category_name = CategoryGroup::where('id', $category_id->category_group_id)->value('name');
    return $group_category_name;
  }
}
