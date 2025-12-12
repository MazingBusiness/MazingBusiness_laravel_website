@extends('backend.layouts.app')

@section('content')

<style>
    .import-row-table th,
    .import-row-table td {
        vertical-align: middle !important;
        font-size: 12px;
        white-space: nowrap;
    }

    .select2-container {
        width: 100% !important;
    }

    .small-input {
        font-size: 12px;
        padding: 2px 6px;
        height: 28px;
    }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="h4 mb-0">
                {{ translate('Import Products for BL') }} :
                <span class="text-primary">{{ $bl->bl_no }}</span>
            </h1>
            <small class="text-muted d-block mt-1">
                {{ translate('Select products with quantity and dollar price, then fill import details in modal and add them to the import cart.') }}
            </small>
        </div>

        <div class="col-auto text-right">
            <a href="{{ route('import.cart.index', $bl->id) }}" class="btn btn-soft-primary btn-sm">
                <i class="las la-shopping-cart mr-1"></i>
                {{ translate('View Cart for this BL') }}
            </a>

            <a href="{{ route('import_companies.index') }}" class="btn btn-soft-secondary btn-sm">
                <i class="las la-arrow-left mr-1"></i>
                <span class="d-none d-md-inline">{{ translate('Back to Import Companies') }}</span>
            </a>
        </div>
    </div>
</div>

