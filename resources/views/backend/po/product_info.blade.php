@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h1 class="h3">Products Information for Order: {{ $order->purchase_order_no }}</h1>
    </div>

    <div class="card">
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
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form action="{{ route('purchase-orders.convert', $order->id) }}" method="POST">
                @csrf

                 <!-- Hidden input to pass the seller ID -->
                <input type="hidden" name="seller_id" value="{{ $order->seller_id }}">
                <input type="hidden" name="purchase_order_no" value="{{ $order->purchase_order_no }}">
                <input type="hidden" name="purchase_no" value="{{ $purchaseNo }}">

                <!-- Seller Information Section -->
                <div class="form-group row">
                    <label for="seller_name" class="col-md-2 col-form-label text-right font-weight-bold">Seller Name:</label>
                    <div class="col-md-4">
                        <input type="text" id="seller_name" name="seller_info[seller_name]" class="form-control border rounded shadow-sm" value="{{ $sellerInfo['seller_name'] }}">
                    </div>

                    <label for="seller_phone" class="col-md-2 col-form-label text-right font-weight-bold">Seller Phone:</label>
                    <div class="col-md-4">
                        <input type="text" id="seller_phone" name="seller_info[seller_phone]" class="form-control border rounded shadow-sm" value="{{ $sellerInfo['seller_phone'] }}">
                    </div>
                </div>

                <!-- Seller Address and GSTIN -->
                <div class="form-group row">
                    <label for="seller_address" class="col-md-2 col-form-label text-right font-weight-bold">Seller Address:</label>
                    <div class="col-md-10">
                        <textarea id="seller_address" name="seller_info[seller_address]" class="form-control border rounded shadow-sm">{{ $sellerInfo['seller_address'] }}</textarea>
                    </div>
                </div>

                <div class="form-group row">
                    <label for="seller_gstin" class="col-md-2 col-form-label text-right font-weight-bold">Seller GSTIN:</label>
                    <div class="col-md-4">
                        <input type="text" id="seller_gstin" name="seller_info[seller_gstin]" class="form-control border rounded shadow-sm" value="{{ $sellerInfo['seller_gstin'] }}">
                    </div>
                </div>

                <!-- Global Inputs for Seller Invoice No and Date -->
                <div class="form-group row">
                   
                        <label for="seller_invoice_no" class="col-md-2 col-form-label text-right font-weight-bold">Seller Invoice No:</label>
                        <div class="col-md-4">
                            <input type="text" id="seller_invoice_no" name="seller_invoice_no" class="form-control border rounded shadow-sm" placeholder="Enter Invoice Number">
                        </div>

                        <label for="seller_invoice_date" class="col-md-2 col-form-label text-right font-weight-bold">Seller Invoice Date:</label>
                        <div class="col-md-4">
                            <input type="date" id="seller_invoice_date" name="seller_invoice_date" class="form-control border rounded shadow-sm">
                        </div>
                   
                </div>


                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>Part No.</th>
                            <th>HSN Code</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Purchase Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        @php $grandTotal = 0; @endphp
                        @foreach($productInfo as $index => $product)
                            @php
                                $subtotal = $product['qty'] * $product['purchase_price'];
                                $grandTotal += $subtotal;
                            @endphp
                            <tr>
                                <td>{{ $product['part_no'] }}</td>
                                <td><input type="text" name="products[{{ $index }}][hsncode]" value="{{ $product['hsncode'] }}" class="form-control"></td>
                                <td>{{ $product['product_name'] }}</td>
                                <td>
                                    <input type="number" name="products[{{ $index }}][qty]" value="{{ $product['qty'] }}" class="form-control qty-input" data-index="{{ $index }}">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="products[{{ $index }}][purchase_price]" value="{{ $product['purchase_price'] }}" class="form-control price-input" data-index="{{ $index }}">
                                </td>
                                <td class="subtotal" data-index="{{ $index }}">{{ $subtotal }}</td>
                            </tr>
                            <input type="hidden" name="products[{{ $index }}][part_no]" value="{{ $product['part_no'] }}">
                            <input type="hidden" name="products[{{ $index }}][order_no]" value="{{ $product['order_no'] }}">
                            <input type="hidden" name="products[{{ $index }}][age]" value="{{ $product['age'] }}">
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Total:</th>
                            <th id="grandTotal">{{ $grandTotal }}</th>
                        </tr>
                    </tfoot>
                </table>

                @if ($order->force_closed == 0 && $order->is_closed == 0)
                    <div style="display:none;" class="mt-4 text-left">
                        <button type="submit" class="btn btn-success py-2">
                            <i class="las la-check"></i> Convert to Purchase
                        </button>
                    </div>
                @else
                    <div class="mt-4 text-left">
                        <button type="button" class="btn btn-secondary py-2" disabled>
                            <i class="las la-times"></i> Order Cancelled
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>

@endsection


@section('script')
<script>
    $(function () {
        console.log("jQuery is working!");

        // Listen for input changes on qty and price fields
        $(document).on("input change keyup", ".qty-input, .price-input", function () {
            let index = $(this).data("index");
            recalculateSubtotal(index);
        });

        function recalculateSubtotal(index) {
            let qty = Number($(`input[name='products[${index}][qty]']`).val()) || 0;
            let price = Number($(`input[name='products[${index}][purchase_price]']`).val()) || 0;
            let subtotal = qty * price;

            // Update the subtotal in the table
            $(`.subtotal[data-index='${index}']`).text(subtotal.toFixed(2));

            // Recalculate the grand total
            recalculateGrandTotal();
        }

        function recalculateGrandTotal() {
            let grandTotal = 0;
            $(".subtotal").each(function () {
                grandTotal += Number($(this).text()) || 0;
            });

            // Update the grand total in the footer
            $("#grandTotal").text(grandTotal.toFixed(2));
        }
    });
</script>
@endsection

