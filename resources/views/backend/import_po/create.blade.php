@extends('backend.layouts.app')

@section('content')

<style>
    .import-po-header {
        border-bottom: 1px solid #edf1f7;
        margin-bottom: 15px;
        padding-bottom: 8px;
    }
    .import-po-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
    }
    .import-po-subtitle {
        font-size: 13px;
        color: #6b7280;
    }

    .po-items-table {
        border-top: 1px solid #e5e7eb;
    }
    .po-items-table th,
    .po-items-table td {
        vertical-align: middle !important;
        font-size: 12px;
        white-space: nowrap;
    }

    .po-items-table thead th {
        background-color: #f3f4f6;
        border-bottom: 1px solid #e5e7eb;
        font-weight: 600;
        color: #4b5563;
    }

    .small-input {
        font-size: 12px;
        padding: 3px 6px;
        height: 30px;
        border-radius: 4px;
    }

    .small-input:focus {
        box-shadow: 0 0 0 0.1rem rgba(59,130,246,0.25);
        border-color: #3b82f6;
    }

    .product-select {
        width: 100% !important;
    }

    #poItemModal .modal-dialog {
        max-width: 1100px;
    }
    #poItemModal .modal-header {
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 18px;
    }
    #poItemModal .modal-title {
        font-size: 16px;
        font-weight: 600;
    }
    #poItemModal .modal-body {
        padding: 16px 20px 10px 20px;
        background-color: #f3f4f6;
    }

    .po-modal-section {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        padding: 12px 14px 10px 14px;
        margin-bottom: 12px;
    }
    .po-modal-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    .po-modal-section-title {
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #4b5563;
    }
    .po-modal-section-sub {
        font-size: 11px;
        color: #9ca3af;
    }

    #poItemModal .select2-container {
        width: 100% !important;
    }
    #poItemModal .select2-container .select2-selection--single {
        height: 46px;
        padding: 6px 10px;
        border-radius: 8px;
        border-color: #d1d5db;
        font-size: 14px;
        display: flex;
        align-items: center;
        box-shadow: 0 0 0 1px rgba(209,213,219,.3);
    }
    #poItemModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.4;
        padding-left: 2px;
        color: #111827;
    }
    #poItemModal .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9ca3af;
    }
    #poItemModal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px;
        right: 8px;
    }

    .packing-display-input {
        cursor: pointer;
        background-color: #f9fafb;
    }

    .import-image-preview img {
        max-width: 120px;
        max-height: 120px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        object-fit: cover;
    }

    .card-header h6 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 0;
    }

    .po-line-details {
        background-color: #f9fafb;
    }
    .po-line-details-inner {
        font-size: 11px;
        color: #4b5563;
    }
    .po-line-details-inner .label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 2px;
        display: inline-block;
    }

    .summary-badge {
        display: inline-block;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 9999px;
        background-color: #e5e7eb;
        color: #374151;
        margin-bottom: 2px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Header --}}
        <div class="import-po-header d-flex justify-content-between align-items-center">
            <div>
                <div class="import-po-title">
                    {{ translate('Create Import Purchase Order') }}
                </div>
                <div class="import-po-subtitle">
                    {{ translate('Add PO header details and build item list using the modal below.') }}
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('import_pos.store') }}" method="POST">
            @csrf

            {{-- HEADER --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="las la-file-alt mr-1"></i>{{ translate('PO Header Details') }}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Import Company --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ translate('Import Company') }} <span class="text-danger">*</span></label>
                            <select name="import_company_id" class="form-control aiz-selectpicker" data-live-search="true" required>
                                <option value="">{{ translate('Select Company') }}</option>
                                @foreach($importCompanies as $company)
                                    <option value="{{ $company->id }}" @if(old('import_company_id') == $company->id) selected @endif>
                                        {{ $company->company_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Supplier --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ translate('Supplier') }} <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-control aiz-selectpicker" data-live-search="true" required>
                                <option value="">{{ translate('Select Supplier') }}</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @if(old('supplier_id') == $supplier->id) selected @endif>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- PO No --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ translate('PO Number') }} <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="po_no"
                                   value="{{ old('po_no') }}"
                                   class="form-control"
                                   placeholder="{{ translate('Enter PO Number') }}"
                                   required>
                        </div>
                    </div>

                    <div class="row">
                        {{-- PO Date --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">{{ translate('PO Date') }} <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="po_date"
                                   value="{{ old('po_date', now()->toDateString()) }}"
                                   class="form-control"
                                   required>
                        </div>

                        {{-- Currency --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">{{ translate('Currency') }} <span class="text-danger">*</span></label>
                            <select name="currency_code" class="form-control" required>
                                <option value="USD" @if(old('currency_code') == 'USD') selected @endif>USD</option>
                                <option value="RMB" @if(old('currency_code') == 'RMB') selected @endif>RMB</option>
                                <option value="EUR" @if(old('currency_code') == 'EUR') selected @endif>EUR</option>
                            </select>
                        </div>

                        {{-- Delivery Terms --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">{{ translate('Delivery Terms') }}</label>
                            <input type="text"
                                   name="delivery_terms"
                                   value="{{ old('delivery_terms') }}"
                                   class="form-control"
                                   placeholder="{{ translate('e.g. FOB Ningbo, CIF Kolkata') }}">
                        </div>

                        {{-- Payment Terms --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">{{ translate('Payment Terms') }}</label>
                            <input type="text"
                                   name="payment_terms"
                                   value="{{ old('payment_terms') }}"
                                   class="form-control"
                                   placeholder="{{ translate('e.g. 30% Advance, 70% Against Documents') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <label class="form-label">{{ translate('Remarks / Special Instructions') }}</label>
                            <textarea name="remarks"
                                      rows="2"
                                      class="form-control"
                                      placeholder="{{ translate('Any additional notes for this PO...') }}">{{ old('remarks') }}</textarea>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ================= PO LINE ITEMS LIST ================= --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="las la-list-ul mr-1"></i>
                        {{ translate('PO Line Items') }}
                    </h6>
                    <button type="button"
                            class="btn btn-outline-primary btn-sm"
                            data-toggle="modal"
                            data-target="#poItemModal">
                        <i class="las la-plus-circle mr-1"></i>
                        {{ translate('Add Item (via Modal)') }}
                    </button>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 table-hover po-items-table" id="po_items_table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">{{ translate('Line No.') }}</th>
                                    <th style="width: 200px;">{{ translate('Product') }}</th>
                                    <th style="width: 140px;">{{ translate('Supplier Model No') }}</th>
                                    <th style="width: 220px;">{{ translate('Description') }}</th>
                                    <th style="width: 90px;"  class="text-right">{{ translate('Req. Qty') }}</th>
                                    <th style="width: 120px;" class="text-right">{{ translate('Unit Price (USD)') }}</th>
                                    <th style="width: 120px;" class="text-right">{{ translate('Unit Price (RMB)') }}</th>
                                    <th style="width: 140px;" class="text-center">{{ translate('Summary / Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="po_items_tbody">
                                <tr class="no-items-row">
                                    <td colspan="8" class="text-center text-muted py-3">
                                        <i class="las la-info-circle mr-1"></i>
                                        {{ translate('No items added yet. Click "Add Item (via Modal)" to insert the first line.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        {{ translate('Use the modal to add each PO item. All fields remain editable; expand any line to adjust carton/packing details.') }}
                    </small>
                </div>
            </div>

            <div class="text-right mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="las la-save mr-1"></i>
                    {{ translate('Save Purchase Order') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ================= MODAL FOR ADDING ONE ITEM ================= --}}
<div class="modal fade" id="poItemModal" tabindex="-1" role="dialog" aria-labelledby="poItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Add PO Item') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                {{-- Product + image --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">
                                    {{ translate('Product (Search by Part No / Name)') }}
                                </div>
                            </div>
                            <select id="modal_product_id"
                                    class="form-control product-select"
                                    data-placeholder="{{ translate('Search by Part No / Name') }}">
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">
                                    {{ translate('Import Image') }}
                                </div>
                            </div>

                            <div class="form-group mb-1">
                                <div class="input-group" data-toggle="aizuploader" data-type="image">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium">
                                            {{ translate('Browse') }}
                                        </div>
                                    </div>
                                    <div class="form-control file-amount">
                                        {{ translate('Choose File') }}
                                    </div>
                                    <input type="hidden"
                                           id="modal_import_photo_id"
                                           class="selected-files">
                                </div>
                                <div class="file-preview box sm import-image-preview" id="modal_import_image_preview_box"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Basic details + qty --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">{{ translate('Basic Details') }}</div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-2">
                                    <label class="mb-1">{{ translate('Supplier Model No') }}</label>
                                    <input type="text"
                                           id="modal_supplier_model_no"
                                           class="form-control small-input"
                                           placeholder="{{ translate('Model No') }}">
                                </div>

                                <div class="col-md-12 mb-1">
                                    <label class="mb-1">{{ translate('Description') }}</label>
                                    <textarea id="modal_description"
                                              rows="2"
                                              class="form-control small-input"
                                              placeholder="{{ translate('Item description') }}"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">{{ translate('Quantity & Packaging') }}</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="mb-1">{{ translate('Requirement Qty') }}</label>
                                    <input type="number"
                                           step="0.01"
                                           id="modal_requirement_qty"
                                           class="form-control small-input text-right"
                                           placeholder="0.00">
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="mb-1">{{ translate('Packaging') }}</label>
                                    <input type="text"
                                           id="modal_packaging"
                                           class="form-control small-input"
                                           placeholder="{{ translate('Box / Carton etc.') }}">
                                </div>

                                <div class="col-md-12">
                                    <label class="mb-1 d-flex justify-content-between align-items-center">
                                        <span>{{ translate('Packing Details') }}</span>
                                        <span style="display: none;" class="badge badge-soft-secondary" style="font-size:10px;">
                                            {{-- translate('Inner / Outer / Color / Brand') --}}
                                        </span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text"
                                               id="modal_packing_details_display"
                                               class="form-control small-input packing-display-input"
                                               placeholder="{{ translate('Click to set key/value') }}"
                                               readonly>
                                        <div class="input-group-append">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    id="btn_open_packing_modal">
                                                <i class="las la-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="modal_packing_details_raw">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Pricing & weight --}}
                <div class="row">
                    <div class="col-md-8">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">
                                    {{ translate('Pricing & Carton Details') }}
                                </div>
                                <div class="po-modal-section-sub">
                                    {{ translate('Total weight/CBM will be auto-calculated: (Req Qty ÷ Qty/Carton) × Carton values.') }}
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="mb-1">{{ translate('Unit Price') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                               step="0.0001"
                                               id="modal_unit_price"
                                               class="form-control small-input text-right"
                                               placeholder="0.0000">
                                        <div class="input-group-append">
                                            <select id="modal_price_currency"
                                                    class="form-control small-input"
                                                    style="max-width: 90px;">
                                                <option value="USD">USD</option>
                                                <option value="RMB">RMB</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label class="mb-1">{{ translate('Qty per Carton') }}</label>
                                    <input type="number"
                                           step="0.0001"
                                           id="modal_qty_per_carton"
                                           class="form-control small-input text-right"
                                           placeholder="0.000">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="mb-1">{{ translate('Weight/Carton (kg)') }}</label>
                                    <input type="number"
                                           step="0.0001"
                                           id="modal_weight_per_carton_kg"
                                           class="form-control small-input text-right"
                                           placeholder="0.000">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="mb-1">{{ translate('CBM/Carton') }}</label>
                                    <input type="number"
                                           step="0.0001"
                                           id="modal_cbm_per_carton"
                                           class="form-control small-input text-right"
                                           placeholder="0.000">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="po-modal-section">
                            <div class="po-modal-section-header">
                                <div class="po-modal-section-title">{{ translate('Remarks') }}</div>
                            </div>

                            <input type="text"
                                   id="modal_remarks"
                                   class="form-control small-input"
                                   placeholder="{{ translate('Any note for this line item') }}">
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <small class="text-muted mr-auto">
                    {{ translate('Product & Qty are recommended. Prices can be filled in selected currency.') }}
                </small>
                <button type="button" class="btn btn-soft-secondary btn-sm" data-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn_add_item_to_list">
                    <i class="las la-plus-circle mr-1"></i>
                    {{ translate('Add to List') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ================= PACKING DETAILS BUILDER MODAL ================= --}}
<div class="modal fade" id="packingDetailsModal" tabindex="-1" role="dialog" aria-labelledby="packingDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Packing Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="packing_kv_table">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40%;">{{ translate('Key') }}</th>
                                <th style="width: 50%;">{{ translate('Value') }}</th>
                                <th style="width: 10%;" class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_packing_row">
                                        <i class="las la-plus"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <small class="text-muted d-block mt-2">
                    {{ translate('Example: Inner Box: 10 pcs, Outer Box: 5 inner, Color: Red, Brand: OPEL etc.') }}
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary btn-sm" data-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn_save_packing_details">
                    {{ translate('Save Packing Details') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        (function($){
            "use strict";

            function renumberLines() {
                $('#po_items_tbody tr.po-line-row').each(function(index){
                    $(this).find('.line-no').text(index + 1);
                });
            }

            function recalcTotalsForRow($anyRow) {
                var $mainRow, $detailsRow;

                if ($anyRow.hasClass('po-line-row')) {
                    $mainRow    = $anyRow;
                    $detailsRow = $mainRow.next('.po-line-details');
                } else if ($anyRow.hasClass('po-line-details')) {
                    $detailsRow = $anyRow;
                    $mainRow    = $detailsRow.prev('.po-line-row');
                } else {
                    $detailsRow = $anyRow.closest('.po-line-details');
                    $mainRow    = $detailsRow.prev('.po-line-row');
                }

                if ($mainRow.length === 0 || $detailsRow.length === 0) return;

                var $reqQtyInput    = $mainRow.find('.line-req-qty');
                var $qtyCartonInput = $detailsRow.find('.line-qty-carton');
                var $wtCartonInput  = $detailsRow.find('.line-weight-carton');
                var $cbmCartonInput = $detailsRow.find('.line-cbm-carton');
                var $totalWtInput   = $detailsRow.find('.line-total-weight');
                var $totalCbmInput  = $detailsRow.find('.line-total-cbm');

                var reqQty   = parseFloat($reqQtyInput.val())    || 0;
                var qtyCtn   = parseFloat($qtyCartonInput.val()) || 0;
                var wtCtn    = parseFloat($wtCartonInput.val())  || 0;
                var cbmCtn   = parseFloat($cbmCartonInput.val()) || 0;

                var totalWt = '';
                var totalCb = '';

                if (reqQty > 0 && qtyCtn > 0) {
                    var cartons = reqQty / qtyCtn;
                    if (wtCtn > 0) {
                        totalWt = (cartons * wtCtn).toFixed(3);
                    }
                    if (cbmCtn > 0) {
                        totalCb = (cartons * cbmCtn).toFixed(3);
                    }
                }

                if (totalWt !== '') $totalWtInput.val(totalWt);
                if (totalCb !== '') $totalCbmInput.val(totalCb);

                var $sumCtn  = $mainRow.find('.summary-ctn');
                var $sumWt   = $mainRow.find('.summary-wt');
                var $sumCbm  = $mainRow.find('.summary-cbm');

                var qtyCtnDisplay = qtyCtn > 0 ? (qtyCtn + ' /ctn') : '-';
                var wtDisplay     = totalWt !== '' && parseFloat(totalWt) > 0 ? (totalWt + ' kg')  : '-';
                var cbmDisplay    = totalCb !== '' && parseFloat(totalCb) > 0 ? (totalCb + ' cbm') : '-';

                $sumCtn.text(qtyCtnDisplay);
                $sumWt.text(wtDisplay);
                $sumCbm.text(cbmDisplay);
            }

            function initProductSelect() {
                $('#modal_product_id').select2({
                    placeholder: $('#modal_product_id').data('placeholder') || "{{ translate('Search by Part No / Name') }}",
                    allowClear: true,
                    minimumInputLength: 1,
                    dropdownParent: $('#poItemModal'),
                    ajax: {
                        url: '{{ route("import.pos.ajax.products_search") }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '' };
                        },
                        processResults: function (data) {
                            return data;
                        },
                        cache: true
                    },
                    language: {
                        inputTooShort: function () {
                            return "{{ translate('Type at least 1 character to search') }}";
                        }
                    }
                });
            }

            function defaultPackingRows() {
                return [
                    { key: 'Inner Box', value: '' },
                    { key: 'Outer Box', value: '' },
                    { key: 'Color',     value: '' },
                    { key: 'Brand',     value: '' },
                ];
            }

            function renderPackingTable(rows) {
                var $tbody = $('#packing_kv_table tbody');
                $tbody.empty();

                if (!rows || !rows.length) {
                    rows = defaultPackingRows();
                }

                rows.forEach(function(row){
                    var key = row.key || '';
                    var val = row.value || '';

                    var tr = `
                        <tr>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm pack-key"
                                       value="${ $('<div>').text(key).html() }">
                            </td>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm pack-value"
                                       value="${ $('<div>').text(val).html() }">
                            </td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-icon btn-sm btn-soft-danger btn-remove-pack-row">
                                    <i class="las la-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $tbody.append(tr);
                });
            }

            function parsePackingJson(jsonStr) {
                if (!jsonStr) return [];
                try {
                    var data = JSON.parse(jsonStr);
                    if (Array.isArray(data)) return data;
                    return [];
                } catch (e) {
                    return [];
                }
            }

            function buildPackingDisplayText(rows) {
                var parts = [];
                rows.forEach(function(row){
                    var k = (row.key || '').trim();
                    var v = (row.value || '').trim();
                    if (k === '' && v === '') return;
                    if (k && v) {
                        parts.push(k + ': ' + v);
                    } else if (k) {
                        parts.push(k);
                    } else if (v) {
                        parts.push(v);
                    }
                });
                return parts.join('; ');
            }

            function resetItemModalFields() {
                $('#modal_product_id').val(null).trigger('change');
                $('#modal_supplier_model_no').val('');
                $('#modal_requirement_qty').val('');
                $('#modal_description').val('');
                $('#modal_packaging').val('');
                $('#modal_unit_price').val('');
                $('#modal_price_currency').val('USD');
                $('#modal_qty_per_carton').val('');
                $('#modal_weight_per_carton_kg').val('');
                $('#modal_cbm_per_carton').val('');
                $('#modal_remarks').val('');

                $('#modal_packing_details_raw').val('');
                $('#modal_packing_details_display').val('');

                $('#modal_import_photo_id').val('');
                $('#modal_import_image_preview_box').html('');
            }

            var packingContext = {
                mode: 'add',          // 'add' | 'line'
                detailsRow: null,
                lineDisplayInput: null
            };

            $(document).ready(function(){

                initProductSelect();

                $('#poItemModal').on('show.bs.modal', function () {
                    resetItemModalFields();
                    packingContext.mode = 'add';
                    setTimeout(function () {
                        $('#modal_product_id').select2('open');
                    }, 300);
                });

                $('#modal_product_id').on('select2:select', function(e){
                    var data = e.params.data || {};
                    var photoId       = data.import_photo_id || '';
                    var photoUrl      = data.import_photo_url || '';
                    var qtyPerCarton  = data.quantity_per_carton || '';
                    var weightPerCtn  = data.weight_per_carton || '';
                    var cbmPerCtn     = data.cbm_per_carton || '';
                    var dollarPrice   = data.dollar_price || '';

                    if (photoId) {
                        $('#modal_import_photo_id').val(photoId);
                        if (photoUrl) {
                            $('#modal_import_image_preview_box').html(
                                '<img src="' + photoUrl + '" class="img-fit" alt="import image">'
                            );
                        } else {
                            $('#modal_import_image_preview_box').html('');
                        }
                    } else {
                        $('#modal_import_photo_id').val('');
                        $('#modal_import_image_preview_box').html('');
                    }

                    if (qtyPerCarton !== '') {
                        $('#modal_qty_per_carton').val(qtyPerCarton);
                    }
                    if (weightPerCtn !== '') {
                        $('#modal_weight_per_carton_kg').val(weightPerCtn);
                    }
                    if (cbmPerCtn !== '') {
                        $('#modal_cbm_per_carton').val(cbmPerCtn);
                    }
                    if (dollarPrice !== '') {
                        $('#modal_unit_price').val(dollarPrice);
                        $('#modal_price_currency').val('USD');
                    }
                });

                // open packing modal from ADD
                $('#btn_open_packing_modal, #modal_packing_details_display').on('click', function(){
                    packingContext.mode = 'add';
                    packingContext.detailsRow = null;
                    packingContext.lineDisplayInput = null;

                    var raw = $('#modal_packing_details_raw').val();
                    var rows = parsePackingJson(raw);
                    if (!rows || rows.length === 0) {
                        rows = defaultPackingRows();
                    }
                    renderPackingTable(rows);
                    $('#packingDetailsModal').modal('show');
                });

                $('#btn_add_packing_row').on('click', function(){
                    var $tbody = $('#packing_kv_table tbody');
                    var tr = `
                        <tr>
                            <td><input type="text" class="form-control form-control-sm pack-key" value=""></td>
                            <td><input type="text" class="form-control form-control-sm pack-value" value=""></td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-icon btn-sm btn-soft-danger btn-remove-pack-row">
                                    <i class="las la-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $tbody.append(tr);
                });

                $(document).on('click', '.btn-remove-pack-row', function(){
                    $(this).closest('tr').remove();
                });

                // save packing → apply either to ADD modal or LIST row
                $('#btn_save_packing_details').on('click', function(){
                    var rows = [];
                    $('#packing_kv_table tbody tr').each(function(){
                        var key = $(this).find('.pack-key').val() || '';
                        var val = $(this).find('.pack-value').val() || '';
                        if (key.trim() === '' && val.trim() === '') return;
                        rows.push({ key: key, value: val });
                    });

                    if (rows.length === 0) {
                        rows = defaultPackingRows();
                    }

                    var jsonStr = JSON.stringify(rows);
                    var display = buildPackingDisplayText(rows);

                    if (packingContext.mode === 'add') {
                        $('#modal_packing_details_raw').val(jsonStr);
                        $('#modal_packing_details_display').val(display);
                    } else if (packingContext.mode === 'line') {
                        if (packingContext.detailsRow && packingContext.lineDisplayInput) {
                            packingContext.lineDisplayInput.val(display);
                            packingContext.detailsRow.find('.line-packing-details-json').val(jsonStr);
                        }
                    }

                    $('#packingDetailsModal').modal('hide');
                });

                // ADD ITEM TO LIST
                $('#btn_add_item_to_list').on('click', function () {
                    const productData = $('#modal_product_id').select2('data');
                    const productId   = productData && productData.length ? productData[0].id : '';
                    const productText = productData && productData.length ? productData[0].text : '';

                    const supplierModelNo   = $('#modal_supplier_model_no').val() || '';
                    const requirementQtyStr = $('#modal_requirement_qty').val() || '';
                    const description       = $('#modal_description').val() || '';
                    const packaging         = $('#modal_packaging').val() || '';

                    const packingDisplay    = $('#modal_packing_details_display').val() || '';
                    const packingRawJson    = $('#modal_packing_details_raw').val() || '';

                    const unitPriceStr      = $('#modal_unit_price').val() || '';
                    const priceCurrency     = $('#modal_price_currency').val() || 'USD';
                    const qtyPerCartonStr   = $('#modal_qty_per_carton').val() || '';
                    const weightPerCtnStr   = $('#modal_weight_per_carton_kg').val() || '';
                    const cbmPerCtnStr      = $('#modal_cbm_per_carton').val() || '';
                    const remarks           = $('#modal_remarks').val() || '';
                    const importPhotoId     = $('#modal_import_photo_id').val() || '';

                    if (!productId && supplierModelNo.trim() === '' && description.trim() === '') {
                        alert("{{ translate('Please select a product or enter at least model no / description.') }}");
                        return;
                    }

                    const requirementQty = parseFloat(requirementQtyStr) || 0;
                    const qtyPerCarton   = parseFloat(qtyPerCartonStr)   || 0;
                    const weightPerCtn   = parseFloat(weightPerCtnStr)   || 0;
                    const cbmPerCtn      = parseFloat(cbmPerCtnStr)      || 0;

                    let totalWeight = '';
                    let totalCbm    = '';

                    if (requirementQty > 0 && qtyPerCarton > 0) {
                        const cartons = requirementQty / qtyPerCarton;
                        if (weightPerCtn > 0) {
                            totalWeight = (cartons * weightPerCtn).toFixed(3);
                        }
                        if (cbmPerCtn > 0) {
                            totalCbm = (cartons * cbmPerCtn).toFixed(3);
                        }
                    }

                    let unitPriceUsd = '';
                    let unitPriceRmb = '';
                    if (unitPriceStr !== '') {
                        if (priceCurrency === 'USD') {
                            unitPriceUsd = unitPriceStr;
                        } else if (priceCurrency === 'RMB') {
                            unitPriceRmb = unitPriceStr;
                        }
                    }

                    $('#po_items_tbody').find('tr.no-items-row').remove();

                    const esc = function(str){
                        return $('<div>').text(str || '').html();
                    };

                    const mainRowHtml = `
                        <tr class="po-line-row">
                            <td class="text-center line-no"></td>

                            <td>
                                <div class="font-weight-600 text-truncate" title="${ esc(productText) }">
                                    ${ productText ? esc(productText) : '-' }
                                </div>
                                <input type="hidden" name="lines[product_id][]" value="${ esc(productId) }">
                                <input type="hidden" name="lines[photo_id][]"   value="${ esc(importPhotoId) }">
                            </td>

                            <td>
                                <input type="text"
                                       class="form-control form-control-sm small-input line-supplier-model"
                                       name="lines[supplier_model_no][]"
                                       value="${ esc(supplierModelNo) }">
                            </td>

                            <td>
                                <input type="text"
                                       class="form-control form-control-sm small-input line-description"
                                       name="lines[description][]"
                                       value="${ esc(description) }">
                            </td>

                            <td class="text-right">
                                <input type="number"
                                       step="0.01"
                                       class="form-control form-control-sm small-input text-right line-req-qty"
                                       name="lines[requirement_qty][]"
                                       value="${ esc(requirementQtyStr) }">
                            </td>

                            <td class="text-right">
                                <input type="number"
                                       step="0.0001"
                                       class="form-control form-control-sm small-input text-right"
                                       name="lines[unit_price_usd][]"
                                       value="${ esc(unitPriceUsd) }">
                            </td>

                            <td class="text-right">
                                <input type="number"
                                       step="0.0001"
                                       class="form-control form-control-sm small-input text-right"
                                       name="lines[unit_price_rmb][]"
                                       value="${ esc(unitPriceRmb) }">
                            </td>

                            <td class="text-center">
                                <div><span class="summary-badge summary-ctn">-</span></div>
                                <div><span class="summary-badge summary-wt">-</span></div>
                                <div><span class="summary-badge summary-cbm">-</span></div>
                                <div class="mt-1">
                                    <button type="button" class="btn btn-xs btn-soft-info btn-toggle-details">
                                        <i class="las la-chevron-down"></i> {{ translate('Details') }}
                                    </button>
                                    <button type="button" class="btn btn-icon btn-sm btn-soft-danger btn-remove-line ml-1">
                                        <i class="las la-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;

                    const safeJson = packingRawJson.replace(/'/g,"&#39;");

                    const detailsRowHtml = `
                        <tr class="po-line-details d-none">
                            <td colspan="8">
                                <div class="po-line-details-inner pt-2 border-top mt-1">
                                    <div class="row">
                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('Qty/Carton') }}</span>
                                            <input type="number"
                                                   step="0.0001"
                                                   class="form-control form-control-sm small-input text-right line-qty-carton"
                                                   name="lines[qty_per_carton][]"
                                                   value="${ esc(qtyPerCartonStr) }">
                                        </div>
                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('Wt/Carton (kg)') }}</span>
                                            <input type="number"
                                                   step="0.0001"
                                                   class="form-control form-control-sm small-input text-right line-weight-carton"
                                                   name="lines[weight_per_carton_kg][]"
                                                   value="${ esc(weightPerCtnStr) }">
                                        </div>
                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('CBM/Carton') }}</span>
                                            <input type="number"
                                                   step="0.0001"
                                                   class="form-control form-control-sm small-input text-right line-cbm-carton"
                                                   name="lines[cbm_per_carton][]"
                                                   value="${ esc(cbmPerCtnStr) }">
                                        </div>

                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('Packaging') }}</span>
                                            <input type="text"
                                                   class="form-control form-control-sm small-input line-packaging"
                                                   name="lines[packaging][]"
                                                   value="${ esc(packaging) }">
                                        </div>

                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('Total Weight (kg)') }}</span>
                                            <input type="number"
                                                   step="0.0001"
                                                   class="form-control form-control-sm small-input text-right line-total-weight"
                                                   name="lines[total_weight][]"
                                                   value="${ esc(totalWeight) }">
                                        </div>
                                        <div class="col-md-3 mb-1">
                                            <span class="label">{{ translate('Total CBM') }}</span>
                                            <input type="number"
                                                   step="0.0001"
                                                   class="form-control form-control-sm small-input text-right line-total-cbm"
                                                   name="lines[total_cbm][]"
                                                   value="${ esc(totalCbm) }">
                                        </div>

                                        <div class="col-md-6 mb-1">
                                            <span class="label d-flex justify-content-between align-items-center">
                                                <span>{{ translate('Packing Details') }}</span>
                                            </span>
                                            <div class="input-group input-group-sm">
                                                <input type="text"
                                                       class="form-control form-control-sm small-input line-packing-details-display"
                                                       value="${ esc(packingDisplay) }"
                                                       readonly>
                                                <input type="hidden"
                                                       class="line-packing-details-json"
                                                       name="lines[packing_details][]"
                                                       value='${ safeJson }'>
                                                <div class="input-group-append">
                                                    <button type="button"
                                                            class="btn btn-outline-secondary btn-xs btn-edit-packing-details-line">
                                                        <i class="las la-edit"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-1">
                                            <span class="label">{{ translate('Remarks') }}</span>
                                            <input type="text"
                                                   class="form-control form-control-sm small-input line-remarks"
                                                   name="lines[remarks][]"
                                                   value="${ esc(remarks) }">
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;

                    $('#po_items_tbody').append(mainRowHtml + detailsRowHtml);
                    renumberLines();
                    recalcTotalsForRow($('#po_items_tbody tr.po-line-row').last());

                    $('#poItemModal').modal('hide');
                });

                $(document).on('click', '.btn-toggle-details', function () {
                    var $mainRow    = $(this).closest('tr.po-line-row');
                    var $detailsRow = $mainRow.next('.po-line-details');
                    $detailsRow.toggleClass('d-none');

                    var $icon = $(this).find('i.las');
                    if ($detailsRow.hasClass('d-none')) {
                        $icon.removeClass('la-chevron-up').addClass('la-chevron-down');
                    } else {
                        $icon.removeClass('la-chevron-down').addClass('la-chevron-up');
                    }
                });

                // open packing modal from LIST row
                $(document).on('click', '.btn-edit-packing-details-line', function () {
                    packingContext.mode = 'line';
                    var $detailsRow = $(this).closest('.po-line-details');
                    packingContext.detailsRow = $detailsRow;
                    packingContext.lineDisplayInput = $detailsRow.find('.line-packing-details-display');

                    var raw = $detailsRow.find('.line-packing-details-json').val() || '';
                    var rows = parsePackingJson(raw);
                    if (!rows || rows.length === 0) {
                        rows = defaultPackingRows();
                    }
                    renderPackingTable(rows);
                    $('#packingDetailsModal').modal('show');
                });

                $(document).on('click', '.btn-remove-line', function () {
                    var $mainRow    = $(this).closest('tr.po-line-row');
                    var $detailsRow = $mainRow.next('.po-line-details');
                    $detailsRow.remove();
                    $mainRow.remove();

                    if ($('#po_items_tbody tr.po-line-row').length === 0) {
                        $('#po_items_tbody').html(`
                            <tr class="no-items-row">
                                <td colspan="8" class="text-center text-muted py-3">
                                    <i class="las la-info-circle mr-1"></i>
                                    {{ translate('No items added yet. Click "Add Item (via Modal)" to insert the first line.') }}
                                </td>
                            </tr>
                        `);
                    } else {
                        renumberLines();
                    }
                });

                $(document).on('input change', '.line-req-qty, .line-qty-carton, .line-weight-carton, .line-cbm-carton', function () {
                    recalcTotalsForRow($(this).closest('tr'));
                });

            });

        })(jQuery);
    </script>
@endsection
