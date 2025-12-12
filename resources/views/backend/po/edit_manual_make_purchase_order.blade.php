@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">Edit Manual Purchase Order</h1>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>There were some errors with your submission:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.saveEditedManualPO') }}">
            @csrf
            <input type="hidden" name="party_type" value="seller">
            <input type="hidden" name="address_id" id="address_id">

            <!-- Warehouse -->
            <div class="row">
                <div class="col-md-4">
                    <label>Warehouse</label>
                    <select name="warehouse_id" class="form-control" required>
                        <option value="">-- Select Warehouse --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @if($invoice->warehouse_id == $wh->id) selected @endif>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr>

            <!-- Seller Info -->
            <div class="row">
                <div class="col-md-4">
                    <label>Seller</label>
                    <select name="seller_id" id="seller_id" class="form-control" required>
                        <option value="">-- Select Seller --</option>
                        @foreach($all_sellers as $seller)
                            <option 
                                value="{{ $seller->seller_id }}"
                                data-name="{{ $seller->seller_name }}"
                                data-address="{{ $seller->seller_address }}"
                                data-phone="{{ $seller->seller_phone }}"
                                data-gstin="{{ $seller->gstin }}"
                                data-state="{{ $seller->state_name }}"
                                @if($invoice->seller_id == $seller->seller_id) selected @endif
                            >
                                {{ $seller->seller_name }}
                            </option>
                        @endforeach
                        <option value="create_new">+ Create Seller</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>GSTIN</label>
                   <input type="text" id="seller_gstin" name="seller_info[gstin]" class="form-control" readonly
       value="{{ $sellerInfo->gstin ?? '' }}">

                </div>
                <div class="col-md-4">
                    <label>Phone</label>
                    <input type="text" id="seller_phone" name="seller_info[phone]" class="form-control" readonly
       value="{{ $sellerInfo->seller_phone ?? '' }}">

                </div>
                <div class="col-md-8 mt-3">
                    <label>Address</label>
                    <textarea id="seller_address" name="seller_info[address]" class="form-control" rows="2" readonly>{{ $sellerInfo->seller_address ?? '' }}</textarea>
                </div>
                <div class="col-md-4 mt-3" id="stateWrapper" style="display: none;">
    <label>State</label>
    <select name="seller_info[state]" id="seller_state" class="form-control">
        <option value="">-- Select State --</option>
        @foreach($states as $state)
            <option value="{{ $state->id }}" 
                @if(isset($sellerInfo) && $sellerInfo->state_name == $state->name) selected @endif>
                {{ $state->name }}
            </option>
        @endforeach
    </select>
</div>
            </div>

            <hr>
            <input type="hidden" name="seller_info[seller_name]" id="hidden_seller_name" value="{{ $sellerInfo->seller_name ?? '' }}">
<input type="hidden" name="seller_info[seller_phone]" id="hidden_seller_phone" value="{{ $sellerInfo->seller_phone ?? '' }}">
<input type="hidden" name="seller_info[state_name]" id="hidden_seller_state" value="{{ $sellerInfo->state_name ?? '' }}">
<input type="hidden" name="seller_info[seller_id]" id="hidden_seller_id" value="{{ $invoice->seller_id }}">

<input type="hidden" name="purchase_invoice_id" value="{{ $invoice->id }}">

            <!-- Seller Invoice Info (Always visible for Convert) -->
<div class="row mt-3">
    <div class="col-md-4">
        <label>Seller Invoice No</label>
        <input type="text" id="seller_invoice_no" name="seller_invoice_no" class="form-control"
               value="{{ $invoice->seller_invoice_no ?? '' }}">
    </div>
    <div class="col-md-4">
        <label>Seller Invoice Date</label>
        <input type="date" id="seller_invoice_date" name="seller_invoice_date" class="form-control"
               value="{{ $invoice->seller_invoice_date ?? '' }}">
    </div>
