@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">Make Manual Purchase Order — Manager 41</h1>
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
      <input type="hidden" name="action" id="actionField" value="">

      {{-- Party / Credit Note / Attachment --}}
      <div class="form-group row">
        <div class="col-md-3">
          <label class="col-form-label">Party Type</label>
          <select id="partyType" class="form-control">
            <option value="seller">🛒 Seller</option>
            <option value="customer">👤 Customer</option>
          </select>
        </div>

        <div class="col-md-3" id="creditNoteTypeWrapper" style="display:none;">
          <label class="col-form-label">Credit Note Type</label>
          <select id="creditNoteType" class="form-control">
            <option value="" disabled selected>Select Type</option>
            <option value="service">Service</option>
            <option value="goods">Goods</option>
          </select>
        </div>

        <div class="form-group col-md-6" id="attachmentWrapper">
          <label for="attachment" class="font-weight-bold">Upload Attachment
            <small class="text-muted">(PDF / Image)</small>
          </label>
          <div class="custom-file">
            <input type="file" class="custom-file-input" id="attachment" name="attachment" accept=".pdf,.png,.jpg,.jpeg" required>
            <label class="custom-file-label" for="attachment">Choose file</label>
          </div>
          <small class="form-text text-muted mt-1">Accepted: .pdf, .png, .jpg, .jpeg</small>
        </div>
      </div>

      <input type="hidden" name="credit_note_type" id="credit_note_type_input" value="">

      {{-- Warehouse + Seller/Customer --}}
      <div class="form-group row">
        <label class="col-md-2 col-form-label">Select Warehouse <span class="text-danger">*</span></label>
        <div class="col-md-4">
          <select name="warehouse_id" id="warehouse" class="form-control" required>
            <option value="" disabled selected>Select a Warehouse</option>
            @foreach($warehouses as $warehouse)
              <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Seller --}}
        <div id="sellerSection" class="col-md-6 row">
          <label class="col-md-4 col-form-label">Select Seller</label>
          <div class="col-md-8">
            <select id="seller" class="form-control selectpicker" data-live-search="true">
              <option value="create_new">➕ Create Seller</option>
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

        {{-- Customer --}}
        <div id="customerSection" class="col-md-6 row" style="display:none;">
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

      {{-- Seller Info --}}
      <div class="form-group row">
        <input type="hidden" name="seller_info[seller_id]" id="seller_id">
        <div class="col-md-6">
          <label>Name</label>
          <input type="text" name="seller_info[seller_name]" id="seller_name" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Address</label>
          <input type="text" name="seller_info[seller_address]" id="seller_address" class="form-control">
        </div>
      </div>
      <div class="form-group row">
        <div class="col-md-6">
          <label>GSTIN</label>
          <input type="text" name="seller_info[seller_gstin]" id="seller_gstin" class="form-control">
        </div>
        <div class="col-md-6">
          <label>Phone</label>
          <input type="text" name="seller_info[seller_phone]" id="seller_phone" class="form-control">
        </div>
      </div>

      {{-- State for new seller --}}
      <div class="form-group col-md-6" id="state_dropdown_wrapper" style="display:none;">
        <label for="state_id">Select State</label>
        <select name="seller_info[state_name]" id="state_id" class="form-control">
          <option value="">-- Select State --</option>
          @foreach($states as $state)
            <option value="{{ $state->name }}">{{ $state->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Convert to Purchase fields --}}
      <div id="convertFields" style="display:none;" class="border rounded p-3 mt-3 mb-3 bg-light">
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

      {{-- SERVICE (credit note type) kept same UI as original --}}
      <div id="serviceFieldsWrapper" style="display:none; margin-top:15px;">
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
                <td><input type="text" class="form-control" name="note" placeholder="Enter Note"></td>
                <td><input type="text" class="form-control" name="sac_code" value="996511"></td>
                <td><input type="text" class="form-control" name="rate"></td>
                <td><input type="number" class="form-control" name="quantity" value="1" min="1"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Products table (NO Price Without GST column, purchase price editable) --}}
      <div class="table-responsive" id="productTableWrapper">
        <table class="table table-bordered" id="productTable">
          <thead class="thead-light">
            <tr>
              <th>S.No</th>
              <th>Part No.</th>
              <th>Product Name</th>
              <th>Purchase Price (With GST)</th>
              <th>HSN Code</th>
              <th>Quantity</th>
              <th>PO</th>
              <th>Sub Total</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
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
        <button type="button" class="btn btn-warning" id="convertBtn">Convert to Purchase</button>
      </div>
    </form>
  </div>
</div>

