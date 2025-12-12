<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
            'billing_name'  => 'required|max:255',
            'hsncode'  => 'required|max:255',
            'alias_name'  => 'required|max:255',
            'group_id'  => 'required',
            'category_id'   => 'required',
            'brand_id'   => 'required',
            'seller_id'   => 'required',
            'warehouse_id'   => 'required',
            'unit'          => 'required',
            'tax'       => 'required|numeric',
            'purchase_price'    => 'required|numeric',
            'mrp'    => 'required|numeric',
            'meta_title'      => 'required|max:255',
            // 'current_stock' => 'required|numeric',
            // 'seller_stock' => 'required|numeric',
            'slug' => 'required',
            'weight' => 'required|numeric',
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
            'name.required'             => 'Product name is required',
            'billing_name.required'     => 'Product billing name is required',
            'hsncode.required'          => 'Product hsncode is required',
            'alias_name.required'       => 'Product alias name is required',
            'group_id.required'         => 'Product category group is required',
            'category_id.required'      => 'Category is required',
            'brand_id.required'         => 'Brand is required',
            'seller_id.required'        => 'Seller is required',
            'warehouse_id.required'     => 'Warehouse is required',
            'unit.required'             => 'Unit field is required',
            'tax.required'              => 'Tax is required',
            'tax.numeric'               => 'Tax must be numeric',
            'purchase_price.required'   => 'Purchase price is required',
            'purchase_price.numeric'    => 'Purchase price must be numeric',
            'mrp.required'              => 'Mrp is required',
            'mrp.numeric'               => 'Mrp must be numeric',
            'weight.required'           => 'Weight is required',
            'weight.numeric'            => 'Weight must be numeric',
            'meta_title.required'       => 'Meta title is required',
            'slug.required'             => 'Slug is required',
        ];
    }
}
