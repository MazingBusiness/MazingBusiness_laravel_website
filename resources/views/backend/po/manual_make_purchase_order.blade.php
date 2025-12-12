@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3"> Make Manual Purchase Order</h1>
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
        <form method="POST" action="{{ route('admin.saveManualPurchaseOrder') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="party_type" id="party_type_input" value="seller"> 
            <input type="hidden" name="address_id" id="address_id"> 
            {{-- default seller --}}
       <!-- Combined Row for Party Type and Credit Note Type -->
<div class="form-group row">
    <!-- Party Type Dropdown -->
    <div class="col-md-3">
        <label class="col-form-label">Party Type</label>
        <select id="partyType" class="form-control">
            <option value="seller">🛒 Seller</option>
            <option value="customer">👤 Customer</option>
        </select>
    </div>

    <!-- Credit Note Type Dropdown (Initially Hidden) -->
    <div class="col-md-3" id="creditNoteTypeWrapper" style="display: none;">
        <label class="col-form-label">Credit Note Type</label>
        <select id="creditNoteType" class="form-control">
            <option value="" disabled selected>Select Type</option>
            <option value="service">Service</option>
            <option value="goods">Goods</option>
        </select>
    </div>

    <!-- Upload Attachment (show except customer+service) -->
    <div class="form-group col-md-6" id="attachmentWrapper" style="display: none;">
        <label for="attachment" class="font-weight-bold">
            Upload Attachment <small class="text-muted">(PDF / Image)</small>
        </label>

        <div class="custom-file">
            <input
                type="file"
                class="custom-file-input"
                id="attachment"
                name="attachment"
                accept=".pdf,.png,.jpg,.jpeg"
                required
            >
            <label class="custom-file-label" for="attachment">Choose file</label>
        </div>

        <small class="form-text text-muted mt-1">
            Accepted formats: .pdf, .png, .jpg, .jpeg
        </small>
    </div>
</div>



<input type="hidden" name="credit_note_type" id="credit_note_type_input" value="">
            
            <!-- Warehouse & Seller & Party Dropdowns -->