{{-- Add Product Modal (unchanged, but no "price without GST" logic) --}}
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
        </div>

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

        <div class="form-group" id="existingPOWrapper" style="display:none;">
          <label>Existing POs with this Product</label>
          <select id="existingPOList" class="form-control" readonly></select>
        </div>

        <div class="form-group">
          <label>HSN Code</label>
          <input type="text" id="productHsn" class="form-control">
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
let selectedPendingQty = 0;

// attachment label
$('#attachment').on('change', function(){ $(this).next('.custom-file-label').html($(this).val().split('\\').pop()); });

// seller picker
$('#seller').on('change', function () {
  const sel = $(this).find(':selected');
  const val = sel.val();
  if (val === 'create_new') {
    $('#seller_name,#seller_address,#seller_gstin,#seller_phone,#seller_id').val('');
    $('#state_dropdown_wrapper').show(); $('#state_id').val('');
  } else {
    $('#seller_name').val(sel.data('name'));
    $('#seller_address').val(sel.data('address'));
    $('#seller_gstin').val(sel.data('gstin'));
    $('#seller_phone').val(sel.data('phone'));
    $('#seller_id').val(val);
    $('#state_id').val(sel.data('state'));
    $('#state_dropdown_wrapper').hide();
  }
}).trigger('change');

// Add product (Purchase Price editable; value is WITH GST)
$('#confirmAddProduct').click(function () {
  const sel = $('#productSelect option:selected');
  const productId = sel.val();
  const partNo = sel.data('part-no');
  const name = sel.data('name');
  const priceWithGst = parseFloat(sel.data('price') || 0).toFixed(2);
  const qty = parseFloat($('#productQty').val());
  const hsncode = $('#productHsn').val();
  const selectedPO = $('#existingPOList option:selected').val() || '';
  const poPending = parseFloat($('#existingPOList option:selected').data('pending')) || 0;
  const gstRate = parseFloat(sel.data('gst') || 0);

  if (!productId || !qty || qty <= 0) { alert('Select product and enter valid quantity.'); return; }
  if (selectedPO && qty > poPending) { alert('Entered quantity exceeds remaining pending quantity of selected PO!'); return; }

  if (selectedPO) {
    const usageKey = `${selectedPO}_${partNo}`;
    poUsageMap[usageKey] = (poUsageMap[usageKey] || 0) + qty;
  }

  const lineTotal = (parseFloat(priceWithGst) * qty).toFixed(2);

  const row = `
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
        <input type="number" step="0.01" name="orders[${rowIndex}][purchase_price]" value="${priceWithGst}" class="form-control purchase-price">
        <small class="text-muted">With GST</small>
      </td>
      <td>
        <input type="hidden" name="orders[${rowIndex}][hsncode]" value="${hsncode}">
        <input type="text" class="form-control" value="${hsncode}" readonly>
      </td>
      <td>
        <input type="number" name="orders[${rowIndex}][quantity]" value="${qty}" class="form-control quantity-field" min="1" required>
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
    </tr>`;
  $('#productTable tbody').append(row);
  rowIndex++; $('#addProductModal').modal('hide');
  $('#productQty').val(''); $('#productSelect').val('');
  $('.selectpicker').selectpicker('refresh');
  calculateTotals();
});

// totals
function calculateTotals(){
  let total = 0;
  $('#productTable tbody tr').each(function(index){
    const row = $(this);
    const price = parseFloat(row.find('.purchase-price').val()) || 0; // WITH GST
    const qty   = parseFloat(row.find('.quantity-field').val()) || 0;
    const lt = price * qty;
    row.find('.line-total').val(lt.toFixed(2));
    row.find('.serial-number').text(index+1);
    total += lt;
  });
  $('#total').val(total.toFixed(2));
}

// on edit
$(document).on('input', '.purchase-price, .quantity-field', calculateTotals);

// remove
$(document).on('click', '.remove-row', function(){
  const $row = $(this).closest('tr');
  const qty = parseFloat($row.find('.quantity-field').val()) || 0;
  const poNo = $row.find('input[name*="[purchase_order_no]"]').val();
  const partNo = $row.find('input[name*="[part_no]"]').val();
  if (poNo) {
    const key = `${poNo}_${partNo}`;
    poUsageMap[key] = Math.max((poUsageMap[key] || 0) - qty, 0);
  }
  $row.remove(); calculateTotals();
});

