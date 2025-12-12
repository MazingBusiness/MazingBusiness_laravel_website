@extends('backend.layouts.app')

@section('content')
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
    <form class="form form-horizontal mar-top" action="{{ route('products.ownBrandProductUpdate', $product->id) }}" method="POST"
      enctype="multipart/form-data" id="choice_form">
      <div class="row gutters-5">
        <div class="col-lg-8">
          <input name="_method" type="hidden" value="POST">
          <input type="hidden" name="id" value="{{ $product->id }}">
          <input type="hidden" name="lang" value="{{ $lang }}">
          @csrf
          <div class="card">
            <ul class="nav nav-tabs nav-fill border-light">
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
                    value="{{ $product->name }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Part No.') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="part_no" placeholder="{{ translate('Part No.') }}"
                    value="{{ $product->part_no }}" required>
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
                      <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Minimum Order Qty 1') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="number" lang="en" class="form-control" name="min_order_qty_1"
                    value="{{ $product->min_order_qty_1 }}"
                    min="1" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Minimum Order Qty 2') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-8">
                  <input type="number" lang="en" class="form-control" name="min_order_qty_2"
                    value="{{ $product->min_order_qty_2 }}"
                    min="1" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('CBM') }} </label>
                <div class="col-lg-8">
                  <input type="text" class="form-control" name="cbm" id="cbm" value="{{ $product->cbm }}" placeholder="{{ translate('CBM') }}" >
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Comptable Model') }}</label>
                <div class="col-lg-8">
                  <input type="text" lang="en" class="form-control" name="compatable_model" value="{{ $product->compatable_model }}">
                </div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product Images') }}</h5>
            </div>
            <div class="card-body">
              @if(count($photos) > 0)
                @foreach($photos as $photo)
                  {{-- <img src="https://storage.googleapis.com/mazing/{{$photo->file_name}}" class="img-rounded" alt="Cinque Terre" style="border: 1px solid #000000; border-radius: 10px; width:20%;">  --}}
				          <img src="{{ env('UPLOADS_BASE_URL', 'https://mazingbusiness.com/public') . '/' . $photo->file_name }}" class="img-rounded" alt="Cinque Terre" style="border: 1px solid #000000; border-radius: 10px; width:20%;">
                @endforeach
              @endif
              <?php /*<div class="form-group row">
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
          <?php /*<div class="card">
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
          */?>
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Product price and Weight') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('MRP') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('MRP') }}" name="mrp"
                    class="form-control" value="{{ $product->mrp }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('INR Bronze') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('INR Bronze') }}" name="inr_bronze" class="form-control" value="{{ $product->inr_bronze }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('INR Silver') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('NR Silver') }}" name="inr_silver" class="form-control" value="{{ $product->inr_silver }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('INR Gold') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('INR Gold') }}" name="inr_gold" class="form-control" value="{{ $product->inr_gold }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Dollar Bronze') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('Dollar Bronze') }}" name="doller_bronze" class="form-control" value="{{ $product->doller_bronze }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Dollar Silver') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('Dollar Bronze') }}" name="doller_silver" class="form-control" value="{{ $product->doller_silver }}" required>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-lg-3 col-from-label">{{ translate('Dollar Gold') }} <i class="las la-star text-danger" title="{{ translate('Required') }}"></i></label>
                <div class="col-lg-6">
                  <input type="text" placeholder="{{ translate('Dollar Gold') }}" name="doller_gold" class="form-control" value="{{ $product->doller_gold }}" required>
                </div>
              </div>              
              <div class="form-group row">
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
                  <textarea class="aiz-text-editor" name="description">{{ $product->description }}</textarea>
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
              <?php /* <div class="form-group row">
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
          <div class="card">
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

          <?php /*<<div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Stock') }}</h5>
            </div>
            <div class="card-body">
              <div class="form-group row">
                <div class="col-md-12">                  
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Current Stock') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="current_stock" value="1"
                          @if ($product->current_stock >= 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-md-6 col-from-label">{{ translate('Seller Stock') }}</label>
                    <div class="col-md-6">
                      <label class="aiz-switch aiz-switch-success mb-0">
                        <input type="checkbox" name="seller_stock" value="1"
                          @if ($product->seller_stock >= 1) checked @endif>
                        <span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          div class="card">
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
          </div> */ ?>

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
