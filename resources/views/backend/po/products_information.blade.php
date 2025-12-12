@extends('backend.layouts.app')

@section('content')

<style>
/* BUTTONS */

.convert-btn {
    background-color: #358DB0;
    color: #fff;
    font-weight: 600;
    padding: 10px 25px;
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease-in-out;
}

.convert-btn:hover {
    background-color: #2c7ca0;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

.new-product-btn {
    background-color: #358DB0;
    color: #fff;
    font-weight: 600;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease-in-out;
}

.new-product-btn:hover {
    background-color: #2c7ca0;
    transform: translateY(-2px);
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
}

/* HEADINGS */

.section-heading {
    background-color: #358DB0 !important;
    color: #fff;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 18px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    margin-bottom: 20px;

    /* Animation */
    opacity: 0;
    animation: fadeInUp 0.6s ease-in-out forwards;
    animation-delay: 0.2s;
}

/* INPUT FIELDS */

.table input.form-control {
    border-radius: 6px;
    border: 1px solid #ddd;
    box-shadow: none;
    font-size: 14px;
}

.table input.form-control:focus {
    border-color: #358DB0;
    box-shadow: 0 0 0 0.15rem rgba(53, 141, 176, 0.25);
}

/* SELECT DROPDOWN ARROW */

select {
    appearance: none;
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 12px 12px;
}

/* SUBTOTAL */

.subtotal {
    font-weight: 600;
    color: #358DB0;
}

/* DELETE ICON */

.delete-row i:hover {
    transform: scale(1.1);
    color: #ff3b3b;
    transition: all 0.2s ease-in-out;
}

/* TABLE STYLES */

.table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.table tbody tr:hover {
    background-color: #eef4ff;
    transition: background-color 0.3s ease;
}

/* ANIMATIONS */

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.blink-total {
    animation: blink 1s ease-in-out infinite;
}


.custom-thead {
    background-color: #358DB0;
    color: #ffffff;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.custom-thead th {
    font-weight: 600;
    font-size: 13px;
    letter-spacing: 0.5px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    border: none;
    padding-top: 12px;
    padding-bottom: 12px;
}
.product-header {
    padding: 12px 20px;
    background-color: #358DB0;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.product-header .section-heading {
    background: none;
    color: #fff;
    font-weight: 600;
    font-size: 18px;
    margin: 0;
    padding: 0;
    box-shadow: none;
    animation: none;
}

.product-header .new-product-btn {
    background-color: #ffffff;
    color: #358DB0;
    font-weight: 600;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease-in-out;
}

.product-header .new-product-btn:hover {
    background-color: #f1f9ff;
    color: #2c7ca0;
    transform: translateY(-1px);
}

.rotate-icon {
    transition: transform 0.3s ease;
}

.collapsed .rotate-icon {
    transform: rotate(180deg);
}

.row-highlight {
    background-color: #fff7d6 !important; /* Light yellow */
/*     background-color: #e1f5fe !important;  Light blue /*/
    transition: background-color 0.3s ease;
}

.newly-added-row {
    background-color: #e6f9e6 !important; /* Light green */
    transition: background-color 0.5s ease-in-out;
}

</style>

    <div class="aiz-titlebar text-left mt-2 mb-3">
        {{-- <h1 class="h3">Products Information for Order: {{ $order->purchase_order_no }}</h1> --}}
        <h1 class="h3">Products Information for Order</h1>
       
    </div>
     <!-- Display Validation Errors -->
        @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error:</strong> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

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

     <form id="convert-purchase-form" action="{{ route('purchase-orders.convert', $order->id) }}" method="POST" class="p-4 bg-white border rounded shadow-sm">
    @csrf

   <!-- 🔐 Hidden Seller ID -->
<input type="hidden" name="seller_id" id="seller_id" value="{{ $order->seller_id ?? '' }}">


    <input type="hidden" name="purchase_order_no" value="{{ $order->purchase_order_no }}">
    <input type="hidden" name="purchase_no" value="{{ $purchaseNo }}">

    <!-- 🧾 Seller Info Section -->
    <div class="mb-4 border rounded bg-light">
    <!-- Collapsible Header -->
    <h5 class="mb-0 bg-primary text-white font-weight-bold section-heading d-flex justify-content-between align-items-center px-3 py-2" 
        data-toggle="collapse" 
        data-target="#sellerInfoCollapse" 
        style="cursor: pointer;">
        <span><i class="las la-user-tag mr-2"></i> Seller Information</span>
        <i class="las la-angle-down rotate-icon" id="sellerToggleIcon"></i>
    </h5>

  
    <!-- 🧾 Seller Info Section -->


    <!-- Collapsible Body -->
    <div id="sellerInfoCollapse" class="collapse show">
        <div class="p-3">

            <!-- Seller Dropdown -->
            <div class="form-group">
                <label for="seller_dropdown">Select Seller</label>
                <select id="seller_dropdown" class="form-control">
    <option value="create_new">➕ Create Seller</option>
   @foreach($all_sellers as $seller)
    <option value="{{ $seller->seller_id }}"
        data-name="{{ $seller->seller_name }}"
        data-address="{{ $seller->seller_address }}"
        data-gstin="{{ $seller->gstin }}"
        data-phone="{{ $seller->seller_phone }}"
        data-state="{{ $seller->state_name }}"
        @if($seller->seller_id == $order->seller_id) selected @endif>
        {{ $seller->seller_name }}
    </option>
@endforeach
</select>
            </div>

            <!-- Seller Fields -->
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="seller_name">Seller Name</label>
                    <input type="text" id="seller_name" name="seller_info[seller_name]" class="form-control" value="{{ $sellerInfo['seller_name'] }}">
                </div>
                <div class="form-group col-md-6">
                    <label for="seller_phone">Seller Phone</label>
                    <input type="text" id="seller_phone" name="seller_info[seller_phone]" class="form-control" value="{{ $sellerInfo['seller_phone'] }}">
                </div>
            </div>

            <div class="form-group">
                <label for="seller_address">Seller Address</label>
                <textarea id="seller_address" name="seller_info[seller_address]" class="form-control" rows="2">{{ $sellerInfo['seller_address'] }}</textarea>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="seller_gstin">Seller GSTIN</label>
                    <input type="text" id="seller_gstin" name="seller_info[seller_gstin]" class="form-control" value="{{ $sellerInfo['seller_gstin'] }}">
                </div>

                <!-- State Dropdown for new seller -->
               <div class="form-group col-md-6" id="state_wrapper" style="display: none;">
    <label for="state_id">Select State</label>
    <select name="seller_info[state_name]" id="state_id" class="form-control">
        <option value="">-- Select State --</option>
        @foreach($states as $state)
            <option value="{{ $state->name }}">{{ $state->name }}</option>
        @endforeach
    </select>
</div>

            </div>

        </div>
    </div>
</div>



    <!-- 📄 Invoice Info Section -->
    <div class="mb-4 border rounded bg-light">
    <!-- Collapsible Header -->
    <h5 class="mb-0 bg-primary text-white font-weight-bold section-heading d-flex justify-content-between align-items-center px-3 py-2" 
        data-toggle="collapse" 
        data-target="#invoiceDetailsCollapse" 
        style="cursor: pointer;">
        <span><i class="las la-file-invoice mr-2"></i> Invoice Details</span>
        <i class="las la-angle-down rotate-icon" id="invoiceToggleIcon"></i>
    </h5>

    <!-- Collapsible Body -->
    <div id="invoiceDetailsCollapse" class="collapse show">
        <div class="form-row p-3">
            <div class="form-group col-md-6">
                <label>Seller Invoice No</label>
                <input type="text" name="seller_invoice_no" class="form-control" placeholder="Enter Invoice Number">
            </div>
            <div class="form-group col-md-6">
                <label>Seller Invoice Date</label>
                <input type="date" name="seller_invoice_date" class="form-control">
            </div>
        </div>
    </div>
</div>


    <!-- ➕ Add Product Button -->

   <div style="position:relative;top: 30px; " class="product-header d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 text-white font-weight-bold d-flex align-items-center" style="font-size: 18px;">
            <i class="las la-boxes mr-2"></i> Purchase Order Product Details
        </h5>
        <button type="button" class="btn new-product-btn" data-toggle="modal" data-target="#addProductModal">
            <i class="las la-plus-circle"></i> New Product
        </button>
    </div>



    <!-- 📦 Product Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover shadow-sm bg-white rounded">
           <thead class="custom-thead">
                <tr class="text-center align-middle">
                    <th>#</th> <!-- ✅ Serial No. -->
                    <th class="font-weight-bold text-uppercase py-3">Part No.</th>
                    <th class="font-weight-bold text-uppercase py-3">HSN Code</th>
                    <th class="font-weight-bold text-uppercase py-3 text-left">Product</th>
                    <th class="font-weight-bold text-uppercase py-3">Purchase Order No.</th>
                    <th class="font-weight-bold text-uppercase py-3">Quantity</th>
                    <th class="font-weight-bold text-uppercase py-3">Price Without GST</th>
                    <th class="font-weight-bold text-uppercase py-3">Price</th>
                    <th class="font-weight-bold text-uppercase py-3">Subtotal</th>
                    <th class="font-weight-bold text-uppercase py-3">Action</th>
                </tr>
            </thead>



            <tbody id="productTableBody">
                @php $grandTotal = 0; @endphp
                @foreach($productInfo as $index => $product)
                    @php
                        $subtotal = $product['pending'] * $product['purchase_price'];
                        $grandTotal += $subtotal;
                    @endphp
                    <tr class="text-center align-middle">
                        <td class="serial-no">{{ $index + 1 }}</td> <!-- ✅ Serial No. -->
                        <td class="align-middle font-weight-bold text-dark">{{ $product['part_no'] }}</td>
                        <td style="width: 120px;border-radius: 20px">
                            <input type="text" name="products[{{ $index }}][hsncode]" value="{{ $product['hsncode'] }}" class="form-control form-control-sm text-center border-primary">
                        </td>
                        <td class="text-left align-middle">{{ $product['product_name'] }}</td>
                        <td>
                           <select name="products[{{ $index }}][purchase_order_no]" class="form-control form-control-sm border-primary">
                            @if(isset($partPurchaseOrders[$product['part_no']]))
                                @foreach($partPurchaseOrders[$product['part_no']] as $po)
                                    <option value="{{ $po }}" {{ $product['purchase_order_no'] == $po ? 'selected' : '' }}>
                                        {{ $po }} ({{ $poPendingQty[$product['part_no']][$po] ?? 0 }})
                                    </option>
                                @endforeach
                            @endif
                        </select>

                        </td>
                        <td style="width: 100px;">
                            <input type="number" name="products[{{ $index }}][pending]" value="{{ $product['pending'] }}" class="form-control form-control-sm qty-input text-center border-secondary" data-index="{{ $index }}">
                        </td>
                        <!-- Price Without GST -->
                        <td style="width: 100px;">
                            <input type="text" name="products[{{ $index }}][exclusive_price]" value="{{ $product['exclusive_price'] }}" class="form-control form-control-sm excl-price-input text-center border-secondary" data-index="{{ $index }}" data-tax="{{ $product['tax'] }}">
                        </td>
                        <td style="width: 100px;">
                            <input type="number" name="products[{{ $index }}][purchase_price]" value="{{ $product['purchase_price'] }}" class="form-control form-control-sm price-input text-center border-secondary" data-index="{{ $index }}" readonly>
                        </td>
                        <td class="subtotal text-right text-muted align-middle" data-index="{{ $index }}">
                            {{ ($subtotal) }}
                        </td>
                       {{--  <td>
                            <button type="button" class="btn btn-sm delete-row"
                                data-index="{{ $index }}"
                                data-id="{{ $product['part_no'] }}"
                                data-po="{{ $product['purchase_order_no'] }}"
                                data-purchase_order_details_id="{{ $product['purchase_order_details_id'] }}"
                                data-pending="{{ $product['pending'] }}">
                                <i class="las la-trash delete-row" data-rowid="1" style="font-size: 25px; color:#f00; cursor:pointer;"></i>
                            </button>
                        </td>--}}
                        <td>
                            <a href="javascript:void(0)" 
                               class="delete-row"
                               data-index="{{ $index }}"
                               data-id="{{ $product['part_no'] }}"
                               data-po="{{ $product['purchase_order_no'] }}"
                               data-purchase_order_details_id="{{ $product['purchase_order_details_id'] }}"
                               data-pending="{{ $product['pending'] }}">
                                <i class="las la-trash" data-rowid="1" style="font-size: 25px; color:#f00; cursor:pointer;"></i>
                            </a>
                        </td>

                    </tr>
                    <input type="hidden" name="products[{{ $index }}][part_no]" value="{{ $product['part_no'] }}">
                    <input type="hidden" name="products[{{ $index }}][order_no]" value="{{ $product['order_no'] }}">
                    <input type="hidden" name="products[{{ $index }}][age]" value="{{ $product['age'] }}">
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" class="text-right font-weight-bold">Grand Total</td>
                    <td style="font-size: 13px;" id="grandTotal" class="text-primary font-weight-bold text-right h5 ">
                       ₹ {{ number_format($grandTotal, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ✅ Submit CTA -->
    <div class="mt-4 text-right">
        @if ($order->force_closed == 0 && $order->is_closed == 0)
            <button style="font-size:12px;" type="submit" class="convert-btn ">
                <i class="las la-check-circle"></i> Convert to Purchase
            </button>
        @else
            <button type="button" class="btn btn-lg btn-secondary px-4" disabled>
                <i class="las la-times-circle"></i> Order Cancelled
            </button>
        @endif
    </div>
</form>


        </div>
    </div>



    <!-- model -->

   <!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header section-heading">
                <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span style="color:white !important;" aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="deleteForm">
                    <!-- Checkbox for Unavailable by Seller -->
                    <div class="form-group">
                        <input type="checkbox" id="unavailableBySeller" name="unavailable_by_seller">
                        <label for="unavailableBySeller">Unavailable by Seller</label>
                    </div>

                    <!-- Quantity Input -->
                    <div class="form-group" id="quantityInputGroup">
                        <label for="deleteQuantity" class="font-weight-bold">Quantity</label>
                        <input type="number" id="deleteQuantity" name="quantity" class="form-control" placeholder="Enter Quantity" min="1">
                    </div>

                    <input type="hidden" id="deleteRowIndex">
                    <input type="hidden" id="deleteRowId"> <!-- Store row ID -->
                    <input type="hidden" id="deletePurchaseOrderNo">
                    <input type="hidden" id="deletePurchaseOrderDetailsId">
                    <input type="hidden" id="maxPendingQty">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>



<!-- ✅ Stylish Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white section-heading">
                <h5 class="modal-title font-weight-bold" id="addProductModalLabel ">
                    <i class="las la-plus-circle mr-2"></i>Add Product
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body px-4 py-3">
                <form id="addProductForm">
                    <div class="form-row">
                        <!-- PO Number -->
                        <div class="form-group col-md-6">
                            <label for="purchaseOrderNo">Purchase Order No:</label>
                            <select id="purchaseOrderNo" name="purchase_order_no" class="form-control">
                                @foreach($purchaseOrderNos as $po)
                                    <option value="{{ $po }}">{{ $po }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Category Group -->
                        <div class="form-group col-md-6">
                            <label>Category Group</label>
                            <select class="form-control aiz-selectpicker" name="category_group_id" id="category-group-select" data-live-search="true">
                                <option value="">-- Select Group --</option>
                                @foreach($categoryGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <!-- Category -->
                        <div class="form-group col-md-6">
                            <label for="categorySelect">Category</label>
                            <select class="form-control aiz-selectpicker" name="category_ids[]" multiple data-live-search="true" id="category-select">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Brand -->
                        <div class="form-group col-md-6">
                            <label for="brandSelect">Brand</label>
                            <select class="form-control aiz-selectpicker" name="brand_ids[]" multiple data-live-search="true" id="brand-select">
                                <option value="">Select Brand</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Product Select -->
                    <div class="form-group">
                        <label for="productSelect">Select Product</label>
                        <select id="productSelect" name="product_id" class="form-control aiz-selectpicker" data-live-search="true">
                            <option value="">-- Select Product --</option>
                            @foreach($allProducts as $product)
                                <option value="{{ $product->id }}"
                                        data-part-no="{{ $product->part_no }}"
                                        data-hsncode="{{ $product->hsncode }}"
                                        data-tax="{{ $product->tax ?? 0 }}" 
                                        data-price="{{ $product->purchase_price }}">
                                    {{ $product->name }} ({{ $product->part_no }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <!-- Qty -->
                        <div class="form-group col-md-4">
                            <label for="productQty">Quantity</label>
                            <input type="number" id="productQty" name="quantity" class="form-control" min="1" placeholder="Enter quantity">
                        </div>

                        <!-- Order No -->
                        <div class="form-group col-md-4">
                            <label for="orderNo">Order No</label>
                            <input type="text" id="orderNo" name="order_no" class="form-control" placeholder="Enter Order No">
                        </div>

                        <!-- Age -->
                        <div class="form-group col-md-4">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" class="form-control" min="0" placeholder="Enter Age">
                        </div>
                    </div>

                    <input type="hidden" id="selectedPartNo">
                    <input type="hidden" id="selectedHsncode">
                    <input type="hidden" id="selectedPrice">
                </form>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="las la-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmAddProduct">
                    <i class="las la-plus"></i> Add
                </button>
            </div>
        </div>
    </div>
</div>





@endsection


@section('script')
<script>
    $(function () {
        console.log("jQuery is working!");
         let selectedRowIndex;
         

        // Listen for input changes on qty and price fields
        $(document).on("input change keyup", ".qty-input, .price-input", function () {
            let index = $(this).data("index");
            recalculateSubtotal(index);
        });

        // 🔁 When 'Price Without GST' changes, auto-update 'Price'
        $(document).on("input", ".excl-price-input", function () {
            const index = $(this).data("index");
            const tax = parseFloat($(this).data("tax")) || 0;
            const exclPrice = parseFloat($(this).val()) || 0;

            // Calculate price including GST
            const priceInclGST = exclPrice * (1 + tax / 100);
            $(`input[name='products[${index}][purchase_price]']`).val(priceInclGST.toFixed(2));

            // Recalculate subtotal
            recalculateSubtotal(index);
        });

        function recalculateSubtotal(index) {
            let qty = Number($(`input[name='products[${index}][pending]']`).val()) || 0;
            let price = Number($(`input[name='products[${index}][purchase_price]']`).val()) || 0;
            let subtotal = qty * price;

            // Update the subtotal in the table with ₹
            $(`.subtotal[data-index='${index}']`).text(`₹ ${subtotal.toFixed(2)}`);

            // Recalculate the grand total
            recalculateGrandTotal();
        }

        function recalculateGrandTotal() {
            let grandTotal = 0;
            $(".subtotal").each(function () {
                let text = $(this).text().replace(/[^\d.-]/g, ''); // Remove ₹ symbol
                grandTotal += Number(text) || 0;
            });

            // Update the grand total in the footer with ₹
            $("#grandTotal").text(`₹ ${grandTotal.toFixed(2)}`);
        }

        // ✅ Trigger recalculation on page load
        $("#productTableBody tr").each(function () {
            let index = $(this).find(".qty-input").data("index");
            if (typeof index !== 'undefined') {
                recalculateSubtotal(index);
            }
        });


        $('#convert-purchase-form').on('submit', function (e) {
            let selectedPOs = [];

            // Loop through all selected POs
            $("select[name^='products']").each(function () {
                let selectedValue = $(this).val();
                if (selectedValue) {
                    selectedPOs.push(selectedValue);
                }
            });

            // Check for duplicate POs
            let duplicates = selectedPOs.filter((item, index) => selectedPOs.indexOf(item) !== index);

            if (duplicates.length > 0) {
                let confirmProceed = confirm("Warning: You have selected the same PO multiple times. Do you want to proceed?");
                if (!confirmProceed) {
                    e.preventDefault(); // Stop form submission
                }
            }
        });


      // ✅ Open Modal When Delete Button is Clicked
    $(document).on("click", ".delete-row", function () {

        let rowIndex = $(this).data("index");
        let rowId = $(this).data("id"); // ✅ Now rowId will correctly get part_no
        let poNumber = $(this).data("po"); // ✅ capture PO number
        let podId = $(this).data("purchase_order_details_id"); // ✅ get details ID
        let pendingQty = $(this).data("pending");

        console.log("Clicked rowIndex:", rowIndex); // ✅ Debugging: Ensure it's capturing index
        console.log("Clicked rowId:", rowId); // ✅ Debugging: Ensure it's capturing ID correctly

        $("#deleteRowIndex").val(rowIndex); // Store index
        $("#deleteRowId").val(rowId); // Store ID
        $("#deletePurchaseOrderNo").val(poNumber); // ✅ store in hidden field
         $("#deletePurchaseOrderDetailsId").val(podId); // ✅ set to hidden input
        $("#deleteQuantity").val(""); // Reset quantity input
        $("#unavailableBySeller").prop("checked", false); // Reset checkbox
        $("#quantityInputGroup").show(); // Show quantity input
        $("#deleteConfirmationModal").modal("show"); // Show modal
        $("#maxPendingQty").val(pendingQty); // Store pending qty
    });

    // ✅ Toggle Quantity Input When Checkbox is Checked
        $("#unavailableBySeller").change(function () {
            if ($(this).prop("checked")) {
                $("#quantityInputGroup").hide(); // Hide input
                $("#deleteQuantity").val(""); // Clear quantity
            } else {
                $("#quantityInputGroup").show(); // Show input
            }
        });

     // ✅ Confirm Update (Instead of Delete)
         $("#confirmDelete").click(function () {
            
            let rowIndex = $("#deleteRowIndex").val();
            let rowId = $("#deleteRowId").val();
             let poNumber = $("#deletePurchaseOrderNo").val(); // ✅ get PO from hidden input
             let podId = $("#deletePurchaseOrderDetailsId").val(); // ✅ retrieve value
            let isUnavailable = $("#unavailableBySeller").prop("checked");
            let quantity = $("#deleteQuantity").val();

            let maxPendingQty = parseInt($("#maxPendingQty").val());
            // max pending quentiy validatiaon
            if (!isUnavailable) {
                if (isNaN(quantity)) {
                    alert("Please enter a quantity.");
                    return;
                }

                if (quantity <= 0) {
                    alert("Quantity must be greater than 0.");
                    return;
                }

                if (quantity > maxPendingQty) {
                    alert("Quantity cannot exceed pending quantity (" + maxPendingQty + ").");
                    return;
                }
            }else {
                // ✅ If Unavailable is checked, we just override quantity to 0
                quantity = 0;
            }

            // ✅ Validate Quantity (if checkbox is unchecked)
            if (!isUnavailable && (quantity === "" || parseInt(quantity) <= 0)) {
                alert("Please enter a valid quantity.");
                return;
            }

            // ✅ AJAX Request to Update `pre_close`, `pending`, and Stock
            $.ajax({
                url: "{{ route('update.preclose.stock') }}", // ✅ Ensure route matches Laravel
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    part_no: rowId, // ✅ rowId is now properly retrieved
                    purchase_order_no: poNumber, // ✅ send PO number
                     purchase_order_details_id: podId, // ✅ send in request
                    unavailable_by_seller: isUnavailable ? 1 : 0,
                    quantity: isUnavailable ? 0 : quantity
                },
                success: function (response) {
                    alert(response.message);
                    $("#deleteConfirmationModal").modal("hide"); // Hide modal
                    
                   // location.reload();
                    // ✅ Update Table Row (Instead of Removing It)
                    $("#deleteConfirmationModal").modal("hide"); // Hide modal
                    // ✅ Remove the row from DOM
                    $(`#productTableBody tr:eq(${rowIndex})`).remove();
                    // ✅ Remove any associated hidden inputs (if any)
                    $(`input[name='products[${rowIndex}][part_no]']`).remove();
                    $(`input[name='products[${rowIndex}][order_no]']`).remove();
                    $(`input[name='products[${rowIndex}][age]']`).remove();

                    // ✅ Recalculate totals
                    recalculateGrandTotal();
                    
                },
                error: function (xhr, status, error) {
                    console.log("AJAX Error:", xhr.responseText); // ✅ Show full server response in console
                    alert("Error: " + xhr.responseText); // ✅ Display full error message
                }
            });
        });



          // Auto-fill hidden fields when a product is selected
         $('#confirmAddProduct').click(function() {
            let purchaseOrderNo = $('#purchaseOrderNo').val();
            let productId = $('#productSelect').val();
            let quantity = $('#productQty').val();
            let orderNo = $('#orderNo').val();
            let age = $('#age').val();
            // alert("something went wrong");
            // return;

            // Get additional data from selected product
            let selectedOption = $('#productSelect option:selected');
            let partNo = selectedOption.data('part-no');
            let hsncode = selectedOption.data('hsncode');
           
            let price = selectedOption.data('price');

            if (!purchaseOrderNo || !productId || !quantity || !partNo || !hsncode || !price) {
                alert("Please fill in all required fields.");
                return;
            }

            // Disable button to prevent duplicate submissions
            $('#confirmAddProduct').prop('disabled', true).text('Adding...');

            $.ajax({
                url: "{{ route('purchase-orders.addToPurchaseOrderDetails') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    purchase_order_no: purchaseOrderNo,
                    product_id: productId,
                    part_no: partNo,
                    hsncode: hsncode,
                    purchase_price: price,
                    quantity: quantity,
                    order_no: orderNo,
                    age: age
                },
                success: function(response) {
                    alert(response.message);
                    $('#addProductModal').modal('hide');
                    // location.reload();

                    // Only add one new row from the form, not the whole table
                   let newIndex = $('#productTableBody tr').length;
                    let taxRate = parseFloat(selectedOption.data('tax')) || 0;
                    let exclusivePrice = taxRate > 0 ? (price / (1 + taxRate / 100)) : price;
                    let partNo = $('#selectedPartNo').val();
                    const detailId = response.purchase_order_details_id; // ✅ fetched ID
                    let serialNo = newIndex + 1;


                    let row = `
                        <tr class="text-center align-middle">
                            <td class="serial-no">${serialNo}</td>
                            <td class="align-middle font-weight-bold text-dark">${partNo}</td>
                            <td><input type="text" name="products[${newIndex}][hsncode]" value="${$('#selectedHsncode').val()}" class="form-control form-control-sm text-center border-primary"></td>
                            <td class="text-left align-middle">${$('#productSelect option:selected').text()}</td>
                            <td>
                                <select name="products[${newIndex}][purchase_order_no]" class="form-control form-control-sm border-primary">
                                    <option value="${purchaseOrderNo}">${purchaseOrderNo} (${quantity})</option>
                                </select>
                            </td>
                            <td><input type="number" name="products[${newIndex}][pending]" value="${quantity}" class="form-control form-control-sm qty-input text-center border-secondary" data-index="${newIndex}"></td>
                            <td><input type="number" name="products[${newIndex}][exclusive_price]" value="${exclusivePrice.toFixed(2)}" class="form-control form-control-sm text-center border-secondary" readonly></td>
                            <td><input type="number" name="products[${newIndex}][purchase_price]" value="${price.toFixed(2)}" class="form-control form-control-sm price-input text-center border-secondary" data-index="${newIndex}"></td>
                            <td class="subtotal text-right text-muted align-middle" data-index="${newIndex}">₹ ${(price * quantity).toFixed(2)}</td>
                            <td>
                                <a href="javascript:void(0)" 
                                    class="delete-row"
                                    data-index="${newIndex}"
                                    data-id="${partNo}"
                                    data-po="${purchaseOrderNo}"
                                    data-purchase_order_details_id="${detailId}" // ✅ use real ID
                                    data-pending="${quantity}">
                                    <i class="las la-trash" style="font-size: 25px; color:#f00; cursor:pointer;"></i>
                                </a>
                            </td>
                        </tr>
                        <input type="hidden" name="products[${newIndex}][part_no]" value="${partNo}">
                        <input type="hidden" name="products[${newIndex}][order_no]" value="${orderNo}">
                        <input type="hidden" name="products[${newIndex}][age]" value="${age}">
                    `;

                    $('#productTableBody').append(row);
                    recalculateGrandTotal(); // ✅ Refresh total
                },
                error: function(xhr) {
                    alert("Error: " + xhr.responseText);
                },
                complete: function() {
                    // Re-enable button after request completes
                    $('#confirmAddProduct').prop('disabled', false).text('Add');
                }
            });
        });
        
        // on edit color changing function
         $(document).on("input", "input[name*='[hsncode]'], input[name*='[pending]'], input[name*='[exclusive_price]']", function () {
            const $row = $(this).closest("tr");

            // ✅ Add highlight to this row
            $row.addClass("row-highlight");
        });


        // Handle product selection change
        $('#productSelect').change(function() {
            let selectedOption = $(this).find(':selected');
            let partNo = selectedOption.data('part-no');
            let hsncode = selectedOption.data('hsncode');
            let price = selectedOption.data('price');


            $('#selectedPartNo').val(partNo);
            $('#selectedHsncode').val(hsncode);
            $('#selectedPrice').val(price);
        });



        //dynamic select section 

        function refreshProductDropdown(categoryIds = [], brandIds = []) {
            $('#productSelect').html('<option value="">-- Select Product --</option>');

            $.ajax({
                url: '{{ route("find-products-by-category-and-brand") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    category_ids: categoryIds,
                    brand_ids: brandIds
                },
                success: function (response) {
                    $.each(response, function (index, product) {
                        $('#productSelect').append(
                            '<option value="' + product.id + '" ' +
                                'data-part-no="' + product.part_no + '" ' +
                                'data-hsncode="' + product.hsncode + '" ' +
                                'data-price="' + product.purchase_price + '">' +
                                product.name + ' (' + product.part_no + ')' +
                            '</option>'
                        );
                    });

                    $('#productSelect').selectpicker('refresh');
                }
            });
        }

        // On Category Change
        $('#category-select').on('change', function () {
            const categoryIds = $(this).val();
            const brandIds = $('#brand-select').val();

            $('#brand-select').html('<option value="">Select Brand</option>');
            $('#productSelect').html('<option value="">-- Select Product --</option>');

            if (categoryIds && categoryIds.length > 0) {
                // Load Brands by Category
                $.ajax({
                    url: '{{ url("/find-brands-by-category") }}/' + categoryIds.join(','),
                    method: 'GET',
                    success: function (response) {
                        $.each(response, function (index, brand) {
                            $('#brand-select').append('<option value="' + brand.id + '">' + brand.name + '</option>');
                        });

                        $('#brand-select').selectpicker('refresh');
                    }
                });
            }

            refreshProductDropdown(categoryIds, brandIds);
        });

        // On Brand Change
        $('#brand-select').on('change', function () {
            const brandIds = $(this).val();
            const categoryIds = $('#category-select').val();
            refreshProductDropdown(categoryIds, brandIds);
        });

        $('#category-group-select').on('change', function () {
            let groupId = $(this).val();

            // Clear Category dropdown
            $('#category-select').html('');
            $('#category-select').append('<option value="">-- Select Category --</option>');

            if (groupId) {
                $.ajax({
                    url: '/find-categories-by-group/' + groupId,
                    method: 'GET',
                    success: function (response) {
                        $.each(response, function (index, category) {
                            $('#category-select').append('<option value="' + category.id + '">' + category.name + '</option>');
                        });
                        $('#category-select').selectpicker('refresh');
                    }
                });
            } else {
                $('#category-select').selectpicker('refresh');
            }
        });

        const $dropdown = $('#seller_dropdown');
        const $stateWrapper = $('#state_wrapper');
        const $sellerId = $('#seller_id');

        function updateSellerFields() {
            const selectedVal = $dropdown.val();
            const selectedOption = $dropdown.find('option:selected');

            if (selectedVal === 'create_new') {
                // Show state dropdown and clear fields
                $('#seller_name').val('');
                $('#seller_phone').val('');
                $('#seller_address').val('');
                $('#seller_gstin').val('');
                $('#state_id').val('');
                $sellerId.val('');
                $stateWrapper.show();
            } else {
                // Prefill seller info
                $('#seller_name').val(selectedOption.data('name'));
                $('#seller_phone').val(selectedOption.data('phone'));
                $('#seller_address').val(selectedOption.data('address'));
                $('#seller_gstin').val(selectedOption.data('gstin'));
                $sellerId.val(selectedVal);

                const stateName = selectedOption.data('state');
                if (stateName) {
                    $('#state_id').val(stateName); // ✅ Set the dropdown to correct state
                }

                $stateWrapper.hide(); // Hide state dropdown for existing sellers
            }
        }

        // Initial trigger on page load
        updateSellerFields();
        // Trigger on change
        $dropdown.on('change', updateSellerFields);

    });
</script>
@endsection