// fetching products / filters (same as your original)
function refreshProductDropdown(categoryIds = [], brandIds = []) {
  $('#productSelect').html('<option value="">-- Select Product --</option>');
  $.ajax({
    url: '{{ route("find-products-by-category-and-brand") }}',
    method: 'POST',
    data: {_token:'{{ csrf_token() }}', category_ids: categoryIds, brand_ids: brandIds},
    success: function (resp) {
      $.each(resp, function(_, p){
        $('#productSelect').append(
          `<option value="${p.id}"
             data-part-no="${p.part_no}"
             data-name="${p.name}"
             data-price="${p.purchase_price}"
             data-hsncode="${p.hsncode}"
             data-gst="${p.tax}">
             ${p.name} (${p.part_no})
           </option>`
        );
      });
      $('#productSelect').selectpicker('refresh');
    }
  });
}

$('#category-group-select').on('change', function(){
  const gid = $(this).val();
  $('#category-select').html('<option value="">-- Select Category --</option>');
  $('#brand-select').html('<option value="">-- Select Brand --</option>');
  $('#productSelect').html('<option value="">-- Select Product --</option>');
  if (gid) {
    $.get('/find-categories-by-group/'+gid, function(resp){
      $.each(resp, function(_, c){ $('#category-select').append(`<option value="${c.id}">${c.name}</option>`); });
      $('#category-select').selectpicker('refresh');
    });
  }
});

$('#category-select').on('change', function(){
  const cids = [].concat($('#category-select').val() || []);
  const bids = [].concat($('#brand-select').val() || []);
  $('#brand-select').html('<option value="">-- Select Brand --</option>');
  $('#productSelect').html('<option value="">-- Select Product --</option>');
  if (cids.length) {
    $.get('{{ url("/find-brands-by-category") }}/'+cids.join(','), function(resp){
      $.each(resp, function(_, b){ $('#brand-select').append(`<option value="${b.id}">${b.name}</option>`); });
      $('#brand-select').selectpicker('refresh');
    });
  }
  refreshProductDropdown(cids, bids);
});

$('#brand-select').on('change', function(){
  const bids = [].concat($('#brand-select').val() || []);
  const cids = [].concat($('#category-select').val() || []);
  refreshProductDropdown(cids, bids);
});

// product change → POs for that seller
$('#productSelect').on('change', function () {
  const sel = $('#productSelect option:selected');
  $('#productHsn').val(sel.data('hsncode') || '');
  const partNo = sel.data('part-no');
  const sellerId = $('#seller_id').val();

  if (!partNo || !sellerId) { $('#existingPOWrapper').hide(); $('#existingPOList').html(''); return; }

  $.post('{{ route("fetch.product.pos") }}',
    {_token: '{{ csrf_token() }}', part_no: partNo, seller_id: sellerId},
    function (poList) {
      if (poList.length > 0) {
        let options = '<option value="">-- Select PO --</option>';
        poList.forEach(po => {
          const key = `${po.po}_${partNo}`;
          const usedQty = poUsageMap[key] || 0;
          const remaining = (po.pending || 0) - usedQty;
          const d = po.date ? ` - ${po.date}` : '';
          if (remaining > 0) options += `<option value="${po.po}" data-pending="${remaining}">${po.po} (${remaining})${d}</option>`;
        });
        if (options.includes('option value="')) {
          $('#existingPOList').html(options); $('#existingPOWrapper').show();
        } else { $('#existingPOWrapper').hide().html(''); }
      } else { $('#existingPOWrapper').hide().html(''); }
    }
  );
});

// prefill qty from PO
$('#existingPOList').on('change', function(){
  selectedPendingQty = parseFloat($('option:selected', this).data('pending')) || 0;
  $('#productQty').val(selectedPendingQty);
});

