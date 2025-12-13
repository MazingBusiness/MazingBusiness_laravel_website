@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h1 class="h3">Product Information for Purchase Order No. : {{ $order->purchase_order_no }}</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Display Success Message -->
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Display Validation Errors -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('purchase-order.convert', $order->id) }}" method="POST">
                @csrf

                <!-- Hidden input to pass the seller ID -->
                <input type="hidden" name="seller_id" value="{{ $order->seller_id }}">
                <input type="hidden" name="purchase_order_no" value="{{ $order->purchase_order_no }}">

                <!-- Table for Product Information -->
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Part No.</th>
                            <th>HSN Code</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Purchase Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                    @php
                        $grandTotal = 0;
                    @endphp
                    @foreach($filteredProductInfo as $index => $product)
                        @php
                            $subtotal = $product['qty'] * $product['purchase_price'];
                            $grandTotal += $subtotal;
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $product['part_no'] }}</td>
                            <td>
                                <input readonly type="text" name="products[{{ $index }}][hsncode]" value="{{ $product['hsncode'] }}" class="form-control border rounded shadow-sm">
                            </td>
                            <td>{{ $product['product_name'] }}</td>
                            <td>
                                <input readonly type="number" name="products[{{ $index }}][qty]" value="{{ $product['qty'] }}" class="form-control qty-input border rounded shadow-sm" data-index="{{ $index }}" onchange="recalculateSubtotal({{ $index }})">
                            </td>
                            <td>
                                <input readonly type="number" step="0.01" name="products[{{ $index }}][purchase_price]" value="{{ $product['purchase_price'] }}" class="form-control price-input border rounded shadow-sm" data-index="{{ $index }}" onchange="recalculateSubtotal({{ $index }})">
                            </td>
                            <td class="subtotal" data-index="{{ $index }}">{{ ($subtotal) }}</td>
                        </tr>
                        <input type="hidden" name="products[{{ $index }}][part_no]" value="{{ $product['part_no'] }}">
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-right">Total:</th>
                            <th id="grandTotal">{{ ($grandTotal) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<!-- script is written in app.blade.php -->
@endsection
