@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('Purchased Order') }}</h1>
      @if (session('status'))
        <div class="alert alert-success">
          {{ session('status') }}
        </div>
      @endif
    </div>
    <div class="d-flex justify-content-end mb-3">
      <a href="{{ route('import.excel.form') }}" class="btn btn-primary btn-sm" title="{{ translate('Add') }}">
        <i class="las la-file-import"></i> Add
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-header row gutters-5 d-flex justify-content-between align-items-center">
      <form method="GET" action="#" class="d-flex w-100">
        <div class="col-md-3">
          <select class="form-control" id="sellerName" name="sellerName">
            <option value="">{{ translate('Select Seller') }}</option>
            @foreach($sellers as $seller)
              <option value="{{ $seller->id }}" {{ request('sellerName') == $seller->id ? 'selected' : '' }}>
                {{ $seller->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary">Filter</button>
        </div>
      </form>
      <div>
        <button type="button" class="btn btn-success btn-lg" id="makePurchaseOrder">
          <i class="las la-shopping-cart"></i> Make Purchase Order
        </button>
      </div>
    </div>

    <div class="card-body">
      <form id="purchase_order_form" method="GET" action="{{ route('purchase-order.showSelected') }}">
        @csrf
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <th><input type="checkbox" id="checkAll"></th>
              <th><a href="javascript:void(0)">#</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'branch', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Branch</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'order_date', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Order Date</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'order_no', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Order No.</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'party', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Party</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'part_no', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Part No.</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'item', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Product Name</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'seller_name', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Seller</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'order_qty', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Order Qty</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'closing_qty', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Closing Qty</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'to_be_ordered', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">To Be Ordered</a></th>
              <th><a href="{{ route('admin.purchase_order', array_merge(request()->query(), ['sort' => 'age', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">Age</a></th>
              <th>{{ translate('Actions') }}</th> <!-- New column for actions -->
            </tr>
          </thead>
          <tbody>
          @foreach($purchaseOrders as $key => $order)
            <tr>
              <td><input type="checkbox" name="selectedOrders[]" value="{{ $order->id }}"></td>
              <td>{{ $purchaseOrders->firstItem() + $key }}</td>
              <td>{{ $order->branch }}</td>
              <td>{{ $order->order_date }}</td>
              <td>{{ $order->order_no }}</td>
              <td>{{ $order->party }}</td>
              <td>{{ $order->part_no }}</td>
              <td>{{ $order->item }}</td>
              <td>{{ $order->seller_company_name }} ({{ $order->seller_name }})</td>
              <td>{{ $order->order_qty }}</td>
              <td>{{ $order->closing_qty }}</td>
              <td>{{ $order->to_be_ordered }}</td>
              <td>{{ $order->age }}</td>
              <td>
                <a href="{{ route('purchase-order-delete', ['id' => $order->id]) }}" 
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('{{ translate('Are you sure you want to delete this purchase order?') }}');">
                  <i class="las la-trash-alt"></i>
                </a>
              </td> <!-- Delete icon -->
            </tr>
          @endforeach
          </tbody>
        </table>
      </form>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center">
      <div>
        <p class="text-sm text-muted">
          Showing {{ $purchaseOrders->firstItem() }} to {{ $purchaseOrders->lastItem() }} of {{ $purchaseOrders->total() }} entries
        </p>
      </div>
      <div class="aiz-pagination">
        {{ $purchaseOrders->links() }}
      </div>
    </div>
  </div>
@endsection

@section('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#checkAll').addEventListener('change', function() {
      let checkboxes = document.querySelectorAll('input[type="checkbox"][name="selectedOrders[]"]');
      checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
    });
  });
</script>
@endsection