<div class="form-group row">
        <!-- Warehouse -->
        <label class="col-md-2 col-form-label">Select Warehouse <span class="text-danger">*</span></label>
        <div class="col-md-4">
            <select name="warehouse_id" id="warehouse" class="form-control" required>
                <option value="" disabled selected>Select a Warehouse</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>

            <!-- Seller Dropdown -->
                <div id="sellerSection" class="col-md-6 row">
                    <label class="col-md-4 col-form-label">Select Seller</label>
                    <div class="col-md-8">
                       
                        <select id="seller" class="form-control selectpicker" data-live-search="true">
                            <option value="create_new" >➕ Create Seller</option>
                            @foreach($all_sellers as $seller)
                                <option value="{{ $seller->seller_id }}"
                                        data-name="{{ $seller->seller_name }}"
                                        data-address="{{ $seller->seller_address }}"
                                        data-gstin="{{ $seller->gstin }}"
                                        data-phone="{{ $seller->seller_phone }}"
                                        data-state="{{ $seller->state_name }}">
                                    {{ $seller->seller_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>


                                <!-- Customer Dropdown (Hidden by Default) -->
                <div id="customerSection" class="col-md-6 row" style="display: none;">
                    <label class="col-md-4 col-form-label">Select Customer</label>
                    <div class="col-md-8">
                        
                        <select id="customer" class="form-control selectpicker" data-live-search="true">
                            <option value="">Select Customer</option>
                            @foreach($all_customers as $cust)
                                <option value="{{ $cust->id }}"
                                        data-name="{{ $cust->company_name }}"
                                        data-address="{{ $cust->address }}"
                                        data-phone="{{ $cust->phone }}"
                                        data-gstin="{{ $cust->gstin }}"
                                        data-state="{{ $cust->state_name }}">
                                    {{ $cust->company_name }} ({{ $cust->acc_code ?? 'No Code' }} - {{ $cust->city ?? 'No City' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seller Info -->
            <div class="form-group row">
                <input type="hidden" name="seller_info[seller_id]" id="seller_id">
                <!-- <input type="hidden" name="seller_info[state_name]" id="state_name"> -->
                <div class="col-md-6">
                    <label> Name</label>
                    <input type="text" name="seller_info[seller_name]" id="seller_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label> Address</label>
                    <input type="text" name="seller_info[seller_address]" id="seller_address" class="form-control">
                </div>
            </div>
            <div class="form-group row">
                <div class="col-md-6">
                    <label> GSTIN</label>
                    <input type="text" name="seller_info[seller_gstin]" id="seller_gstin" class="form-control">
                </div>
                <div class="col-md-6">
                    <label> Phone</label>
                    <input type="text" name="seller_info[seller_phone]" id="seller_phone" class="form-control">
                </div>
            </div>

         <!-- State Dropdown for new seller -->
<div class="form-group col-md-6" id="state_dropdown_wrapper" style="display: none;">
    <label for="state_id">Select State</label>
    <select name="seller_info[state_name]" id="state_id" class="form-control">
        <option value="">-- Select State --</option>
        @foreach($states as $state)
            <option value="{{ $state->name }}">{{ $state->name }}</option>
        @endforeach
    </select>
</div>


<!-- Hidden Field to Detect Convert Action -->
<input type="hidden" name="action" id="actionField" value="">

<!-- Seller Invoice Inputs (Initially Hidden) -->
<div id="convertFields" style="display: none;" class="border rounded p-3 mt-3 mb-3 bg-light">
    <div class="row">
        <div class="col-md-6">
            <label for="seller_invoice_no">Seller Invoice Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="seller_invoice_no" id="seller_invoice_no" placeholder="Enter Invoice No">
        </div>
        <div class="col-md-6">
            <label for="seller_invoice_date">Seller Invoice Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="seller_invoice_date" id="seller_invoice_date" value="{{ date('Y-m-d') }}">
        </div>
    </div>
</div>


    <!-- Service Fields Table (Initially Hidden) -->
    <div id="serviceFieldsWrapper" style="display: none; margin-top: 15px;">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Note</th>
                        <th>SAC Code</th>
                        <th>Rate</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="note" placeholder="Enter Note">
                        </td>
                        <td>
                            <input type="text" value="996511" class="form-control" name="sac_code" placeholder="Enter SAC Code">
                        </td>
                        <td>
                            <input type="text" class="form-control" name="rate" placeholder="Enter Rate">
                        </td>
                        <td>
                            <input type="number" value="1" class="form-control" name="quantity" placeholder="Enter Quantity" min="1">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


            <!-- Product Table -->
            <div class="table-responsive"  id="productTableWrapper">
                <table class="table table-bordered" id="productTable">
                    <thead class="thead-light">
                        <tr>
                            <th>S.No</th> <!-- 🔁 Add this line -->
                            <th>Part No.</th>
                            <th>Product Name</th>
                            <th>Purchase Price</th>
                            <th>HSN Code</th>
                            <th>Price Without GST</th>
                            <th>Quantity</th>
                            <th>po</th>
                            <th>Sub Total</th> <!-- ✅ NEW COLUMN -->
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- JS Rows Append Here -->
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

            <div class="text-right mt-3" id="actionButtonsWrapper">
                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#addProductModal" id="addProductBtn">+ Add Product</button>
                <button type="submit" class="btn btn-success" id="saveOrderBtn">Save Purchase Order</button>
                <!-- <button type="submit" name="action" value="convert" class="btn btn-warning">Convert to Purchase</button> -->
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
let rowIndex = 0;
let poUsageMap = {}; 
// Seller auto-fill

$('#attachment').on('change', function () {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });

$('#seller').on('change', function () {
    const selected = $(this).find(':selected');
    const selectedValue = selected.val();

    if (selectedValue === 'create_new') {
        $('#seller_name').val('');
        $('#seller_address').val('');
        $('#seller_gstin').val('');
        $('#seller_phone').val('');
        $('#seller_id').val('');
        $('#state_dropdown_wrapper').show(); // ✅ This works now
        $('#state_id').val('');
    } else {
        $('#seller_name').val(selected.data('name'));
        $('#seller_address').val(selected.data('address'));
        $('#seller_gstin').val(selected.data('gstin'));
        $('#seller_phone').val(selected.data('phone'));
        $('#seller_id').val(selectedValue);

        const stateName = selected.data('state');
        $('#state_id').val(stateName); // ✅ prefill for existing seller

        $('#state_dropdown_wrapper').hide(); // ✅ hide for existing seller
    }
});




        // ✅ Trigger change on page load so correct fields are visible
        $('#seller').trigger('change');

// Add Product
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

    // ✅ Prevent over-entry
    if (selectedPO && qty > poPending) {
        alert('Entered quantity exceeds remaining pending quantity of selected PO!');
        return;
    }

    // ✅ Update poUsageMap
    if (selectedPO) {
        // poUsageMap[selectedPO] = (poUsageMap[selectedPO] || 0) + qty;
         const usageKey = `${selectedPO}_${partNo}`;
        poUsageMap[usageKey] = (poUsageMap[usageKey] || 0) + qty;
    }

    let gstRate = parseFloat(selected.data('gst') || 0);
    let priceWithoutGst = parseFloat((price * 100) / (100 + gstRate)).toFixed(2);
    let lineTotal = (price * qty).toFixed(2);

    let newRow = `
        <tr data-gst="${gstRate}">
            <td class="serial-number"></td>
            <td>
                <input type="hidden" name="orders[${rowIndex}][product_id]" value="${productId}">
                <input type="hidden" name="orders[${rowIndex}][part_no]" value="${partNo}">
                ${partNo}
            </td>
            <td>
                <input type="hidden" name="orders[${rowIndex}][product_name]" value="${name}">${name}
            </td>
            <td>
                <input type="number" step="0.01" name="orders[${rowIndex}][purchase_price]" value="${price}" class="form-control purchase-price" readonly>
            </td>
            <td>
                <input type="hidden" name="orders[${rowIndex}][hsncode]" value="${hsncode}">
                <input type="text" class="form-control" value="${hsncode}" readonly>
            </td>
            <td>
                <input type="number" step="0.01" class="form-control price-without-gst" value="${priceWithoutGst}">
            </td>
            <td>
                <input type="number" name="orders[${rowIndex}][quantity]" value="${qty}" class="form-control quantity-field" required>
            </td>
            <td>
                <input type="hidden" name="orders[${rowIndex}][purchase_order_no]" value="${selectedPO}">
                <input type="text" class="form-control" value="${selectedPO}" readonly>
            </td>
            <td>
                <input type="text" class="form-control line-total" value="${lineTotal}" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-row"><i class="las la-trash"></i></button>
            </td>
        </tr>
    `;

    $('#productTable tbody').append(newRow);
    rowIndex++;
    $('#addProductModal').modal('hide');
    $('#productQty').val('');
    $('#productSelect').val('');
    $('.selectpicker').selectpicker('refresh');
    calculateTotals();
});



    function calculateTotals() {
        let subtotal = 0;
        let total = 0;

        $('#productTable tbody tr').each(function () {
            const row = $(this);
            const price = parseFloat(row.find('.purchase-price').val()) || 0;
            const qty = parseFloat(row.find('.quantity-field').val()) || 0;

            const lineTotal = price * qty;
            row.find('.line-total').val(lineTotal.toFixed(2)); // ✅ update line total field

            subtotal += lineTotal;
            total += lineTotal;
        });

        $('#subtotal').val(subtotal.toFixed(2));
        $('#total').val(total.toFixed(2));

        // Update serial numbers
        $('#productTable tbody tr').each(function (index) {
            $(this).find('.serial-number').text(index + 1);
        });
    }

    $(document).on('input', '.price-without-gst', function () {
       
        const $row = $(this).closest('tr');
        const gstRate = parseFloat($row.data('gst')) || 0;
        const priceWithoutGst = parseFloat($(this).val()) || 0;

        // Calculate price with GST
        const priceWithGst = (priceWithoutGst * (100 + gstRate)) / 100;
        $row.find('.purchase-price').val(priceWithGst.toFixed(2));
         calculateTotals();
    });

    // Remove product
    $(document).on('click', '.remove-row', function () {
        const $row = $(this).closest('tr');
        const qty = parseFloat($row.find('.quantity-field').val()) || 0;
        const poNo = $row.find('input[name*="[purchase_order_no]"]').val();

        if (poNo && poUsageMap[poNo]) {
            poUsageMap[poNo] -= qty;

            // Optional: prevent negative
            if (poUsageMap[poNo] < 0) {
                poUsageMap[poNo] = 0;
            }
        }

        $row.remove();
        calculateTotals();
    });
    // When quantity changes
    $(document).on('input', '.quantity-field', function () {
        calculateTotals();
    });

// Get products based on category & brand
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
                        'data-name="' + product.name + '" ' +
                        'data-price="' + product.purchase_price + '" ' +
                        'data-hsncode="' + product.hsncode + '" ' +
                        'data-gst="' + product.tax + '">' + // ✅ Add this line here
                        product.name + ' (' + product.part_no + ')' +
                    '</option>'
                );
            });
            $('#productSelect').selectpicker('refresh');
        }
    });
}

