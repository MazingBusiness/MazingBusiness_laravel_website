@extends('seller.layouts.app')

@section('panel_content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="mb-0 h6">{{ translate('Edit Product') }}</h5>
  </div>
  <div class="">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    <form class="form form-horizontal mar-top" action="{{ route('seller.product.update', $product->id) }}" method="POST"
      enctype="multipart/form-data" id="choice_form">
      <div class="row gutters-5">
        <div class="col-lg-8">
          <input name="_method" type="hidden" value="POST">
          <input type="hidden" name="id" value="{{ $product->id }}">
          <input type="hidden" name="lang" value="{{ $lang }}">
          @csrf
          <div class="card">
            <ul style="display: none;" class="nav nav-tabs nav-fill border-light">
              @foreach (\App\Models\Language::all() as $key => $language)
                <li class="nav-item">
                  <a class="nav-link text-reset @if ($language->code == $lang) active @else bg-soft-dark border-light border-left-0 @endif py-3"
                    href="{{ route('products.admin.edit', ['id' => $product->id, 'lang' => $language->code]) }}">
                    <img src="{{ static_asset('assets/img/flags/' . $language->code . '.png') }}" height="11"
                      class="mr-1">
                    <span>{{ $language->name }}</span>
                  </a>
                </li>
              @endforeach
            </ul>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Product Name') }} 
                  <i class="las la-star text-danger" title="{{ translate('Required') }}"></i>
                </label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="name" placeholder="{{ translate('Product Name') }}"
                    value="{{ $product->getTranslation('name', $lang) }}" required>
                </div>
              </div>
              <div style="display:none;" class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Billing Name') }} 
                  <i class="las la-star text-danger" title="{{ translate('Required') }}"></i>
                </label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="billing_name" placeholder="{{ translate('Billing Name') }}"
                    value="{{ $product->getTranslation('billing_name', $lang) }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Part No.') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="part_no" placeholder="{{ translate('Part No.') }}"
                    value="{{ $product->part_no }}" required readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('HSN Code') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="hsncode" placeholder="{{ translate('HSN Code') }}"
                    value="{{ $product->hsncode }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Alias Name') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="alias_name" placeholder="{{ translate('Alias Name') }}"
                    value="{{ $product->alias_name }}" required>
                </div>
              </div>
              <div class="form-group row" id="category_group">
                <label class="col-lg-3 col-from-label">{{ translate('Category Group') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" name="group_id" id="group_id"
                    data-selected="{{ $product->group_id }}" data-live-search="true" required>
                    @foreach ($categoryGroups as $categoryGroup)
                      <option value="{{ $categoryGroup->id }}">{{ $categoryGroup->name }}</option>
                      <? /*@foreach ($category->childrenCategories as $childCategory)
                        @include('categories.child_category', ['child_category' => $childCategory])
                      @endforeach */ ?>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row" id="category">
                <label class="col-lg-3 col-from-label">{{ translate('Category') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" name="category_id" id="category_id"
                    data-selected="{{ $product->category_id }}" data-live-search="true" required>
                    @foreach ($categories as $category)
                      <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                      @foreach ($category->childrenCategories as $childCategory)
                        @include('categories.child_category', ['child_category' => $childCategory])
                      @endforeach
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row" id="brand">
                <label class="col-lg-3 col-from-label">{{ translate('Brand') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" name="brand_id" id="brand_id" data-live-search="true">
                    <option value="">{{ translate('Select Brand') }}</option>
                    @foreach (\App\Models\Brand::all() as $brand)
                      <option value="{{ $brand->id }}" @if ($product->brand_id == $brand->id) selected @endif>
                        {{ $brand->getTranslation('name') }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row" id="seller">
                  <label class="col-lg-3 col-from-label">
                      {{ translate('Seller Name') }}
                      <i class="las la-star text-danger" title="{{ translate('Required') }}"></i>
                  </label>
                  <div class="col-lg-8">
                      <!-- Disabled Select for Display -->
                      <select class="form-control aiz-selectpicker" id="seller_id"
                              data-live-search="true" disabled>
                          @foreach ($sellers as $seller)
                              <option value="{{ $seller->id }}" 
                                      @if($seller->id == $product->seller_id) selected @endif>
                                  {{ $seller->user_name }}
                              </option>
                          @endforeach
                      </select>
                      
                      <!-- Hidden Input to Submit Value -->
                      <input type="hidden" name="seller_id" value="{{ $product->seller_id }}">
                  </div>
              </div>

              <div style="display: none;" class="form-group row" id="seller">
                <label class="col-lg-3 col-from-label">{{ translate('Warehouse') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" name="warehouse_id" id="warehouse_id"
                    data-selected="{{ $product->warehouse_id }}" data-live-search="true" required>
                    @foreach ($warehouses as $warehouse)
                      <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Unit') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></i> </label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="unit"
                    placeholder="{{ translate('Unit (e.g. KG, Pc etc)') }}"
                    value="{{ $product->getTranslation('unit', $lang) }}" required readonly>
                </div>
              </div>
              <!-- <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Minimum Purchase Qty') }}</label>
                <div class="col-lg-8">
                  <input type="number" lang="en" class="form-control" name="min_qty"
                    value="@if ($product->min_qty <= 1) {{ 1 }}@else{{ $product->min_qty }} @endif"
                    min="1" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Tags') }}</label>
                <div class="col-lg-8">
                  <input type="text" class="form-control aiz-tag-input" name="tags[]" id="tags"
                    value="{{ $product->tags }}" placeholder="{{ translate('Type to add a tag') }}"
                    data-role="tagsinput">
                </div>
              </div> -->
              <div style="display: none;" class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Generic Names') }} </label>
                <div class="col-lg-8">
                  <input type="text" class="form-control aiz-tag-input" name="generic_name" id="generic_name"
                    value="{{ $product->generic_name }}" placeholder="{{ translate('Type to add a Generic Name') }}" >
                </div>
              </div>
              @if (addon_is_activated('refund_request'))
                <div class="form-group row">
                  <label class="col-lg-3 col-from-label">{{ translate('Refundable') }}</label>
                  <div class="col-lg-8">
                    <label class="aiz-switch aiz-switch-success mb-0" style="margin-top:5px;">
                      <input type="checkbox" name="refundable" @if ($product->refundable == 1) checked @endif
                        value="1">
                      <span class="slider round"></span></label>
                    </label>
                  </div>
                </div>
              @endif
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product Images') }}</h5>
            </div>
            <div class="card-body">
              @if(count($photos) > 0)

                @foreach($photos as $photo)

                  <!-- <img src="https://storage.googleapis.com/mazing/{{$photo->file_name}}" class="img-rounded" alt="Cinque Terre" style="border: 1px solid #000000; border-radius: 10px; width:20%;"> -->
                  <img src="{{ env('UPLOADS_BASE_URL') . '/' . $photo->file_name }}" class="img-rounded" alt="Cinque Terre" style="border: 1px solid #000000; border-radius: 10px; width:20%;">
                @endforeach
              @endif
              <? /*<div class="form-group row">
                <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Gallery Images') }}</label>
                <div class="col-md-8">
                  <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                    <div class="input-group-prepend">
                      <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                    </div>
                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                    <input type="hidden" name="photos" value="{{ $product->photos }}" class="selected-files">
                  </div>
                  <div class="file-preview box sm">
                  </div>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Thumbnail Image') }}
                  <small>(290x300)</small></label>
                <div class="col-md-8">
                  <div class="input-group" data-toggle="aizuploader" data-type="image">
                    <div class="input-group-prepend">
                      <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                    </div>
                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                    <input type="hidden" name="thumbnail_img" value="{{ $product->thumbnail_img }}"
                      class="selected-files">
                  </div>
                  <div class="file-preview box sm">
                  </div>
                </div>
              </div> */ ?>
            </div>
          </div>
          <? /*<div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product Videos') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Video Provider') }}</label>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" name="video_provider" id="video_provider">
                    <option value="youtube" <?php if ($product->video_provider == 'youtube') {
                        echo 'selected';
                    } ?>>{{ translate('Youtube') }}</option>
                    <option value="dailymotion" <?php if ($product->video_provider == 'dailymotion') {
                        echo 'selected';
                    } ?>>{{ translate('Dailymotion') }}</option>
                    <option value="vimeo" <?php if ($product->video_provider == 'vimeo') {
                        echo 'selected';
                    } ?>>{{ translate('Vimeo') }}</option>
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Video Link') }}</label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="video_link" value="{{ $product->video_link }}"
                    placeholder="{{ translate('Video Link') }}">
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product Variation') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row gutters-5">
                <div class="col-lg-3">
                  <input type="text" class="form-control" value="{{ translate('Colors') }}" disabled>
                </div>
                <div class="col-lg-8">
                  <select class="form-control aiz-selectpicker" data-live-search="true"
                    data-selected-text-format="count" name="colors[]" id="colors" multiple>
                    @foreach (\App\Models\Color::orderBy('name', 'asc')->get() as $key => $color)
                      <option value="{{ $color->code }}"
                        data-content="<span><span class='size-15px d-inline-block mr-2 rounded border' style='background:{{ $color->code }}'></span><span>{{ $color->name }}</span></span>"
                        <?php if (in_array($color->code, json_decode($product->colors))) {
                            echo 'selected';
                        } ?>></option>
                    @endforeach
                  </select>
                </div>
                <div class="col-lg-1">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input value="1" type="checkbox" name="colors_active" <?php if (count(json_decode($product->colors)) > 0) {
                        echo 'checked';
                    } ?>>
                    <span></span>
                  </label>
                </div>
              </div>

              <div class="form-group row gutters-5">
                <div class="col-lg-3">
                  <input type="text" class="form-control" value="{{ translate('Attributes') }}" disabled>
                </div>
                <div class="col-lg-8">
                  <select name="choice_attributes[]" id="choice_attributes" data-selected-text-format="count"
                    data-live-search="true" class="form-control aiz-selectpicker" multiple
                    data-placeholder="{{ translate('Choose Attributes') }}">
                    @foreach (\App\Models\Attribute::all() as $key => $attribute)
                      <option value="{{ $attribute->id }}" @if ($product->attributes != null && in_array($attribute->id, json_decode($product->attributes, true))) selected @endif>
                        {{ $attribute->getTranslation('name') }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="">
                <p>{{ translate('Choose the attributes of this product and then input values of each attribute') }}</p>
                <br>
              </div>

              <div class="customer_choice_options" id="customer_choice_options">
                @foreach (json_decode($product->choice_options) as $key => $choice_option)
                  <div class="form-group row">
                    <div class="col-lg-3">
                      <input type="hidden" name="choice_no[]" value="{{ $choice_option->attribute_id }}">
                      <input type="text" class="form-control" name="choice[]"
                        value="{{ optional(\App\Models\Attribute::find($choice_option->attribute_id))->getTranslation('name') }}"
                        placeholder="{{ translate('Choice Title') }}" disabled>
                    </div>
                    <div class="col-lg-8">
                      <select class="form-control aiz-selectpicker attribute_choice" data-live-search="true"
                        name="choice_options_{{ $choice_option->attribute_id }}[]" multiple>
                        @foreach (\App\Models\AttributeValue::where('attribute_id', $choice_option->attribute_id)->get() as $row)
                          <option value="{{ $row->value }}" @if (in_array($row->value, $choice_option->values)) selected @endif>
                            {{ $row->value }}
                          </option>
                        @endforeach
                      </select>
                      {{-- <input type="text" class="form-control aiz-tag-input" name="choice_options_{{ $choice_option->attribute_id }}[]" placeholder="{{ translate('Enter choice values') }}" value="{{ implode(',', $choice_option->values) }}" data-on-change="update_sku"> --}}
                    </div>
                  </div>
                @endforeach
              </div>
            </div> 
          </div>*/?>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product price and Weight') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">
                  {{ translate('Purchase Price') }} ({{ translate('inclusive of GST') }}) 
                  <i class="las la-star text-danger" title="{{ translate('Required') }}"></i>
              </label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('Purchase Price') }}" name="purchase_price"
                    class="form-control" value="{{ $product->purchase_price }}" required>
                </div>
              </div>
              <div style="display: none;" class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('MRP') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('MRP') }}" name="mrp"
                    class="form-control" value="{{ $product->mrp }}" required>
                </div>
              </div>
              <div style="display: none;" class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Weight') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('Weight') }}" name="weight"
                    class="form-control" value="{{ $product->weight }}" required>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product Description') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Description') }}</label>
                <div class="col-lg-9">
                  <textarea class="aiz-text-editor" name="description">{{ $product->getTranslation('description', $lang) }}</textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('SEO Meta Tags') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Meta Title') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="meta_title" value="{{ $product->meta_title }}"
                    placeholder="{{ translate('Meta Title') }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Meta Description') }}</label>
                <div class="col-lg-8">
                  <textarea name="meta_description" rows="8" class="form-control">{{ $product->meta_description }}</textarea>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Meta Keywords') }}</label>
                <div class="col-lg-8">
                  <textarea name="meta_keywords" rows="5" class="form-control">{{ $product->meta_keywords }}</textarea>
                </div>
              </div>
              <? /* <div class="form-group row">
                <label class="col-md-3 col-form-label" for="signinSrEmail">{{ translate('Meta Images') }}</label>
                <div class="col-md-8">
                  <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                    <div class="input-group-prepend">
                      <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}
                      </div>
                    </div>
                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                    <input type="hidden" name="meta_img" value="{{ $product->meta_img }}" class="selected-files">
                  </div>
                  <div class="file-preview box sm">
                  </div>
                </div>
              </div> */ ?>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">{{ translate('Slug') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-md-8">
                  <input type="text" placeholder="{{ translate('Slug') }}" id="slug" name="slug"
                    value="{{ $product->slug }}" class="form-control" required>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">

          <? /* <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Low Stock Quantity Warning') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group mb-3">
                <label for="name">
                  {{ translate('Quantity') }}
                </label>
                <input type="number" name="low_stock_quantity" value="{{ $product->low_stock_quantity }}"
                  min="0" step="1" class="form-control">
              </div>
            </div>
          </div> -->

          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">
                {{ translate('Stock Visibility State') }}
              </h5>
            </div>

            <div class="card-body">

              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Show Stock Quantity') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="radio" name="stock_visibility_state" value="quantity"
                      @if ($product->stock_visibility_state == 'quantity') checked @endif>
                    <span></span>
                  </label>
                </div>
              </div>

              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Show Stock With Text Only') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="radio" name="stock_visibility_state" value="text"
                      @if ($product->stock_visibility_state == 'text') checked @endif>
                    <span></span>
                  </label>
                </div>
              </div>

              <div class="form-group row">
                <label class="col-md-6 col-from-label">{{ translate('Hide Stock') }}</label>
                <div class="col-md-6">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="radio" name="stock_visibility_state" value="hide"
                      @if ($product->stock_visibility_state == 'hide') checked @endif>
                    <span></span>
                  </label>
                </div>
              </div>

            </div>
          </div> */ ?>

          <div style="display: none;" class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Cash On Delivery') }}</h5>
            </div>
            <div class="card-body">
              <? /*@if (get_setting('cash_payment') == '1')
                <div class="form-group row">
                  <div class="col-md-12">
                    <div class="form-group row">
                      <label class="col-md-6 col-from-label">{{ translate('Status') }}</label>
                      <div class="col-md-6">
                        <label class="aiz-switch aiz-switch-success mb-0">
                          <input type="checkbox" name="cash_on_delivery" value="1"
                            @if ($product->cash_on_delivery == 1) checked @endif>
                          <span></span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              @else
                <p>
                  {{ translate('Cash On Delivery option is disabled. Activate this feature from here') }}
                  <a href="{{ route('activation.index') }}"
                    class="aiz-side-nav-link {{ areActiveRoutes(['shipping_configuration.index', 'shipping_configuration.edit', 'shipping_configuration.update']) }}">
                    <span class="aiz-side-nav-text">{{ translate('Cash Payment Activation') }}</span>
                  </a>
                </p>
              @endif */ ?>
              <div  class="form-group row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Status') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="cash_on_delivery" value="1"
                          @if ($product->cash_on_delivery == 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <? /* <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Featured') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Status') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="featured" value="1"
                          @if ($product->featured == 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div> */ ?>
          <div style="display: none;" class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product status') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Publish') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="published" value="1"
                          @if ($product->published >= 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Approve') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="approved" value="1"
                          @if ($product->approved >= 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Stock') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <div class="col-md-12">
                  <!-- Current Stock -->
                  <div class="form-group row">
                    <label for="current_stock" class="col-md-6 col-form-label">{{ translate('Current Stock') }}</label>
                    <div class="col-md-6">
                      <!-- Hidden input for default value -->
                      <input type="hidden" name="current_stock" value="0">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" id="current_stock" name="current_stock" value="1"
                          @if (isset($product->current_stock) && $product->current_stock >= 1) checked @endif>
                        <span class="slider round"></span>
                      </label>
                    </div>
                  </div>
                  <!-- Seller Stock -->
                  <div class="form-group row">
                    <label for="seller_stock" class="col-md-6 col-form-label">{{ translate('Seller Stock') }}</label>
                    <div class="col-md-6">
                      <!-- Hidden input for default value -->
                      <input type="hidden" name="seller_stock" value="0">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" id="seller_stock" name="seller_stock" value="1"
                          @if (isset($product->seller_stock) && $product->seller_stock >= 1) checked @endif>
                        <span class="slider round"></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              
            </div>
          </div>

          <!-- <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Flash Deal') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group mb-3">
                <label for="name">
                  {{ translate('Add To Flash') }}
                </label>
                <select class="form-control aiz-selectpicker" name="flash_deal_id" id="video_provider">
                  <option value="">{{ translate('Choose Flash Title') }}</option>
                  @foreach (\App\Models\FlashDeal::where('status', 1)->get() as $flash_deal)
                    <option value="{{ $flash_deal->id }}" @if ($product->flash_deal_product && $product->flash_deal_product->flash_deal_id == $flash_deal->id) selected @endif>
                      {{ $flash_deal->title }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group mb-3">
                <label for="name">
                  {{ translate('Discount') }}
                </label>
                <input type="number" name="flash_discount" value="{{ $product->discount }}" min="0"
                  step="0.01" class="form-control">
              </div>
              <div class="form-group mb-3">
                <label for="name">
                  {{ translate('Discount Type') }}
                </label>
                <select class="form-control aiz-selectpicker" name="flash_discount_type" id="">
                  <option value="">{{ translate('Choose Discount Type') }}</option>
                  <option value="amount" @if ($product->discount_type == 'amount') selected @endif>
                    {{ translate('Flat') }}
                  </option>
                  <option value="percent" @if ($product->discount_type == 'percent') selected @endif>
                    {{ translate('Percent') }}
                  </option>
                </select>
              </div>
            </div>
          </div> -->

          <!-- <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Estimate Shipping Time') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group mb-3">
                <label for="name">
                  {{ translate('Shipping Days') }}
                </label>
                <div class="input-group">
                  <input type="number" class="form-control" name="est_shipping_days"
                    value="{{ $product->est_shipping_days }}" min="1" step="1"
                    placeholder="{{ translate('Shipping Days') }}">
                  <div class="input-group-prepend">
                    <span class="input-group-text" id="inputGroupPrepend">{{ translate('Days') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div> -->

          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Tax') }}</h5>
            </div>
            <div class="card-body">
              @foreach (\App\Models\Tax::where('tax_status', 1)->get() as $tax)
                <label for="name">
                  {{ $tax->name }}
                  <input type="hidden" value="{{ $tax->id }}" name="tax_id[]">
                </label>

                @php
                  $tax_amount = 0;
                  $tax_type = '';
                  foreach ($tax->product_taxes as $row) {
                      if ($product->id == $row->product_id) {
                          $tax_amount = $row->tax;
                          $tax_type = $row->tax_type;
                      }
                  }
                @endphp

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <input type="number" lang="en" min="0" value="{{ $product->tax }}" step="0.01"
                      placeholder="{{ translate('Tax') }}" name="tax" class="form-control" required>
                  </div>
                  <div class="form-group col-md-6">
                    <select class="form-control aiz-selectpicker" name="tax_type">
                      <option value="flat" @if ($tax_type == 'flat') selected @endif>
                        {{ translate('Flat') }}
                      </option>
                      <option value="percent" @if ($tax_type == 'percent' or $tax_type == null) selected @endif>
                        {{ translate('Percent') }}
                      </option>
                    </select>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

        </div>
        <div class="col-12">
          <div class="mb-3 text-right">
            <button type="submit" name="button" class="btn btn-info">{{ translate('Update Product') }}</button>
          </div>
        </div>
      </div>
    </form>
  </div>

@endsection

@section('script')
  <script type="text/javascript">
    $(document).ready(function() {
      show_hide_shipping_div();
    });

    $("[name=shipping_type]").on("change", function() {
      show_hide_shipping_div();
    });

    function show_hide_shipping_div() {
      var shipping_val = $("[name=shipping_type]:checked").val();

      $(".flat_rate_shipping_div").hide();

      if (shipping_val == 'flat_rate') {
        $(".flat_rate_shipping_div").show();
      }
    }

    function add_more_customer_choice_option(i, name) {
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: "POST",
        url: '{{ route('products.add-more-choice-option') }}',
        data: {
          attribute_id: i
        },
        success: function(data) {
          var obj = JSON.parse(data);
          $('#customer_choice_options').append(
            '\
                                                                  <div class="form-group row">\
                                                                      <div class="col-md-3">\
                                                                          <input type="hidden" name="choice_no[]" value="' +
            i +
            '">\
                                                                          <input type="text" class="form-control" name="choice[]" value="' +
            name +
            '" placeholder="{{ translate('Choice Title') }}" readonly>\
                                                                      </div>\
                                                                      <div class="col-md-8">\
                                                                          <select class="form-control aiz-selectpicker attribute_choice" data-live-search="true" name="choice_options_' +
            i + '[]" multiple>\
                                                                              ' + obj + '\
                                                                          </select>\
                                                                      </div>\
                                                                  </div>');
          AIZ.plugins.bootstrapSelect('refresh');
        }
      });


    }

    $('input[name="colors_active"]').on('change', function() {
      if (!$('input[name="colors_active"]').is(':checked')) {
        $('#colors').prop('disabled', true);
        AIZ.plugins.bootstrapSelect('refresh');
      } else {
        $('#colors').prop('disabled', false);
        AIZ.plugins.bootstrapSelect('refresh');
      }
      update_sku();
    });

    $(document).on("change", ".attribute_choice", function() {
      update_sku();
    });

    $('#colors').on('change', function() {
      update_sku();
    });

    function delete_row(em) {
      $(em).closest('.form-group').remove();
      update_sku();
    }

    function delete_variant(em) {
      $(em).closest('.variant').remove();
    }

    function update_sku() {
      $.ajax({
        type: "POST",
        url: '{{ route('products.sku_combination_edit') }}',
        data: $('#choice_form').serialize(),
        success: function(data) {
          $('#sku_combination').html(data);
          setTimeout(() => {
            AIZ.uploader.previewGenerate();
          }, "500");
          AIZ.plugins.fooTable();
          if (data.length > 1) {
            $('#show-hide-div').hide();
          } else {
            $('#show-hide-div').show();
          }
        }
      });
    }

    AIZ.plugins.tagify();

    $(document).ready(function() {
      update_sku();

      $('.remove-files').on('click', function() {
        $(this).parents(".col-md-4").remove();
      });
    });

    $('#choice_attributes').on('change', function() {
      $.each($("#choice_attributes option:selected"), function(j, attribute) {
        flag = false;
        $('input[name="choice_no[]"]').each(function(i, choice_no) {
          if ($(attribute).val() == $(choice_no).val()) {
            flag = true;
          }
        });
        if (!flag) {
          add_more_customer_choice_option($(attribute).val(), $(attribute).text());
        }
      });

      var str = @php echo $product->attributes @endphp;

      $.each(str, function(index, value) {
        flag = false;
        $.each($("#choice_attributes option:selected"), function(j, attribute) {
          if (value == $(attribute).val()) {
            flag = true;
          }
        });
        if (!flag) {
          $('input[name="choice_no[]"][value="' + value + '"]').parent().parent().remove();
        }
      });

      update_sku();
    });
  </script>
@endsection
