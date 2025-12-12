@extends('backend.layouts.app')

@section('content')
<div>
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h1 class="h3">Supply Order</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Display Success Message -->
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif
            <a style="position: relative;left: 919px;" href="{{ route('admin.supplyOrder.export.all') }}" 
           class="btn btn-success mb-3 ml-2">
           <i class="las la-file-excel"></i> Export All (Pending Only)
        </a>

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

           <form method="GET" action="{{ route('admin.supplyOrderLising') }}" class="mb-4 d-flex">
                <div class="input-group">
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search by Purchase Order No. or Seller Name" 
                        value="{{ request('search') }}" 
                        autofocus
                        id="test";
                    >
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="las la-search"></i> Search
                        </button>
                    </div>
                </div>
                @if(request('search'))
                    <a href="{{ route('admin.supplyOrderLising') }}" class="btn btn-danger ml-2">
                        <i class="las la-times"></i> 
                    </a>
                @endif
            </form>

 <!-- Button to navigate to edit page -->
          <form id="edit-orders-form" method="GET" action="{{ route('productInformation') }}">
            <input type="hidden" name="purchase_order_nos" id="selected-orders">
            <button type="submit" id="edit-button" class="btn btn-warning mb-3" style="display: none;">
                Edit Selected Orders
            </button>
        </form>
                <!-- Global Export Button -->
    
            <table class="table aiz-table mb-0 table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('admin.supplyOrderLising', ['sort_column' => 'purchase_order_no', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                                Purchase Order No
                                @if(request('sort_column') === 'purchase_order_no')
                                    <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('admin.supplyOrderLising', ['sort_column' => 'date', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                                Date
                                @if(request('sort_column') === 'date')
                                    <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('admin.supplyOrderLising', ['sort_column' => 'seller_name', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                                Seller Name
                                @if(request('sort_column') === 'seller_name')
                                    <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>Seller Phone</th>
                        <th>Product Info</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    @php $collapseId = 'collapse-' . $order->id; @endphp
                    <tr>
                        <td>
                            @if($order->force_closed == 0)
                             <input type="checkbox" class="select-person" >
                               <!--  <input type="checkbox" class="select-person" 
                                       data-manager-id="187" 
                                       data-party-code="OPEL0200662" 
                                       data-due-amount="39622.00" 
                                       data-overdue-amount="0.00" 
                                       data-user-id="26435"> -->
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $order->purchase_order_no }}</td>
                        <td>{{ $order->date }}</td>
                        <td>{{ $order->seller_name }}</td>
                        <td>{{ $order->seller_phone }}</td>
                         <td>
                            <a href="{{ route('purchase-orders.product-info', $order->id) }}" class="btn btn-outline-info btn-sm">
                                <i class="las la-eye"></i> View
                            </a>
                        </td> 
                        <td>
                            @if($order->force_closed == 1)
                                <span style="width:80px;" class="badge badge-danger">Canceled</span>
                            @else
                                <span style="width:80px;" class="badge badge-success">Open</span>
                            @endif
                        </td>
                        <td class="d-flex align-items-center">
                            @if($order->force_closed == 0)
                                <form action="{{ route('po-force-close', $order->id) }}" method="get" class="mr-2" onsubmit="return confirm('Are you sure you want to force close this order?');">
                                    @csrf
                                    <button style="padding: 4px;" type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="las la-times"></i> Force Close
                                    </button>
                                </form>
                            @else
                                <span style="width:110px;" class="badge badge-secondary">No Action</span>
                            @endif

                            <a href="{{ route('purchase_order.download_pdf', $order->purchase_order_no) }}" 
                               class="btn btn-outline-primary btn-sm ml-2" 
                               title="Download PDF">
                                <i class="las la-download"></i> PO
                            </a>
                            <a href="{{ route('packing_list.download_pdf', ['purchase_order_no' => $order->purchase_order_no]) }}" 
                               class="btn btn-outline-primary btn-sm ml-2" 
                               title="Download Packing List">
                                <i class="las la-file-alt"></i> PL
                            </a>
                            <a href="{{ route('admin.supplyOrder.export', $order->id) }}" 
                               class="btn btn-outline-success btn-sm ml-2" 
                               title="Download Excel">
                               <i class="las la-file-excel"></i> 
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span>
                        Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} entries
                    </span>
                </div>

                <!-- Pagination Links -->
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function () {
    // Select/Deselect all checkboxes
    $(document).on('click', '#select-all', function () {
        var isChecked = $(this).prop('checked');
        $('.select-person').prop('checked', isChecked);
        updateEditButton();
    });

    // Detect individual checkbox selection
    $(document).on('change', '.select-person', function () {
        updateEditButton();
    });

    function updateEditButton() {
        let selectedOrders = $('.select-person:checked').map(function () {
            return $(this).closest('tr').find('td:nth-child(2)').text(); // Fetch the purchase order number
        }).get();

        if (selectedOrders.length > 0) {
            $('#edit-button').show();
        } else {
            $('#edit-button').hide();
        }
    }

    // Submit selected orders for editing
    $('#edit-orders-form').on('submit', function (e) {
        let selectedOrders = $('.select-person:checked').map(function () {
            return $(this).closest('tr').find('td:nth-child(2)').text();
        }).get();

        if (selectedOrders.length === 0) {
            alert('Please select at least one order.');
            e.preventDefault();
            return false;
        }

        $('#selected-orders').val(selectedOrders.join(',')); // Store selected orders in hidden input
    });
});

</script>
@endsection
