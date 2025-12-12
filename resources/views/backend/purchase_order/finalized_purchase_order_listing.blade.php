@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h1 class="h3">Finalized Purchase Orders</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Display Success Message -->
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

           <form method="GET" action="{{ route('finalized.purchase.orders') }}" class="mb-4 d-flex">
                <div class="input-group">
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search by Purchase Order No. or Seller Name" 
                        value="{{ request('search') }}" 
                        autofocus
                    >
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="las la-search"></i> Search
                        </button>
                    </div>
                </div>
                @if(request('search'))
                    <a href="{{ route('finalized.purchase.orders') }}" class="btn btn-danger ml-2">
                        <i class="las la-times"></i> 
                    </a>
                @endif
            </form>


            <table class="table aiz-table mb-0 table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('finalized.purchase.orders', ['sort_column' => 'purchase_order_no', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                                Purchase Order No
                                @if(request('sort_column') === 'purchase_order_no')
                                    <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('finalized.purchase.orders', ['sort_column' => 'date', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                                Date
                                @if(request('sort_column') === 'date')
                                    <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a style="color:#76C7C0;" href="{{ route('finalized.purchase.orders', ['sort_column' => 'seller_name', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
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
                    <tr>
                        <td>{{ $order->purchase_order_no }}</td>
                        <td>{{ $order->date }}</td>
                        <td>{{ $order->seller_name }}</td>
                        <td>{{ $order->seller_phone }}</td>
                        <td>
                            <a href="{{ route('purchase-order.product-info', $order->id) }}" class="btn btn-outline-info btn-sm">
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
                                <form action="{{ route('purchase-order-force-close', $order->id) }}" method="get" class="mr-2" onsubmit="return confirm('Are you sure you want to force close this order?');">
                                    @csrf
                                    <button style="padding: 4px;" type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="las la-times"></i> Force Close
                                    </button>
                                </form>
                            @else
                                <span style="width:110px;" class="badge badge-secondary">No Action</span>
                            @endif

                            <a href="{{ route('download.purchase_order', $order->purchase_order_no) }}" 
                               class="btn btn-outline-primary btn-sm ml-2" 
                               title="Download PDF">
                                <i class="las la-download"></i> PO
                            </a>
                            <a href="{{ route('download.packing_list', $order->purchase_order_no) }}" 
                               class="btn btn-outline-primary btn-sm ml-2" 
                               title="Download Packing List">
                                <i class="las la-file-alt"></i> PL
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
@endsection