// search (same behavior)
let partNoSearchTimeout=null;
$('#searchBySelect').on('change', function(){
  const v=$(this).val();
  $('#searchLabel').text(v==='name'?'Search by Product Name':'Search by Part No.');
  $('#searchPartNo').attr('placeholder', v==='name'?'Type Product Name':'Type Part No.');
});
$('#searchPartNo').on('input', function(){
  const val=$(this).val().trim(); const by=$('#searchBySelect').val(); const sellerId=$('#seller_id').val();
  clearTimeout(partNoSearchTimeout);
  if (val===''||val.length<3) { $('#productSelect').html('<option value="">-- Select Product --</option>').selectpicker('refresh'); $('#productHsn').val(''); $('#existingPOList').html(''); $('#existingPOWrapper').hide(); return; }
  partNoSearchTimeout=setTimeout(()=>{
    $.post('/admin/search-product-by-part-no', {_token:'{{ csrf_token() }}', search_by: by, search_value: val, seller_id: sellerId}, function(resp){
      if (resp && resp.id) {
        $('#productSelect').html(
          `<option value="${resp.id}" data-part-no="${resp.part_no}" data-name="${resp.name}" data-price="${resp.purchase_price}" data-hsncode="${resp.hsncode}" data-gst="${resp.tax}" selected>
            ${resp.name} (${resp.part_no})
           </option>`
        ).selectpicker('refresh');
        $('#productHsn').val(resp.hsncode || '');
        if (resp.po_list && resp.po_list.length) {
          let options = '<option value="">-- Select PO --</option>';
          resp.po_list.forEach(po=>{
            const used = poUsageMap[po.po] || 0;
            const rem  = (po.pending||0) - used;
            const d    = po.date ? ` - ${po.date}` : '';
            if (rem>0) options+=`<option value="${po.po}" data-pending="${rem}">${po.po} (${rem})${d}</option>`;
          });
          if (options.includes('option value="')) { $('#existingPOList').html(options); $('#existingPOWrapper').show(); $('#existingPOList').trigger('change'); }
          else { $('#existingPOList').html(''); $('#existingPOWrapper').hide(); }
        } else { $('#existingPOList').html(''); $('#existingPOWrapper').hide(); }
      } else if (Array.isArray(resp) && resp.length) {
        let opts=''; resp.forEach(p=>{
          opts+=`<option value="${p.id}" data-part-no="${p.part_no}" data-name="${p.name}" data-price="${p.purchase_price}" data-hsncode="${p.hsncode}" data-gst="${p.tax}">${p.name} (${p.part_no})</option>`;
        }); $('#productSelect').html(opts).selectpicker('refresh'); $('#productHsn').val(''); $('#existingPOList').html(''); $('#existingPOWrapper').hide();
      } else { $('#productSelect').html('<option value="">-- Select Product --</option>').selectpicker('refresh'); $('#productHsn').val(''); $('#existingPOList').html(''); $('#existingPOWrapper').hide(); }
    });
  },500);
});

// Party toggles (same as your original)
$('#partyType').on('change', function () {
  const t = $(this).val();
  $('#party_type_input').val(t);
  $('#seller_name,#seller_address,#seller_gstin,#seller_phone,#seller_id,#address_id,#state_id').val('');
  $('#creditNoteType').val('');
  $('#serviceFieldsWrapper').hide(); $('#productTableWrapper').show();
  $('#creditNoteTypeWrapper').toggle(t==='customer');
  if (t==='seller') { $('#sellerSection').show(); $('#customerSection').hide(); $('#state_dropdown_wrapper').hide(); if ($('#seller').val()==='create_new'){ $('#state_dropdown_wrapper').show(); } }
  else { $('#sellerSection').hide(); $('#customerSection').show(); $('#state_dropdown_wrapper').show(); }
  $('#attachmentWrapper').hide();
  if (t==='seller') $('#attachmentWrapper').show();
  if (t==='customer' && $('#creditNoteType').val()==='goods') $('#attachmentWrapper').show();
}).trigger('change');

$('#creditNoteType').on('change', function(){
  const type=$(this).val(); $('#credit_note_type_input').val(type);
  if (type==='service'){ $('#serviceFieldsWrapper').show(); $('#productTableWrapper').hide(); $('#addProductBtn,#saveOrderBtn').hide(); $('#convertBtn').show(); }
  else { $('#serviceFieldsWrapper').hide(); $('#productTableWrapper').show(); $('#addProductBtn,#saveOrderBtn,#convertBtn').show(); }
  const partyType=$('#partyType').val(); $('#attachmentWrapper').hide();
  if (partyType==='seller') $('#attachmentWrapper').show();
  else if (partyType==='customer' && type==='goods') $('#attachmentWrapper').show();
});

// convert submit
$('#convertBtn').on('click', function () {
  if (!$('#convertFields').is(':visible')) { $('#convertFields').slideDown(); return; }
  if (!$('#seller_invoice_no').val() || !$('#seller_invoice_date').val()) { alert('Please enter Seller Invoice Number and Date.'); return; }
  if ($('#attachmentWrapper').is(':visible') && !$('#attachment').val()) { alert('Please upload the attachment (PDF / Image).'); return; }
  $('#actionField').val('convert'); $(this).closest('form').submit();
});

// customer pick
$('#customer').on('change', function () {
  const s=$(this).find(':selected');
  $('#seller_name').val(s.data('name'));
  $('#seller_address').val(s.data('address'));
  $('#seller_gstin').val(s.data('gstin')||'');
  $('#seller_phone').val(s.data('phone'));
  $('#seller_id').val(s.val());
  $('#state_id').val(s.data('state'));
  $('#address_id').val(s.val());
});
</script>
@endsection