{{-- BL Summary Card --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="row">
            <div class="col-md-4">
                <h6 class="mb-1 text-muted">{{ translate('Import Company') }}</h6>
                <strong>{{ optional($bl->importCompany)->company_name }}</strong><br>
                <small class="text-muted">
                    {{ optional($bl->importCompany)->city }},
                    {{ optional($bl->importCompany)->state }}
                </small>
            </div>
            <div class="col-md-4">
                <h6 class="mb-1 text-muted">{{ translate('BL Details') }}</h6>
                <div>
                    <strong>{{ translate('BL No.') }}:</strong> {{ $bl->bl_no ?? '-' }}
                </div>
                <div>
                    <strong>{{ translate('On Board Date') }}:</strong>
                    {{ $bl->ob_date ? \Carbon\Carbon::parse($bl->ob_date)->format('d-m-Y') : '-' }}
                </div>
            </div>
            <div class="col-md-4">
                <h6 class="mb-1 text-muted">{{ translate('Supplier') }}</h6>
                <strong>{{ optional($bl->supplier)->supplier_name ?? translate('Not Linked') }}</strong><br>
                <small class="text-muted">
                    {{ optional($bl->supplier)->city }}
                    {{ optional($bl->supplier) && optional($bl->supplier)->city ? ',' : '' }}
                    {{ optional($bl->supplier)->country }}
                </small>
            </div>
        </div>
    </div>
</div>

{{-- MAIN CARD: Row based entry --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 h6">
            <i class="las la-list-ul mr-1"></i>
            {{ translate('Add Products to Import Cart') }}
        </h5>
        <small class="text-muted d-none d-md-inline">
            {{ translate('Search product by Part No or Name (AJAX), enter quantity & price, then use Details button to fill import fields.') }}
        </small>
    </div>

    <div class="card-body">
        <form id="import_products_form"
              action="{{ route('import_bl.products.add_to_cart', $bl->id) }}"
              method="POST">
            @csrf

            <input type="hidden" name="import_company_id" value="{{ $bl->import_company_id }}">
            <input type="hidden" name="bl_id" value="{{ $bl->id }}">

            <div class="table-responsive">
                <table class="table table-sm import-row-table" id="import_products_table">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 35%;">{{ translate('Product (Search by Part No / Name)') }}</th>
                            <th style="width: 10%;">{{ translate('Quantity') }}</th>
                            <th style="width: 12%;">{{ translate('Dollar Price (USD)') }}</th>
                            <th style="width: 18%;">{{ translate('Supplier / Details') }}</th>
                            <th style="width: 5%;" class="text-center">
                                {{ translate('Remove') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 🔹 Default First Row --}}
                        <tr class="import-row">
                            {{-- PRODUCT SELECT (AJAX) --}}
                            <td>
                                <select name="product_id[]"
                                        class="form-control form-control-sm small-input product-select"
                                        data-placeholder="{{ translate('Search by Part No / Name') }}">
                                </select>
                            </td>

                            {{-- QUANTITY --}}
                            <td>
                                <input type="number"
                                       name="quantity[]"
                                       class="form-control form-control-sm small-input text-center"
                                       min="1"
                                       value="1"
                                       placeholder="1">
                            </td>

                            {{-- DOLLAR PRICE --}}
                            <td>
                                <input type="number"
                                       step="0.0001"
                                       name="dollar_price[]"
                                       class="form-control form-control-sm small-input text-right"
                                       placeholder="0.0000">
                            </td>

                            {{-- SUPPLIER + DETAILS BUTTON + HIDDEN IMPORT FIELDS --}}
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <small class="text-muted">{{ translate('Supplier') }}:</small>
                                        <div class="supplier-display font-weight-600">
                                            -
                                        </div>
                                        <small class="text-muted d-block details-status">
                                            {{ translate('Details not filled') }}
                                        </small>
                                    </div>
                                    <div class="ml-2">
                                        <button type="button"
                                                class="btn btn-soft-info btn-sm btn-edit-details"
                                                title="{{ translate('Fill Import Details') }}">
                                            <i class="las la-pen"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- 🔒 HIDDEN FIELDS (per row) --}}
                                <input type="hidden" name="import_print_name[]" class="field-import_print_name">
                                <input type="hidden" name="weight_per_carton[]" class="field-weight_per_carton">
                                <input type="hidden" name="cbm_per_carton[]" class="field-cbm_per_carton">
                                <input type="hidden" name="quantity_per_carton[]" class="field-quantity_per_carton">
                                <input type="hidden" name="supplier_id[]" class="field-supplier_id">
                                <input type="hidden" name="supplier_invoice_no[]" class="field-supplier_invoice_no">
                                <input type="hidden" name="supplier_invoice_date[]" class="field-supplier_invoice_date">
                                <input type="hidden" name="terms[]" class="field-terms">
                            </td>

                            {{-- REMOVE ROW --}}
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-icon btn-sm btn-soft-danger btn-remove-row"
                                        title="{{ translate('Remove row') }}">
                                    <i class="las la-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- ACTIONS --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        id="add_row_btn">
                    <i class="las la-plus-circle mr-1"></i>
                    {{ translate('Add New Row') }}
                </button>

                <button type="submit"
                        class="btn btn-primary"
                        id="add_to_cart_btn">
                    <i class="las la-cart-plus mr-1"></i>
                    {{ translate('Add to Cart & Go to Cart') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 🔹 HIDDEN ROW TEMPLATE --}}
<script type="text/x-template" id="import-row-template">
<tr class="import-row">
    <td>
        <select name="product_id[]"
                class="form-control form-control-sm small-input product-select"
                data-placeholder="{{ translate('Search by Part No / Name') }}">
        </select>
    </td>
    <td>
        <input type="number"
               name="quantity[]"
               class="form-control form-control-sm small-input text-center"
               min="1"
               value="1"
               placeholder="1">
    </td>
    <td>
        <input type="number"
               step="0.0001"
               name="dollar_price[]"
               class="form-control form-control-sm small-input text-right"
               placeholder="0.0000">
    </td>
    <td>
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <small class="text-muted">{{ translate('Supplier') }}:</small>
                <div class="supplier-display font-weight-600">
                    -
                </div>
                <small class="text-muted d-block details-status">
                    {{ translate('Details not filled') }}
                </small>
            </div>
            <div class="ml-2">
                <button type="button"
                        class="btn btn-soft-info btn-sm btn-edit-details"
                        title="{{ translate('Fill Import Details') }}">
                    <i class="las la-pen"></i>
                </button>
            </div>
        </div>

        <input type="hidden" name="import_print_name[]" class="field-import_print_name">
        <input type="hidden" name="weight_per_carton[]" class="field-weight_per_carton">
        <input type="hidden" name="cbm_per_carton[]" class="field-cbm_per_carton">
        <input type="hidden" name="quantity_per_carton[]" class="field-quantity_per_carton">
        <input type="hidden" name="supplier_id[]" class="field-supplier_id">
        <input type="hidden" name="supplier_invoice_no[]" class="field-supplier_invoice_no">
        <input type="hidden" name="supplier_invoice_date[]" class="field-supplier_invoice_date">
        <input type="hidden" name="terms[]" class="field-terms">
    </td>
    <td class="text-center">
        <button type="button"
                class="btn btn-icon btn-sm btn-soft-danger btn-remove-row"
                title="{{ translate('Remove row') }}">
            <i class="las la-trash"></i>
        </button>
    </td>
</tr>
</script>

{{-- 🔹 IMPORT DETAILS MODAL --}}
<div class="modal fade" id="importDetailsModal" tabindex="-1" role="dialog" aria-labelledby="importDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importDetailsModalLabel">
                    {{ translate('Import Item Details') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">
                    {{-- Import Print Name --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ translate('Import Print Name') }}</label>
                            <input type="text" id="modal_import_print_name" class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- Qty per Carton --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ translate('Qty per Carton') }}</label>
                            <input type="number" id="modal_quantity_per_carton" min="1" class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- Terms (default from BL port_of_loading) --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ translate('Terms') }}</label>
                            <input type="text" id="modal_terms" class="form-control form-control-sm">
                            <small class="text-muted">
                                {{ translate('Default: BL Port of Loading') }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    {{-- Weight per Carton --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ translate('Weight per Carton') }}</label>
                            <input type="number" step="0.0001" id="modal_weight_per_carton" class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- CBM per Carton --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ translate('CBM per Carton') }}</label>
                            <input type="number" step="0.0001" id="modal_cbm_per_carton" class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- Supplier --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ translate('Supplier') }}</label>
                            <select id="modal_supplier_id"
                                    class="form-control form-control-sm aiz-selectpicker"
                                    data-live-search="true"
                                    title="{{ translate('Select Supplier') }}">
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    {{-- Supplier Invoice No --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ translate('Supplier Invoice No') }}</label>
                            <input type="text" id="modal_supplier_invoice_no" class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- Supplier Invoice Date --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ translate('Supplier Invoice Date') }}</label>
                            <input type="date" id="modal_supplier_invoice_date" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-soft-secondary btn-sm"
                        data-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        id="save_import_details_btn">
                    {{ translate('Save Details') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript">
        (function($){
            "use strict";

            let rowTemplateHtml = '';
            let $currentRow = null; // jis row ke liye modal open hai

            // BL se default terms (port_of_loading)
            var defaultTermsFromBL = @json($bl->port_of_loading);

            function initProductSelect($element) {
                $element.select2({
                    placeholder: $element.data('placeholder') || "{{ translate('Search by Part No / Name') }}",
                    allowClear: true,
                    minimumInputLength: 2,
                    ajax: {
                        url: '{{ route("import.ajax.products_search") }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '' };
                        },
                        processResults: function (data) {
                            // Controller returns: { results: [ {id,text,import_print_name,...}, ... ] }
                            return data;
                        },
                        cache: true
                    }
                });
            }

            $(document).ready(function () {
                rowTemplateHtml = $('#import-row-template').html();

                // First row product select init
                let $firstRow = $('#import_products_table tbody tr.import-row:first');
                initProductSelect($firstRow.find('.product-select'));

                // Modal supplier selectpicker
                $('#modal_supplier_id').selectpicker('refresh');

                // Add row
                $('#add_row_btn').on('click', function (e) {
                    e.preventDefault();

                    let $tbody = $('#import_products_table tbody');
                    let $newRow = $(rowTemplateHtml);

                    $newRow.find('input').val('');
                    $newRow.find('input[name="quantity[]"]').val(1);

                    $tbody.append($newRow);

                    initProductSelect($newRow.find('.product-select'));
                });

                // Remove row
                $(document).on('click', '.btn-remove-row', function () {
                    let $rows = $('#import_products_table tbody tr.import-row');
                    let $row  = $(this).closest('tr');

                    if ($rows.length <= 1) {
                        let $productSelect = $row.find('.product-select');
                        if ($productSelect.data('select2')) {
                            $productSelect.val(null).trigger('change');
                        }
                        $row.find('input').val('');
                        $row.find('input[name="quantity[]"]').val(1);
                        $row.find('.supplier-display').text('-');
                        $row.find('.details-status')
                            .text('{{ translate('Details not filled') }}')
                            .removeClass('text-success')
                            .addClass('text-muted');

                        // hidden fields reset
                        $row.find('.field-import_print_name').val('');
                        $row.find('.field-weight_per_carton').val('');
                        $row.find('.field-cbm_per_carton').val('');
                        $row.find('.field-quantity_per_carton').val('');
                        $row.find('.field-supplier_id').val('');
                        $row.find('.field-supplier_invoice_no').val('');
                        $row.find('.field-supplier_invoice_date').val('');
                        $row.find('.field-terms').val('');
                    } else {
                        let $productSelect = $row.find('.product-select');
                        if ($productSelect.data('select2')) {
                            $productSelect.select2('destroy');
                        }
                        $row.remove();
                    }
                });

                // 🔹 Product select → auto-fill row fields from Product table
                $(document).on('select2:select', '.product-select', function (e) {
                    let data = e.params.data || {};
                    let $row = $(this).closest('tr.import-row');

                    if (!$row.length) return;

                    // Dollar price input
                    if (typeof data.dollar_price !== 'undefined' && data.dollar_price !== null) {
                        $row.find('input[name="dollar_price[]"]').val(data.dollar_price);
                    } else {
                        // keep as-is if null
                    }

                    // Hidden fields
                    $row.find('.field-import_print_name').val(data.import_print_name || '');
                    $row.find('.field-weight_per_carton').val(
                        typeof data.weight_per_carton !== 'undefined' && data.weight_per_carton !== null
                            ? data.weight_per_carton
                            : ''
                    );
                    $row.find('.field-cbm_per_carton').val(
                        typeof data.cbm_per_carton !== 'undefined' && data.cbm_per_carton !== null
                            ? data.cbm_per_carton
                            : ''
                    );
                    $row.find('.field-quantity_per_carton').val(
                        typeof data.quantity_per_carton !== 'undefined' && data.quantity_per_carton !== null
                            ? data.quantity_per_carton
                            : ''
                    );

                    // Supplier (if mapped in product)
                    if (typeof data.supplier_id !== 'undefined' && data.supplier_id) {
                        $row.find('.field-supplier_id').val(data.supplier_id);

                        // Supplier name display (if sent from server)
                        if (typeof data.supplier_name !== 'undefined' && data.supplier_name) {
                            $row.find('.supplier-display').text(data.supplier_name);
                        }
                    } else {
                        $row.find('.field-supplier_id').val('');
                        $row.find('.supplier-display').text('-');
                    }

                    // Details status (agar kuch bhi data aaya hai to Filled dikh sakta hai,
                    // lekin safe side: sirf hidden me kuch ho to hi change karo)
                    if (
                        (data.import_print_name && data.import_print_name !== '') ||
                        (data.weight_per_carton && data.weight_per_carton !== null) ||
                        (data.cbm_per_carton && data.cbm_per_carton !== null) ||
                        (data.quantity_per_carton && data.quantity_per_carton !== null) ||
                        (data.supplier_id && data.supplier_id !== null)
                    ) {
                        $row.find('.details-status')
                            .text('{{ translate('Details filled from product master') }}')
                            .removeClass('text-muted')
                            .addClass('text-success');
                    } else {
                        $row.find('.details-status')
                            .text('{{ translate('Details not filled') }}')
                            .removeClass('text-success')
                            .addClass('text-muted');
                    }
                });

                // Open modal for a row
                $(document).on('click', '.btn-edit-details', function () {
                    $currentRow = $(this).closest('tr.import-row');

                    if (!$currentRow || $currentRow.length === 0) {
                        return;
                    }

                    // Read hidden values from row
                    $('#modal_import_print_name').val($currentRow.find('.field-import_print_name').val());
                    $('#modal_weight_per_carton').val($currentRow.find('.field-weight_per_carton').val());
                    $('#modal_cbm_per_carton').val($currentRow.find('.field-cbm_per_carton').val());
                    $('#modal_quantity_per_carton').val($currentRow.find('.field-quantity_per_carton').val());
                    $('#modal_supplier_invoice_no').val($currentRow.find('.field-supplier_invoice_no').val());
                    $('#modal_supplier_invoice_date').val($currentRow.find('.field-supplier_invoice_date').val());

                    // TERMS: agar row me pehle se set hai to wahi, warna BL.port_of_loading
                    let existingTerms = $currentRow.find('.field-terms').val();
                    if (existingTerms && existingTerms.length > 0) {
                        $('#modal_terms').val(existingTerms);
                    } else {
                        $('#modal_terms').val(defaultTermsFromBL || '');
                    }

                    let supplierId = $currentRow.find('.field-supplier_id').val() || '';
                    $('#modal_supplier_id').val(supplierId);
                    $('#modal_supplier_id').selectpicker('refresh');

                    $('#importDetailsModal').modal('show');
                });

                // Save modal data back to row
                $('#save_import_details_btn').on('click', function () {
                    if (!$currentRow || $currentRow.length === 0) {
                        return;
                    }

                    let importPrintName   = $('#modal_import_print_name').val() || '';
                    let weightPerCarton   = $('#modal_weight_per_carton').val() || '';
                    let cbmPerCarton      = $('#modal_cbm_per_carton').val() || '';
                    let qtyPerCarton      = $('#modal_quantity_per_carton').val() || '';
                    let supplierId        = $('#modal_supplier_id').val() || '';
                    let supplierText      = $('#modal_supplier_id option:selected').text() || '-';
                    let supplierInvNo     = $('#modal_supplier_invoice_no').val() || '';
                    let supplierInvDate   = $('#modal_supplier_invoice_date').val() || '';
                    let terms             = $('#modal_terms').val() || '';

                    // Hidden fields
                    $currentRow.find('.field-import_print_name').val(importPrintName);
                    $currentRow.find('.field-weight_per_carton').val(weightPerCarton);
                    $currentRow.find('.field-cbm_per_carton').val(cbmPerCarton);
                    $currentRow.find('.field-quantity_per_carton').val(qtyPerCarton);
                    $currentRow.find('.field-supplier_id').val(supplierId);
                    $currentRow.find('.field-supplier_invoice_no').val(supplierInvNo);
                    $currentRow.find('.field-supplier_invoice_date').val(supplierInvDate);
                    $currentRow.find('.field-terms').val(terms);

                    // Display in row
                    $currentRow.find('.supplier-display').text(supplierId ? supplierText : '-');

                    if (importPrintName || supplierId || supplierInvNo || supplierInvDate || weightPerCarton || cbmPerCarton || qtyPerCarton || terms) {
                        $currentRow.find('.details-status')
                            .text('{{ translate('Details filled') }}')
                            .removeClass('text-muted')
                            .addClass('text-success');
                    } else {
                        $currentRow.find('.details-status')
                            .text('{{ translate('Details not filled') }}')
                            .removeClass('text-success')
                            .addClass('text-muted');
                    }

                    $('#importDetailsModal').modal('hide');
                });

            });

        })(jQuery);
    </script>
@endsection