</div>
<input type="hidden" name="action" id="actionField" value="">

            <!-- Product Table -->
            <div class="table-responsive">
                <table class="table table-bordered" id="productTable">
                    <thead class="thead-light">
                        <tr>
                            <th>S.No</th>
                            <th>Part No.</th>
                            <th>Product Name</th>
                            <th>Purchase Price</th>
                            <th>HSN Code</th>
                            <th>Price Without GST</th>
                            <th>Quantity</th>
                            <th>PO</th>
                            <th>Sub Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productRows as $row)
                        <tr data-gst="{{ $row['gst'] }}">
                            <td class="serial-number">{{ $loop->iteration }}</td>
                            <td>
                                <input type="hidden" name="orders[{{ $row['index'] }}][product_id]" value="{{ $row['product_id'] }}">
                                <input type="hidden" name="orders[{{ $row['index'] }}][part_no]" value="{{ $row['part_no'] }}">
                                {{ $row['part_no'] }}
                            </td>
                            <td>
                                <input type="hidden" name="orders[{{ $row['index'] }}][product_name]" value="{{ $row['product_name'] }}">
                                {{ $row['product_name'] }}
                            </td>
                            <td>
                                <input type="number" step="0.01" name="orders[{{ $row['index'] }}][purchase_price]" value="{{ $row['purchase_price'] }}" class="form-control purchase-price" readonly>
                            </td>
                            <td>
                                <input type="hidden" name="orders[{{ $row['index'] }}][hsncode]" value="{{ $row['hsncode'] }}">
                                <input type="text" class="form-control" value="{{ $row['hsncode'] }}" readonly>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="orders[{{ $row['index'] }}][price_without_gst]" class="form-control price-without-gst" value="{{ $row['price_without_gst'] }}">
                            </td>
                            <td>
                                <input type="number" name="orders[{{ $row['index'] }}][quantity]" value="{{ $row['quantity'] }}" class="form-control quantity-field" required>
                            </td>
                            <td>
                                <input type="hidden" name="orders[{{ $row['index'] }}][purchase_order_no]" value="{{ $row['purchase_order_no'] }}">
                                <input type="text" class="form-control" value="{{ $row['purchase_order_no'] }}" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control line-total" value="{{ $row['line_total'] }}" readonly>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm remove-row" ><i class="las la-trash"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="row justify-content-end mt-3">
                    <div class="col-md-4">
                        <table class="table table-bordered">
                            <tr>
                                <th>Total Amount</th>
                                <td><input type="text" class="form-control" id="total" readonly></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="text-right mt-3">
                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#addProductModal">+ Add Product</button>
                <button type="submit" class="btn btn-success">Update Purchase Order</button>
                <button type="button" class="btn btn-warning" id="convertBtn">Convert to Purchase</button>
            </div>
        </form>
    </div>
</div>