// On Category Group Change
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

// On Category Change
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

// On Brand Change
$('#brand-select').on('change', function () {
    const brandIds = [].concat($('#brand-select').val() || []);
    const categoryIds = [].concat($('#category-select').val() || []);
    refreshProductDropdown(categoryIds, brandIds);
});

let selectedPendingQty = 0;
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
                console.log("🔥 PO List Response:", poList); // ✅ Yeh console me PO list print karega
                if (poList.length > 0) {
                    let options = '<option value="">-- Select PO --</option>';
                    poList.forEach(po => {
                    // const usedQty = poUsageMap[po.po] || 0;
                    // const remaining = po.pending - usedQty;
                        const usageKey = `${po.po}_${partNo}`;
                        const usedQty = poUsageMap[usageKey] || 0;
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
                        $('#existingPOWrapper').hide();
                        $('#existingPOList').html('');
                    }
                } else {
                    $('#existingPOWrapper').hide();
                    $('#existingPOList').html('');
                }
            },
        error: function () {
            $('#existingPOWrapper').hide();
            $('#existingPOList').html('');
        }
    });
});

// 🔁 Set quantity field when PO is selected
$('#existingPOList').on('change', function () {
    selectedPendingQty = parseFloat($('option:selected', this).data('pending')) || 0;
    $('#productQty').val(selectedPendingQty);
});


