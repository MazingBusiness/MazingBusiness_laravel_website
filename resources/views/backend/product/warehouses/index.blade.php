@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('All Warehouses') }}</h1>
    </div>
    @if (auth()->user()->can('add_warehouse'))
      <div class="col text-right">
        <a href="{{ route('warehouses.create') }}" class="btn btn-circle btn-info">
          <span>{{ translate('Add New Warehouse') }}</span>
        </a>
      </div>
    @endif
  </div>

  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header row gutters-5">
          <div class="col text-center text-md-left">
            <h5 class="mb-md-0 h6">{{ translate('Warehouses') }}</h5>
          </div>
          <div class="col-md-4">
            <form class="" id="sort_warehousess" action="" method="GET">
              <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="search"
                  name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
                  placeholder="{{ translate('Type name & Enter') }}">
              </div>
            </form>
          </div>
        </div>
        <div class="card-body">
          <table class="table aiz-table mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ translate('Name') }}</th>
                <th>{{ translate('Address') }}</th>
                <th>{{ translate('City') }}</th>
                <th>{{ translate('State') }}</th>
                <th>{{ translate('Pincode') }}</th>
                <th>{{ translate('Service States') }}</th>
                <th>{{ translate('Default Phone') }}</th>
                <th class="text-right">{{ translate('Options') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($warehouses as $key => $warehouse)
                <tr>
                  <td>{{ $key + 1 + ($warehouses->currentPage() - 1) * $warehouses->perPage() }}</td>
                  <td>{{ $warehouse->name }}</td>
                  <td>{{ $warehouse->address }}</td>
                  <td>{{ $warehouse->city->name }}</td>
                  <td>{{ $warehouse->state->name }}</td>
                  <td>{{ $warehouse->pincode }}</td>
                  <td>
                    {{ App\Models\State::whereIn('id', explode(',', $warehouse->service_states))->get()->pluck('name')->join(', ') }}
                  </td>
                  <td>{{ $warehouse->phone }}</td>
                  <td class="text-right">
                    @can('edit_warehouse')
                      <a class="btn btn-soft-primary btn-icon btn-circle btn-sm m-1"
                        href="{{ route('warehouses.edit', ['id' => $warehouse->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                        title="{{ translate('Edit') }}">
                        <i class="las la-edit"></i>
                      </a>
                    @endcan
                    @can('download_warehouse_products')
                      <a class="btn btn-soft-info btn-icon btn-circle btn-sm m-1"
                        href="{{ route('download_warehouse_products.products', ['id' => $warehouse->id]) }}"
                        title="{{ translate('Products List') }}">
                        <i class="las la-boxes"></i>
                      </a>
                    @endcan
                    @can('download_warehouse_products')
                      <a class="btn btn-soft-warning btn-icon btn-circle btn-sm m-1"
                        href="{{ route('download_warehouse_products.stocks', ['id' => $warehouse->id]) }}"
                        title="{{ translate('Stocks List') }}">
                        <i class="las la-warehouse"></i>
                      </a>
                    @endcan
                    @can('delete_warehouse')
                      <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm m-1 confirm-delete"
                        data-href="{{ route('warehouses.destroy', $warehouse->id) }}" title="{{ translate('Delete') }}">
                        <i class="las la-trash"></i>
                      </a>
                    @endcan
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
          <div class="aiz-pagination">
            {{ $warehouses->appends(request()->input())->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection

@section('script')
  <script type="text/javascript">
    function sort_warehouses(el) {
      $('#sort_warehouses').submit();
    }
  </script>
@endsection
