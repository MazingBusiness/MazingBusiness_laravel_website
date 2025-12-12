@extends('seller.layouts.app')

@section('panel_content')
  <div class="aiz-titlebar mt-2 mb-4">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h1 class="h3">{{ translate('Products') }}</h1>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header row gutters-5">
      <div class="col">
        <h5 class="mb-md-0 h6">{{ translate('All Products') }}</h5>
      </div>
      <div class="col-md-4">
        <form class="" id="sort_brands" action="" method="GET">
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="search" name="search"
              @isset($search) value="{{ $search }}" @endisset
              placeholder="{{ translate('Search product') }}">
          </div>
        </form>
      </div>
    </div>
    <div class="card-body">
      <table class="table aiz-table mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th width="30%">{{ translate('Name') }}</th>
            <th data-breakpoints="md">{{ translate('Category') }}</th>
            <th data-breakpoints="md">{{ translate('Current Qty') }}</th>
            <th>{{ translate('Base Price') }}</th>
            @if (get_setting('product_approve_by_admin') == 1)
              <th data-breakpoints="md">{{ translate('Approval') }}</th>
            @endif
           <!--  <th  data-breakpoints="md">{{ translate('Published') }}</th>
            <th  data-breakpoints="md">{{ translate('Featured') }}</th> -->
            <th data-breakpoints="md" class="text-right">{{ translate('Options') }}</th>
          </tr>
        </thead>

        <tbody>
          @foreach ($products as $key => $product)
            <tr>
              <td>{{ $key + 1 + ($products->currentPage() - 1) * $products->perPage() }}</td>
              <td>
                <a href="{{ route('product', $product->product->slug) }}" target="_blank" class="text-reset">
                  {{ $product->product->getTranslation('name') }}
                </a>
              </td>
              <td>
                @if ($product->product->category != null)
                  {{ $product->product->category->getTranslation('name') }}
                @endif
              </td>
              <td>
                @php
                  $qty = 0;
                  //   foreach ($product->stocks as $key => $stock) {
                  //       $qty += $stock->qty;
                  //   }
                  echo $qty;
                @endphp
              </td>
              <td>{{ $product->product->unit_price }}</td>
              @if (get_setting('product_approve_by_admin') == 1)
                <td>
                  @if ($product->product->approved == 1)
                    <span class="badge badge-inline badge-success">{{ translate('Approved') }}</span>
                  @else
                    <span class="badge badge-inline badge-info">{{ translate('Pending') }}</span>
                  @endif
                </td>
              @endif
             <!--  <td>
                <label class="aiz-switch aiz-switch-success mb-0">
                  <input onchange="update_published(this)" value="{{ $product->product->id }}" type="checkbox"
                    <?php if ($product->product->published == 1) {
                        echo 'checked';
                    } ?>>
                  <span class="slider round"></span>
                </label>
              </td>
              <td>
                <label class="aiz-switch aiz-switch-success mb-0">
                  <input onchange="update_featured(this)" value="{{ $product->product->id }}" type="checkbox"
                    <?php if ($product->product->seller_featured == 1) {
                        echo 'checked';
                    } ?>>
                  <span class="slider round"></span>
                </label>
              </td> -->
              <td class="text-right">
               <!--  <a class="btn btn-soft-info btn-icon btn-circle btn-sm"
                  href="{{ route('seller.products.edit', ['id' => $product->product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                  title="{{ translate('Edit') }}">
                  <i class="las la-edit"></i>
                </a> -->

                <a class="btn btn-soft-info btn-icon btn-circle btn-sm"
                   href="{{ route('seller.product.edit', ['id' => $product->product_id]) }}"
                   title="{{ translate('Edit') }}">
                   <i class="las la-edit"></i>
                </a>


                <a style="display: none;" href="{{ route('seller.products.duplicate', $product->product->id) }}"
                  class="btn btn-soft-success btn-icon btn-circle btn-sm" title="{{ translate('Duplicate') }}">
                  <i class="las la-copy"></i>
                </a>
                <a style="display: none;" href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                  data-href="{{ route('seller.products.destroy', $product->product->id) }}"
                  title="{{ translate('Delete') }}">
                  <i class="las la-trash"></i>
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="aiz-pagination">
        {{ $products->links() }}
      </div>
    </div>
  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection

@section('script')
  <script type="text/javascript">
    function update_featured(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('seller.products.featured') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Featured products updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
          location.reload();
        }
      });
    }

    function update_published(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('seller.products.published') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Published products updated successfully') }}');
        } else if (data == 2) {
          AIZ.plugins.notify('danger', '{{ translate('Please upgrade your package.') }}');
          location.reload();
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
          location.reload();
        }
      });
    }
  </script>
@endsection