$('#convertBtn').on('click', function () {
    const convertFieldsVisible = $('#convertFields').is(':visible');

    if (!convertFieldsVisible) {
        $('#convertFields').slideDown();
        return;
    }

    // Validate fields
    const invoiceNo = $('#seller_invoice_no').val();
    const invoiceDate = $('#seller_invoice_date').val();

    if (!invoiceNo || !invoiceDate) {
        alert('Please enter Seller Invoice Number and Date.');
        return;
    }

    // ✅ Check if attachment is required (when visible)
    if ($('#attachmentWrapper').is(':visible') && !$('#attachment').val()) {
        alert('Please upload the attachment (PDF / Image).');
        return;
    }

    $('#actionField').val('convert');
    $(this).closest('form').submit();
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


 // Initially set the input for "Search by Part No."
   // $('#searchPartNo').show();  // Make sure the part number input is visible initially

    // Listen for changes in the "Search By" dropdown
    $('#searchBySelect').on('change', function () {
        const searchBy = $(this).val(); // Get the selected value (either 'part_no' or 'name')

        if (searchBy === 'name') {
            // If "Product Name" is selected, update the label and show the correct input field
            $('#searchLabel').text('Search by Product Name');  // Change label to "Search by Product Name"
            $('#searchPartNo').attr('placeholder', 'Type Product Name'); // Update placeholder
            
        } else {
            // If "Part No." is selected, revert the label and show the part number input field
            $('#searchLabel').text('Search by Part No.');  // Change label back to "Search by Part No."
            $('#searchPartNo').attr('placeholder', 'Type Part No.'); // Revert placeholder
        }
    });


// ********Search Part End***********
// Toggle between Seller and Customer section
// Toggle between Seller and Customer section
$('#partyType').on('change', function () {
    const selectedType = $(this).val();
    $('#party_type_input').val(selectedType); // 🔁 Update hidden input

    // 🔁 Clear fields
    $('#seller_name').val('');
    $('#seller_address').val('');
    $('#seller_gstin').val('');
    $('#seller_phone').val('');
    $('#seller_id').val('');
    $('#address_id').val('');
    $('#state_id').val('');

    // Reset UI
    $('#creditNoteType').val('');
    $('#serviceFieldsWrapper').hide();
    $('#productTableWrapper').show();
    $('#creditNoteTypeWrapper').hide();
    $('#addProductBtn').show();
    $('#saveOrderBtn').show();
    $('#convertBtn').show();

    if (selectedType === 'seller') {
        $('#sellerSection').show();
        $('#customerSection').hide();
        $('#state_dropdown_wrapper').hide();

        const selectedSeller = $('#seller').val();
        if (selectedSeller === 'create_new') {
            $('#state_dropdown_wrapper').show();
        }

    } else if (selectedType === 'customer') {
        $('#sellerSection').hide();
        $('#customerSection').show();
        $('#state_dropdown_wrapper').show();
        $('#creditNoteTypeWrapper').show();

        const selectedCustomer = $('#customer').val();
        if (selectedCustomer) {
            const selected = $('#customer').find(':selected');
            $('#state_id').val(selected.data('state'));
        }
    }

    // ✅ Attachment Logic
    $('#attachmentWrapper').hide(); // Hide first

    if (selectedType === 'seller') {
        $('#attachmentWrapper').show();
    }

    if (selectedType === 'customer' && $('#creditNoteType').val() === 'goods') {
        $('#attachmentWrapper').show();
    }
});

// Credit Note Type Change Event
   $('#creditNoteType').on('change', function () {
    const selectedType = $(this).val();
    $('#credit_note_type_input').val(selectedType);

    if (selectedType === 'service') {
        $('#serviceFieldsWrapper').show();
        $('#productTableWrapper').hide();  
        $('#addProductBtn').hide();
        $('#saveOrderBtn').hide();
        $('#convertBtn').show();
    } else {
        $('#serviceFieldsWrapper').hide();
        $('#productTableWrapper').show();
        $('#addProductBtn').show();
        $('#saveOrderBtn').show();
        $('#convertBtn').show();
    }

    // ✅ Attachment field logic
    const partyType = $('#partyType').val();
    $('#attachmentWrapper').hide(); // Always hide first

    if (partyType === 'seller') {
        $('#attachmentWrapper').show(); // Always show for seller
    } else if (partyType === 'customer' && selectedType === 'goods') {
        $('#attachmentWrapper').show(); // Show for customer + goods
    }
});


// Prefill fields on customer select
$('#customer').on('change', function () {
    const selected = $(this).find(':selected');
    $('#seller_name').val(selected.data('name'));
    $('#seller_address').val(selected.data('address'));
    $('#seller_gstin').val('');
    $('#seller_phone').val(selected.data('phone'));
     $('#seller_gstin').val(selected.data('gstin')); // ✅ Fill GSTIN
    $('#seller_id').val(selected.val());
    $('#state_id').val(selected.data('state'));
    $('#address_id').val(selected.val()); // ✅ Set address_id here
});

// Trigger on load
$('#partyType').trigger('change');
</script>
@endsection
