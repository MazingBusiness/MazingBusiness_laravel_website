<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\SliderCollection;
use Cache;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SliderController extends Controller
{
    public function getSliders()
    {
        // return Cache::remember('app.home_slider_images', 86400, function(){
        //     return new SliderCollection(json_decode(get_setting('home_slider_images'), true));
        // }); 
        // die;
        $images = json_decode(get_setting('home_slider_images'), true);
        $links  = json_decode(get_setting('home_slider_links'), true);
        $data=array();
        foreach($images as $key=>$value){
            $data[$key]['photo'] = uploaded_asset($value);
            $explode_array1 = explode('https://mazingbusiness.com/category/',$links[$key]);
            if(isset($explode_array1[1])){
                $explode_array2 = explode('?keyword=',$explode_array1[1]);
                $categories = DB::table('categories')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                ->whereNotNull('category_groups.banner')
                ->where('categories.slug',$explode_array2[0])
                ->where('products.current_stock', '>', 0)
                ->select('categories.*','category_groups.name as ctg_name','category_groups.slug as ctg_slug')
                ->first();

                $data[$key]['redirected_to'] = 'product_listing';
                $data[$key]['category_id'] = (int)$categories->id;
                $data[$key]['category_name'] = $categories->name;
                $data[$key]['category_slug'] = $categories->slug;
                $data[$key]['category_group_id'] = (int)$categories->category_group_id;
                $data[$key]['category_group_name'] = $categories->ctg_name;
                $data[$key]['category_group_slug'] = $categories->ctg_slug;
                $data[$key]['brand_id'] = 187;
                $data[$key]['product_id'] = null;
                $data[$key]['product_name'] = '';
                $data[$key]['product_slug'] = '';
            }           

        }
        \Log::info('Slider API Call.');
        return response()->json([
            'data'=>$data,
            'success' => true,
            'status' => 200
        ]);

    }

    public function bannerOne()
    {
        // return Cache::remember('app.home_banner1_images', 86400, function(){
        //     return new SliderCollection(json_decode(get_setting('home_banner1_images'), true));
        // });

        $images = json_decode(get_setting('home_banner1_images'), true);
        $links  = json_decode(get_setting('home_banner1_links'), true);
        $data=array();
        foreach($images as $key=>$value){
            $data[$key]['photo'] = uploaded_asset($value);
            $explode_array = explode('https://mazingbusiness.com/product/',$links[$key]);
            $explode_array1 = explode('https://mazingbusiness.com/category/',$links[$key]);
            if(isset($explode_array[1])){
                $get_product_details = DB::table('products')
                            ->join('categories', 'products.category_id', '=', 'categories.id')
                            ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                            ->where('products.slug', urldecode($explode_array[1]))
                            // ->where('products.current_stock', '>', 0)
                            ->select('products.*', 'categories.name as category_name', 'categories.slug as category_slug', 'category_groups.name as category_group_name', 'category_groups.slug as category_group_slug')
                            ->first();
                $data[$key]['redirected_to'] = 'product_details';
                $data[$key]['category_id'] = (int)$get_product_details->category_id;
                $data[$key]['category_name'] = $get_product_details->category_name;
                $data[$key]['category_slug'] = $get_product_details->category_slug;
                $data[$key]['category_group_id'] = (int)$get_product_details->group_id;
                $data[$key]['category_group_name'] = $get_product_details->category_group_name;
                $data[$key]['category_group_slug'] =$get_product_details->category_group_slug;
                $data[$key]['brand_id'] = (int)$get_product_details->brand_id;
                $data[$key]['product_id'] = (int)$get_product_details->id;
                $data[$key]['product_name'] = $get_product_details->name;
                $data[$key]['product_slug'] = $get_product_details->slug;
            }
            if(isset($explode_array1[1])){
                $explode_array2 = explode('?keyword=',$explode_array1[1]);

                $categories = DB::table('categories')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                ->where('products.current_stock', '>', 0)
                ->whereNotNull('category_groups.banner')
                ->where('categories.slug',$explode_array2[0])
                ->select('categories.*','category_groups.name as ctg_name','category_groups.slug as ctg_slug')
                ->first();

                $data[$key]['redirected_to'] = 'product_listing';
                $data[$key]['category_id'] = (int)$categories->id;
                $data[$key]['category_name'] = $categories->name;
                $data[$key]['category_slug'] = $categories->slug;
                $data[$key]['category_group_id'] = (int)$categories->category_group_id;
                $data[$key]['category_group_name'] = $categories->ctg_name;
                $data[$key]['category_group_slug'] = $categories->ctg_slug;
                $data[$key]['brand_id'] = 187;
                $data[$key]['product_id'] = null;
                $data[$key]['product_name'] = '';
                $data[$key]['product_slug'] = '';
            }
            
        }
        return response()->json([
            'data'=>$data,
            'success' => true,
            'status' => 200
        ]);
    }

    public function bannerTwo()
    {
        // return Cache::remember('app.home_banner2_images', 86400, function(){
        //     return new SliderCollection(json_decode(get_setting('home_banner2_images'), true));
        // });

        $images = json_decode(get_setting('home_banner2_images'), true);
        $links  = json_decode(get_setting('home_banner2_links'), true);
        
        $data=array();
        foreach($images as $key=>$value){
            $data[$key]['photo'] = uploaded_asset($value);
            $explode_array = explode('https://mazingbusiness.com/product/',$links[$key]);
            $explode_array1 = explode('https://mazingbusiness.com/category/',$links[$key]);
            
            if(isset($explode_array[1])){
                $get_product_details = DB::table('products')
                            ->join('categories', 'products.category_id', '=', 'categories.id')
                            ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                            ->where('products.slug', urldecode($explode_array[1]))
                            // ->where('products.current_stock', '>', 0)
                            ->select('products.*', 'categories.name as category_name', 'categories.slug as category_slug', 'category_groups.name as category_group_name', 'category_groups.slug as category_group_slug')
                            ->first();
                $data[$key]['redirected_to'] = 'product_details';
                $data[$key]['category_id'] = (int)$get_product_details->category_id;
                $data[$key]['category_name'] = $get_product_details->category_name;
                $data[$key]['category_slug'] = $get_product_details->category_slug;
                $data[$key]['category_group_id'] = (int)$get_product_details->group_id;
                $data[$key]['category_group_name'] = $get_product_details->category_group_name;
                $data[$key]['category_group_slug'] =$get_product_details->category_group_slug;
                $data[$key]['brand_id'] = (int)$get_product_details->brand_id;
                $data[$key]['product_id'] = (int)$get_product_details->id;
                $data[$key]['product_name'] = $get_product_details->name;
                $data[$key]['product_slug'] = $get_product_details->slug;
            }
            if(isset($explode_array1[1])){
                $explode_array2 = explode('?keyword=',$explode_array1[1]);
                $categories = DB::table('categories')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                // ->whereNotNull('category_groups.banner')
                ->where('products.current_stock', '>', 0)
                ->where('categories.slug',$explode_array2[0])
                ->select('categories.*','category_groups.name as ctg_name','category_groups.slug as ctg_slug')
                ->first();
                $data[$key]['redirected_to'] = 'product_listing';
                $data[$key]['category_id'] = (int)$categories->id;
                $data[$key]['category_name'] = $categories->name;
                $data[$key]['category_slug'] = $categories->slug;
                $data[$key]['category_group_id'] = (int)$categories->category_group_id;
                $data[$key]['category_group_name'] = $categories->ctg_name;
                $data[$key]['category_group_slug'] = $categories->ctg_slug;
                $data[$key]['brand_id'] = 187;
                $data[$key]['product_id'] = null;
                $data[$key]['product_name'] = '';
                $data[$key]['product_slug'] = '';
            }
        }
        
        return response()->json([
            'data'=>$data,
            'success' => true,
            'status' => 200
        ]);
    }

    public function bannerThree()
    {
        // return Cache::remember('app.home_banner3_images', 86400, function(){
        //     return new SliderCollection(json_decode(get_setting('home_banner3_images'), true));
        // });

        $images = json_decode(get_setting('home_banner3_images'), true);
        $links  = json_decode(get_setting('home_banner3_links'), true);
        $data=array();
        foreach($images as $key=>$value){
            $data[$key]['photo'] = uploaded_asset($value);
            $explode_array = explode('https://mazingbusiness.com/product/',$links[$key]);
            $explode_array1 = explode('https://mazingbusiness.com/category/',$links[$key]);
            if(isset($explode_array[1])){
                $get_product_details = DB::table('products')
                            ->join('categories', 'products.category_id', '=', 'categories.id')
                            ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                            ->where('products.slug', urldecode($explode_array[1]))
                            // ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                            ->select('products.*', 'categories.name as category_name', 'categories.slug as category_slug', 'category_groups.name as category_group_name', 'category_groups.slug as category_group_slug')
                            ->first();
                $data[$key]['redirected_to'] = 'product_details';
                $data[$key]['category_id'] = (int)$get_product_details->category_id;
                $data[$key]['category_name'] = $get_product_details->category_name;
                $data[$key]['category_slug'] = $get_product_details->category_slug;
                $data[$key]['category_group_id'] = (int)$get_product_details->group_id;
                $data[$key]['category_group_name'] = $get_product_details->category_group_name;
                $data[$key]['category_group_slug'] =$get_product_details->category_group_slug;
                $data[$key]['brand_id'] = (int)$get_product_details->brand_id;
                $data[$key]['product_id'] = (int)$get_product_details->id;
                $data[$key]['product_name'] = $get_product_details->name;
                $data[$key]['product_slug'] = $get_product_details->slug;
            }
            if(isset($explode_array1[1])){
                $explode_array2 = explode('?keyword=',$explode_array1[1]);
                $categories = DB::table('categories')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                // ->whereNotNull('category_groups.banner')
                // ->leftJoin('products', 'products.category_id', '=', 'categories.id')
                ->where('categories.slug',$explode_array2[0])
                ->select('categories.*','category_groups.name as ctg_name','category_groups.slug as ctg_slug')
                ->first();
                $data[$key]['redirected_to'] = 'product_listing';
                $data[$key]['category_id'] = (int)$categories->id;
                $data[$key]['category_name'] = $categories->name;
                $data[$key]['category_slug'] = $categories->slug;
                $data[$key]['category_group_id'] = (int)$categories->category_group_id;
                $data[$key]['category_group_name'] = $categories->ctg_name;
                $data[$key]['category_group_slug'] = $categories->ctg_slug;
                $data[$key]['brand_id'] = 187;
                $data[$key]['product_id'] = null;
                $data[$key]['product_name'] = '';
                $data[$key]['product_slug'] = '';
            }
        }
        return response()->json([
            'data'=>$data,
            'success' => true,
            'status' => 200
        ]);
    }
}
