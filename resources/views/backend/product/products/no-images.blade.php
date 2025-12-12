@extends('backend.layouts.app')

@section('content')
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col-auto">
        <h1>{{ translate('Products without Images') }}</h1>
      </div>
      <div class="col text-right">
        <a href="{{ route('products.all') }}" class="btn btn-circle btn-info">
          <span>{{ translate('All Products') }}</span>
        </a>
      </div>
    </div>
  </div>
  <br>

  <div class="card">
    <form class="" id="sort_products" action="" method="GET">
      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-md-0 h6">{{ translate('All Product') }}</h5>
        </div>

        <div class="col-md-2">
          <div class="form-group mb-0">
            <input type="text" class="form-control form-control-sm" id="search"
              name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
              placeholder="{{ translate('Type & Enter') }}">
          </div>
        </div>
      </div>

      <div class="card-body">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              @if (auth()->user()->can('product_delete'))
                <th>
                  <div class="form-group">
                    <div class="aiz-checkbox-inline">
                      <label class="aiz-checkbox">
                        <input type="checkbox" class="check-all">
                        <span class="aiz-square-check"></span>
                      </label>
                    </div>
                  </div>
                </th>
              @else
                <th data-breakpoints="lg">#</th>
              @endif
              <th>{{ translate('Name') }}</th>
              <th data-breakpoints="sm">{{ translate('Info') }}</th>
              <th data-breakpoints="md">{{ translate('Total Stock') }}</th>
              <th data-breakpoints="lg">{{ translate('Published') }}</th>
              <th data-breakpoints="lg">{{ translate('Featured') }}</th>
              <th data-breakpoints="sm" class="text-right">{{ translate('Options') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $key => $product)
              <tr>
                @if (auth()->user()->can('product_delete'))
                  <td>
                    <div class="form-group d-inline-block">
                      <label class="aiz-checkbox">
                        <input type="checkbox" class="check-one" name="id[]" value="{{ $product->id }}">
                        <span class="aiz-square-check"></span>
                      </label>
                    </div>
                  </td>
                @else
                  <td>{{ $key + 1 + ($products->currentPage() - 1) * $products->perPage() }}</td>
                @endif
                <td>
                  <div class="row gutters-5 w-200px w-md-300px mw-100">
                    <div class="col-auto">
                      <img src="{{ uploaded_asset($product->thumbnail_img) }}" alt="Image" class="size-50px img-fit">
                    </div>
                    <div class="col">
                      <span class="text-muted text-truncate-2">{{ $product->getTranslation('name') }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <strong>{{ translate('Num of Sale') }}:</strong> {{ $product->num_of_sale }} {{ translate('times') }}
                  </br>
                  <strong>{{ translate('Base Price') }}:</strong> {{ single_price($product->unit_price) }} </br>
                  <strong>{{ translate('Rating') }}:</strong> {{ $product->rating }} </br>
                </td>
                <td>
                  @php
                    $qty = 0;
                    if ($product->variant_product) {
                        foreach ($product->stocks as $key => $stock) {
                            $qty += $stock->qty + $stock->seller_stock;
                            echo $stock->part_no . ' - ' . $stock->qty . '<br>';
                        }
                    } else {
                        //$qty = $product->current_stock;
                        $qty = $product->stocks->sum('qty') + $product->stocks->sum('seller_stock');
                        echo $qty;
                    }
                  @endphp
                  @if ($qty <= $product->low_stock_quantity)
                    <span class="badge badge-inline badge-danger">Low</span>
                  @endif
                </td>
                <td>
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input onchange="update_published(this)" value="{{ $product->id }}" type="checkbox"
                      <?php if ($product->published == 1) {
                          echo 'checked';
                      } ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input onchange="update_featured(this)" value="{{ $product->id }}" type="checkbox"
                      <?php if ($product->featured == 1) {
                          echo 'checked';
                      } ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td class="text-right">
                  @can('product_edit')
                    <div class="input-group btn btn-soft-primary btn-icon btn-circle btn-sm float-right"
                      data-toggle="aizuploader" data-type="image">
                      <i class="las la-plus m-auto"></i>
                      <input type="hidden" name="photos[]" onchange="saveimages(this)" class="selected-files"
                        data-product-id="{{ $product->id }}">
                    </div>
                  @endcan
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="aiz-pagination">
          {{ $products->appends(request()->input())->links() }}
        </div>
      </div>
    </form>
  </div>
@endsection

@section('script')
  <script type="text/javascript">
    $(document).on("change", ".check-all", function() {
      if (this.checked) {
        // Iterate each checkbox
        $('.check-one:checkbox').each(function() {
          this.checked = true;
        });
      } else {
        $('.check-one:checkbox').each(function() {
          this.checked = false;
        });
      }
    });


    $(document).ready(function() {
      function saveimages(el) {}
      //$('#container').removeClass('mainnav-lg').addClass('mainnav-sm');
    });

    function update_todays_deal(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.todays_deal') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Todays Deal updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function update_published(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.published') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Published products updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function update_approved(el) {
      if (el.checked) {
        var approved = 1;
      } else {
        var approved = 0;
      }
      $.post('{{ route('products.approved') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        approved: approved
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Product approval update successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function update_featured(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.featured') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Featured products updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function sort_products(el) {
      $('#sort_products').submit();
    }

    function bulk_delete() {
      var data = new FormData($('#sort_products')[0]);
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('bulk-product-delete') }}",
        type: 'POST',
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        success: function(response) {
          if (response == 1) {
            location.reload();
          }
        }
      });
    }
  </script>
@endsection
