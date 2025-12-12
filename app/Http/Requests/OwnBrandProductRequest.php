<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OwnBrandProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'          => 'required|max:255',
            'part_no'  => 'required|max:255',
            'alias_name'  => 'required|max:255',
            'group_id'  => 'required',
            'category_id'   => 'required',
            'min_order_qty_1'  => 'required|numeric',
            'min_order_qty_2'  => 'required|numeric',
            'mrp'    => 'required|numeric',
            'inr_bronze'    => 'required|numeric',
            'inr_silver'    => 'required|numeric',
            'inr_gold'    => 'required|numeric',
            'doller_bronze'    => 'required|numeric',
            'doller_silver'    => 'required|numeric',            
            'doller_gold'    => 'required|numeric',
            'description'   => 'nullable',
            'meta_title'      => 'required|max:255',
            'meta_description'   => 'nullable',
            'meta_keywords'   => 'nullable',            
            'slug' => 'required',
            'weight' => 'required|numeric',
            'published' => 'required|numeric',
            'approved' => 'required|numeric',
        ];
    }

    /**
     * Get the validation messages of rules that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required'                 => 'Product name is required',
            'part_no.required'              => 'Part number is required',
            'alias_name.required'           => 'Product alias name is required',
            'group_id.required'             => 'Product category group is required',
            'category_id.required'          => 'Category is required',
            'min_order_qty_1.required'      => 'Minimum Order Quantity 1 is required',
            'min_order_qty_1.numeric'       => 'Minimum Order Quantity 1 must be number',
            'min_order_qty_2.required'      => 'Minimum Order Quantity 2 is required',
            'min_order_qty_2.numeric'       => 'Minimum Order Quantity 2 must be number',
            'mrp.required'                  => 'Mrp is required',
            'mrp.numeric'                   => 'Mrp must be numeric',
            'inr_bronze.required'           => 'INR bronze is required',
            'inr_bronze.numeric'            => 'INR bronze must be numeric',
            'inr_silver.required'           => 'INR Silver is required',
            'inr_silver.numeric'            => 'INR Silver must be numeric',
            'inr_gold.required'             => 'INR gold is required',
            'inr_gold.numeric'              => 'INR gold must be numeric',
            'doller_bronze.required'        => 'Dollar bronze is required',
            'doller_bronze.numeric'         => 'Dollar bronze must be numeric',
            'doller_silver.required'        => 'Dollar silver is required',
            'doller_silver.numeric'         => 'Dollar silver must be numeric',
            'doller_gold.required'          => 'Dollar gold is required',
            'doller_gold.numeric'           => 'Dollar gold must be numeric',
            'meta_title.required'           => 'Meta title is required',
            'slug.required'                 => 'Slug is required',
            'weight.required'               => 'Weight is required',
            'weight.numeric'                => 'Weight must be numeric',
            'published.required'            => 'Published is required',
            'published.numeric'             => 'Published must be numeric',
            'approved.required'             => 'Approved is required',
            'approved.numeric'              => 'Approved must be numeric',
        ];
    }
}