<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Add Product</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">


        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Category Group</label>
                <select class="form-control selectpicker" id="category-group-select" data-live-search="true">
                    <option value="">-- Select Group --</option>
                    @foreach ($categoryGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-4">
                <label>Category</label>
                <select class="form-control selectpicker" id="category-select" data-live-search="true">
                    <option value="">-- Select Category --</option>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label>Brand</label>
                <select class="form-control selectpicker" id="brand-select" data-live-search="true">
                    <option value="">-- Select Brand --</option>
                </select>
            </div>
         
            <!--  <div class="form-group col-md-3">
                 <label>Search by Part No.</label>
                 <input type="text" id="searchPartNo" class="form-control" placeholder="Type Part No.">
            </div> -->

         
        </div>

        <!-- Place "Search By" in a new row -->
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Search By</label>
                <select class="form-control" id="searchBySelect">
                    <option value="part_no">Part No.</option>
                    <option value="name">Product Name</option>
                </select>
            </div>

            <div class="form-group col-md-8">
                <label id="searchLabel">Search by Part No.</label>
                <input type="text" id="searchPartNo" class="form-control" placeholder="Type Part No.">
            </div>
        </div>

        <div class="form-group">
            <label>Product</label>
            <select class="form-control selectpicker" id="productSelect" data-live-search="true">
                <option value="">-- Select Product --</option>
            </select>
        </div>
        <div class="form-group" id="existingPOWrapper" style="display: none;">
            <label>Existing POs with this Product</label>
            <select id="existingPOList" class="form-control"  readonly></select>
        </div>

        <div class="form-group">
            <label>HSN Code</label>
            <input type="text" id="productHsn" class="form-control" >
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" id="productQty" class="form-control" min="1">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmAddProduct">Add</button>

      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
<script>
$(document).ready(function () {
    calculateTotals();

    $('#seller_id').on('change', function () {
        const selected = $(this).find(':selected');
        if (selected.val() === 'create_new') {
            $('#seller_gstin, #seller_address, #seller_phone').val('').prop('readonly', false);
            $('#stateWrapper').show();
        } else {
            $('#seller_gstin').val(selected.data('gstin')).prop('readonly', true);
            $('#seller_address').val(selected.data('address')).prop('readonly', true);
            $('#seller_phone').val(selected.data('phone')).prop('readonly', true);
            $('#stateWrapper').hide();
        }
    });

    $('#productTable').on('input', '.price-without-gst', function () {
        const row = $(this).closest('tr');
        const gst = parseFloat(row.data('gst')) || 0;
        const priceWithoutGst = parseFloat($(this).val()) || 0;

        // Calculate price (incl. GST)
        const purchasePrice = (priceWithoutGst * (1 + gst / 100)).toFixed(2);

        // Update the purchase price field
        row.find('.purchase-price').val(purchasePrice);

        // Update line total as well
        const qty = parseFloat(row.find('.quantity-field').val()) || 0;
        const lineTotal = (purchasePrice * qty).toFixed(2);
        row.find('.line-total').val(lineTotal);

        calculateTotals();
    });

    function calculateLineTotal(row) {
        const price = parseFloat(row.find('.price-without-gst').val()) || 0;
        const qty = parseFloat(row.find('.quantity-field').val()) || 0;
        const gst = parseFloat(row.data('gst')) || 0;
        const total = price * qty * (1 + gst / 100);
        row.find('.line-total').val(total.toFixed(2));
    }

    function calculateTotals() {
        let total = 0;

        $('#productTable tbody tr').each(function () {
            const row = $(this);
            const price = parseFloat(row.find('.purchase-price').val()) || 0;
            const qty = parseFloat(row.find('.quantity-field').val()) || 0;

            const lineTotal = price * qty;
            row.find('.line-total').val(lineTotal.toFixed(2)); // ✅ update line total field

            total += lineTotal;
        });

        $('#total').val(total.toFixed(2));
    }

    function updateSerialNumbers() {
        $('#productTable tbody tr').each(function (index) {
            $(this).find('.serial-number').text(index + 1);
        });
    }
});
</script>

<script>
$(document).ready(function () {

let poUsageMap = {}; 
const selectedInit = $('#seller_id option:selected');
if (selectedInit.val() && selectedInit.val() !== 'create_new') {
    $('#hidden_seller_name').val(selectedInit.data('name'));
    $('#hidden_seller_phone').val(selectedInit.data('phone'));
    $('#hidden_seller_state').val(selectedInit.data('state'));
    $('#hidden_seller_id').val(selectedInit.val());
}
    //let rowIndex = 0; // ✅ This line is missing or misplaced
    let rowIndex = $('#productTable tbody tr').length;
    calculateTotals();

    const selected = $('#seller_id').find(':selected');
if (selected.val() && selected.val() !== 'create_new') {
    $('#seller_gstin').val(selected.data('gstin')).prop('readonly', true);
    $('#seller_address').val(selected.data('address')).prop('readonly', true);
    $('#seller_phone').val(selected.data('phone')).prop('readonly', true);

    // ✅ Show and pre-select the seller's state
    const selectedState = selected.data('state')?.trim();
    $('#stateWrapper').show();

    $('#seller_state option').each(function () {
        const optionText = $(this).text().trim();
        if (optionText === selectedState) {
            $(this).prop('selected', true);
        }
    });
}

    $('#seller_id').on('change', function () {
    const sel = $(this).find(':selected');

    if (sel.val() === 'create_new') {
        $('#seller_gstin, #seller_address, #seller_phone').val('').prop('readonly', false);
        $('#seller_state').val('').prop('disabled', false);
        $('#stateWrapper').show();

        // Clear hidden inputs
        $('#hidden_seller_name').val('');
        $('#hidden_seller_phone').val('');
        $('#hidden_seller_state').val('');
        $('#hidden_seller_id').val('');
    } else {
        $('#seller_gstin').val(sel.data('gstin')).prop('readonly', true);
        $('#seller_address').val(sel.data('address')).prop('readonly', true);
        $('#seller_phone').val(sel.data('phone')).prop('readonly', true);

        const selectedState = sel.data('state')?.trim();
        $('#seller_state option').each(function () {
            if ($(this).text().trim() === selectedState) {
                $(this).prop('selected', true);
            }
        });

        $('#stateWrapper').show();
        $('#seller_state').prop('disabled', true);

        // ✅ Set hidden fields properly
        $('#hidden_seller_name').val(sel.data('name'));
        $('#hidden_seller_phone').val(sel.data('phone'));
        $('#hidden_seller_state').val(sel.data('state'));
        $('#hidden_seller_id').val(sel.val());
    }
});


    // Quantity or price change
    $('#productTable').on('input', '.quantity-field, .price-without-gst', function () {
        calculateLineTotal($(this).closest('tr'));
        calculateTotals();
    });

    // $('#productTable').on('click', '.remove-row', function () {
    //     $(this).closest('tr').remove();
    //     updateSerialNumbers();
    //     calculateTotals();
    // });

    function calculateLineTotal(row) {
        const price = parseFloat(row.find('.price-without-gst').val()) || 0;
        const qty = parseFloat(row.find('.quantity-field').val()) || 0;
        const gst = parseFloat(row.data('gst')) || 0;
        const total = price * qty * (1 + gst / 100);
        row.find('.line-total').val(total.toFixed(2));
    }

    function calculateTotals() {
        let total = 0;
        $('.line-total').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        $('#total').val(total.toFixed(2));
    }

    function updateSerialNumbers() {
        $('#productTable tbody tr').each(function (index) {
            $(this).find('.serial-number').text(index + 1);
        });
    }


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
                        `<option value="${product.id}" 
                            data-part-no="${product.part_no}" 
                            data-name="${product.name}" 
                            data-price="${product.purchase_price}" 
                            data-hsncode="${product.hsncode}" 
                            data-gst="${product.tax}">
                            ${product.name} (${product.part_no})
                        </option>`
                    );
                });
                $('#productSelect').selectpicker('refresh');
            }
        });
    }


    $('#category-group-select').on('change', function () {
        let groupId = $(this).val();
        $('#category-select').html('<option value="">-- Select Category --</option>');
        $('#brand-select').html('<option value="">-- Select Brand --</option>');
        $('#productSelect').html('<option value="">-- Select Product --</option>');

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
        }
    });

    $('#category-select').on('change', function () {
        const categoryIds = [].concat($('#category-select').val() || []);
        const brandIds = [].concat($('#brand-select').val() || []);
        $('#brand-select').html('<option value="">Select Brand</option>');
        $('#productSelect').html('<option value="">-- Select Product --</option>');

        if (categoryIds.length > 0) {
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

    $('#brand-select').on('change', function () {
        const brandIds = [].concat($('#brand-select').val() || []);
        const categoryIds = [].concat($('#category-select').val() || []);
        refreshProductDropdown(categoryIds, brandIds);
    });


    $('#productSelect').on('change', function () {
        const selected = $('#productSelect option:selected');
        const partNo = selected.data('part-no');
        const hsn = selected.data('hsncode') || '';
        $('#productHsn').val(hsn);

        const sellerId = $('#seller_id').val();
        if (!partNo || !sellerId) {
            $('#existingPOWrapper').hide();
            $('#existingPOList').html('');
            return;
        }

        $.ajax({
            url: '{{ route("fetch.product.pos") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                part_no: partNo,
                seller_id: sellerId
            },
            success: function (poList) {
                if (poList.length > 0) {
                    let options = '<option value="">-- Select PO --</option>';
                    poList.forEach(po => {
                        const usedQty = poUsageMap[po.po] || 0;
                        const remaining = po.pending - usedQty;
                        if (remaining > 0) {
                            const formattedDate = po.date ? ` - ${po.date}` : '';
                            options += `<option value="${po.po}" data-pending="${remaining}">${po.po} (${remaining})${formattedDate}</option>`;
                        }
                    });
                    if (options.includes('option value="')) {
                        $('#existingPOList').html(options);
                        $('#existingPOWrapper').show();
                    } else {
                        $('#existingPOWrapper').hide().html('');
                    }
                } else {
                    $('#existingPOWrapper').hide().html('');
                }
            },
            error: function () {
                $('#existingPOWrapper').hide().html('');
            }
        });
    });

    $('#existingPOList').on('change', function () {
        selectedPendingQty = parseFloat($('option:selected', this).data('pending')) || 0;
        $('#productQty').val(selectedPendingQty);
    });


    $('#confirmAddProduct').click(function () {
        let selected = $('#productSelect option:selected');
        let productId = selected.val();
        let partNo = selected.data('part-no');
        let name = selected.data('name');
        let price = selected.data('price');
        let qty = parseFloat($('#productQty').val());
        let hsncode = $('#productHsn').val();
        let selectedPO = $('#existingPOList option:selected').val() || '';
        let poPending = parseFloat($('#existingPOList option:selected').data('pending')) || 0;

        if (!productId || qty <= 0) {
            alert("Select product and enter valid quantity.");
            return;
        }

        if (selectedPO && qty > poPending) {
            alert('Quantity exceeds pending PO.');
            return;
        }

        if (selectedPO) {
            poUsageMap[selectedPO] = (poUsageMap[selectedPO] || 0) + qty;
        }

        let gstRate = parseFloat(selected.data('gst') || 0);
        let priceWithoutGst = parseFloat((price * 100) / (100 + gstRate)).toFixed(2);
        let lineTotal = (price * qty).toFixed(2);

        // ✅ Always use current table row count as rowIndex
        let rowIndex = $('#productTable tbody tr').length;

        let newRow = `
            <tr data-gst="${gstRate}">
                <td class="serial-number"></td>
                <td><input type="hidden" name="orders[${rowIndex}][product_id]" value="${productId}">
                    <input type="hidden" name="orders[${rowIndex}][part_no]" value="${partNo}">${partNo}</td>
                <td><input type="hidden" name="orders[${rowIndex}][product_name]" value="${name}">${name}</td>
                <td><input type="number" step="0.01" name="orders[${rowIndex}][purchase_price]" value="${price}" class="form-control purchase-price" readonly></td>
                <td><input type="hidden" name="orders[${rowIndex}][hsncode]" value="${hsncode}">
                    <input type="text" class="form-control" value="${hsncode}" readonly></td>
                <td><input type="number" step="0.01" name="orders[${rowIndex}][price_without_gst]" class="form-control price-without-gst" value="${priceWithoutGst}"></td>

                <td><input type="number" name="orders[${rowIndex}][quantity]" value="${qty}" class="form-control quantity-field" required></td>
                <td><input type="hidden" name="orders[${rowIndex}][purchase_order_no]" value="${selectedPO}">
                    <input type="text" class="form-control" value="${selectedPO}" readonly></td>
                <td><input type="text" class="form-control line-total" value="${lineTotal}" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="las la-trash"></i></button></td>
            </tr>`;

        $('#productTable tbody').append(newRow);
        rowIndex++;
        updateSerialNumbers(); // ✅ Add this line
        $('#addProductModal').modal('hide');
        $('#productQty').val('');
        $('#productSelect').val('');
        $('.selectpicker').selectpicker('refresh');
        calculateTotals();
    });



 $('#addProductModal').on('hidden.bs.modal', function () {
        // Clear the search input field
        $('#searchPartNo').val('');
        // Reset the product select dropdown
        $('#productSelect').val('').trigger('change'); // Reset dropdown and trigger change to refresh selectpicker
        // Reset the HSN code field
        $('#productHsn').val('');
        // Reset the quantity field
        $('#productQty').val('');
        // Reset the search-by dropdown to the default option (Part No.)
        $('#searchBySelect').val('part_no');
        // Reset Category Group dropdown
        $('#category-group-select').val('').selectpicker('refresh'); // Reset and refresh selectpicker
        // Reset Category dropdown
        $('#category-select').val('').selectpicker('refresh'); // Reset and refresh selectpicker
        // Reset Brand dropdown
        $('#brand-select').val('').selectpicker('refresh'); // Reset and refresh selectpicker
    });


    // ********Search Part Start***********
  let partNoSearchTimeout = null;

$('#searchPartNo').on('input', function () {
    const searchValue = $(this).val().trim();
    const searchBy = $('#searchBySelect').val();
    const sellerId = $('#seller_id').val();

    clearTimeout(partNoSearchTimeout);

    if (searchValue === '') {
        $('#productSelect').html('<option value="">-- Select Product --</option>').selectpicker('refresh');
        $('#productHsn').val('');
        $('#existingPOList').html('');
        $('#existingPOWrapper').hide();
        return;
    }

    if (searchValue.length < 3) return;

    partNoSearchTimeout = setTimeout(() => {
        $.ajax({
            url: '/admin/search-product-by-part-no',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                search_by: searchBy,
                search_value: searchValue,
                seller_id: sellerId
            },
            success: function (response) {
                console.log('Search Response:', response);
                
                if (response && response.id) {
                    // ✅ Single product returned
                    $('#productSelect').html(
                        `<option value="${response.id}" 
                            data-part-no="${response.part_no}" 
                            data-name="${response.name}" 
                            data-price="${response.purchase_price}" 
                            data-hsncode="${response.hsncode}" 
                            data-gst="${response.tax}" 
                            selected>
                            ${response.name} (${response.part_no})
                        </option>`
                    ).selectpicker('refresh');

                    $('#productHsn').val(response.hsncode || '');

                    // ✅ Filter PO list by poUsageMap
                    if (response.po_list && response.po_list.length > 0) {
                        let options = '<option value="">-- Select PO --</option>';
                        response.po_list.forEach(po => {
                            const usedQty = poUsageMap[po.po] || 0;
                            const remaining = (po.pending ?? 0) - usedQty;
                            const formattedDate = po.date ? ` - ${po.date}` : ''; // ✅ add date from response

                            if (remaining > 0) {
                                options += `<option value="${po.po}" data-pending="${remaining}">${po.po} (${remaining})${formattedDate}</option>`;
                            }
                        });

                        if (options.includes('option value="')) {
                            $('#existingPOList').html(options);
                            $('#existingPOWrapper').show();
                            $('#existingPOList').trigger('change'); // ✅ Prefill qty if needed
                        } else {
                            $('#existingPOList').html('');
                            $('#existingPOWrapper').hide();
                        }
                    } else {
                        $('#existingPOList').html('');
                        $('#existingPOWrapper').hide();
                    }

                } else if (Array.isArray(response) && response.length > 0) {
                    // ✅ Multiple products returned
                    let productOptions = '';
                    response.forEach(product => {
                        productOptions += `<option value="${product.id}" 
                            data-part-no="${product.part_no}" 
                            data-name="${product.name}" 
                            data-price="${product.purchase_price}" 
                            data-hsncode="${product.hsncode}" 
                            data-gst="${product.tax}">
                            ${product.name} (${product.part_no})
                        </option>`;
                    });

                    $('#productSelect').html(productOptions).selectpicker('refresh');
                    $('#productHsn').val('');
                    $('#existingPOList').html('');
                    $('#existingPOWrapper').hide();
                } else {
                    // ❌ No product found
                    $('#productSelect').html('<option value="">-- Select Product --</option>').selectpicker('refresh');
                    $('#productHsn').val('');
                    $('#existingPOList').html('');
                    $('#existingPOWrapper').hide();
                }
            },
            error: function (xhr, status, error) {
                console.error("Error fetching product data:", error);
                $('#existingPOWrapper').hide();
                $('#existingPOList').html('');
            }
        });
    }, 500);
});


$('#convertBtn').on('click', function () {
    const invoiceNo = $('#seller_invoice_no').val();
    const invoiceDate = $('#seller_invoice_date').val();

    if (!invoiceNo || !invoiceDate) {
        alert('Please enter Seller Invoice Number and Date.');
        return;
    }

    $('#actionField').val('convert');
    $(this).closest('form').submit();
});


$('#productTable').on('click', '.remove-row', function () {
    const row = $(this).closest('tr');
    const partNo = row.find('input[name$="[part_no]"]').val();
    

    if (!partNo) {
        alert("Part number not found.");
        return;
    }

    // Send part_no to backend to trigger inventoryProductEntry()
    $.ajax({
        url: '{{ route("admin.inventory.remove.product") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            part_no: partNo
        },
        success: function (response) {
            if (response.success) {
                // Remove row from table after inventory updated
                row.remove();
                updateSerialNumbers();
                calculateTotals();
            } else {
                alert("Inventory sync failed: " + response.message);
            }
        },
        error: function () {
            alert("Error in deleting and syncing product.");
        }
    });
});



});
</script>

@endsection
