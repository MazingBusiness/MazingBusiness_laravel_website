@extends('backend.layouts.app')

@section('content')

<style>
    .import-cart-header {
        border-bottom: 1px solid #edf1f7;
        margin-bottom: 15px;
        padding-bottom: 8px;
    }
    .import-cart-title {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    .import-cart-subtitle {
        font-size: 13px;
        color: #6b7280;
    }
    .cart-summary-box {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        padding: 12px 14px;
        font-size: 13px;
    }
    .cart-summary-box .label {
        color: #6b7280;
    }
    .cart-summary-box .value {
        font-weight: 600;
        color: #111827;
    }
    .import-details-row {
        background: #f9fafb;
    }
    .import-details-row .title {
        font-weight: 600;
        font-size: 12px;
        color: #374151;
    }
    .import-details-row input,
    .import-details-row select {
        font-size: 12px;
    }

    /* Quantity input in CART table (bottom) – BIGGER */
    .qty-input {
        max-width: 110px;
        height: 36px;
        font-size: 13px;
    }

    /* ADD SECTION STYLES (product select row) */
    .import-row-table th,
    .import-row-table td {
        vertical-align: middle !important;
        font-size: 12px;
        white-space: nowrap;
    }
    .select2-container {
        width: 100% !important;
    }

    /* Inputs in ADD rows – a bit bigger */
    .small-input {
        font-size: 13px;
        padding: 4px 8px;
        height: 34px;
    }

    /* Supplier summary table */
    .supplier-summary-card {
        margin-bottom: 15px;
    }
    .supplier-summary-card .card-header {
        padding: 8px 15px;
    }
    .supplier-summary-card .card-body {
        padding: 10px 15px;
    }
    .supplier-summary-table th,
    .supplier-summary-table td {
        font-size: 12px;
        padding: 6px 8px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Header --}}
        <div class="import-cart-header d-flex justify-content-between align-items-center">
            <div>
                <div class="import-cart-title">
                    {{ translate('Import Cart') }}
                </div>
                <div class="import-cart-subtitle">
                    {{ translate('Bill of Lading wise temporary cart for Commercial Invoice & Packing List.') }}
                </div>
            </div>
            <div class="text-right">
                <a href="{{ route('import_bl_details.index', $bl->import_company_id) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="las la-arrow-left mr-1"></i>{{ translate('Back') }}
                </a>
            </div>
        </div>

        @php
            // ---------- SERVER-SIDE TOTALS + SUPPLIER SUMMARY ----------
            $totalQty       = 0;
            $totalValue     = 0.0;
            $sumPackages    = 0;
            $sumWeight      = 0.0;
            $sumCbm         = 0.0;

            // supplier wise: supplier_id or 'na'
            $supplierSummary = [];

            foreach ($cartItems as $row) {
                $qty   = (float) ($row->quantity ?? 0);
                $price = (float) ($row->dollar_price ?? 0);
                $line  = $qty * $price;

                $totalQty   += $qty;
                $totalValue += $line;

                $qtyPerCarton    = (float) ($row->quantity_per_carton ?? 0);
                $weightPerCarton = (float) ($row->weight_per_carton ?? 0);
                $cbmPerCarton    = (float) ($row->cbm_per_carton ?? 0);

                // packages
                if ($qty > 0 && $qtyPerCarton > 0) {
                    $rowPackages = (int) ceil($qty / $qtyPerCarton);
                } else {
                    $rowPackages = (int) ($row->total_no_of_packages ?? 0);
                }

                // weight
                if ($rowPackages > 0 && $weightPerCarton > 0) {
                    $rowWeight = $rowPackages * $weightPerCarton;
                } else {
                    $rowWeight = (float) ($row->total_weight ?? 0);
                }

                // cbm
                if ($rowPackages > 0 && $cbmPerCarton > 0) {
                    $rowCbm = $rowPackages * $cbmPerCarton;
                } else {
                    $rowCbm = (float) ($row->total_cbm ?? 0);
                }

                $sumPackages += $rowPackages;
                $sumWeight   += $rowWeight;
                $sumCbm      += $rowCbm;

                $sid   = $row->supplier_id ?: 'na';
                $sname = optional($row->supplier)->supplier_name ?? translate('Not Set');

                if (! isset($supplierSummary[$sid])) {
                    $supplierSummary[$sid] = [
                        'name'     => $sname,
                        'packages' => 0,
                        'weight'   => 0.0,
                        'cbm'      => 0.0,
                        'value'    => 0.0,
                    ];
                }

                $supplierSummary[$sid]['packages'] += $rowPackages;
                $supplierSummary[$sid]['weight']   += $rowWeight;
                $supplierSummary[$sid]['cbm']      += $rowCbm;
                $supplierSummary[$sid]['value']    += $line;
            }
        @endphp

        {{-- BL & Company Info + Totals --}}
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-2">
                        <h6 class="mb-2">
                            <i class="las la-ship text-primary mr-1"></i>
                            {{ translate('Bill of Lading') }}:
                            <span class="font-weight-semibold">{{ $bl->bl_no }}</span>
                        </h6>
                        <div class="small text-muted mb-1">
                            {{ translate('Company') }}:
                            <strong>{{ optional($bl->importCompany)->company_name }}</strong>
                        </div>
                        <div class="small text-muted">
                            {{ translate('Port of Loading') }}:
                            <strong>{{ $bl->port_of_loading ?: '-' }}</strong> |
                            {{ translate('Place of Delivery') }}:
                            <strong>{{ $bl->place_of_delivery ?: '-' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Totals block 1 --}}
            <div class="col-md-4">
                <div class="cart-summary-box">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="label">{{ translate('Total Items') }}</span>
                        <span class="value" id="total_items">{{ count($cartItems) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="label">{{ translate('Total Quantity') }}</span>
                        <span class="value" id="total_qty">{{ $totalQty }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="label">{{ translate('Total Value (USD)') }}</span>
                        <span class="value" id="total_value">{{ number_format($totalValue, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Totals block 2 --}}
            <div class="col-md-4">
                <div class="cart-summary-box">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="label">{{ translate('Total No. of Packages') }}</span>
                        <span class="value" id="total_packages">{{ $sumPackages }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="label">{{ translate('Total Weight') }}</span>
                        <span class="value" id="total_weight">{{ number_format($sumWeight, 4) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="label">{{ translate('Total CBM') }}</span>
                        <span class="value" id="total_cbm">{{ number_format($sumCbm, 4) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== SUPPLIER-WISE SUMMARY ===================== --}}
        <div class="card supplier-summary-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="las la-users mr-1"></i>
                    {{ translate('Supplier-wise Summary') }}
                </h6>
                <small class="text-muted d-none d-md-inline">
                    {{ translate('Live supplier-wise totals of packages, weight, CBM & value based on cart items and new rows.') }}
                </small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered supplier-summary-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Supplier') }}</th>
                                <th class="text-right">{{ translate('No. of Packages') }}</th>
                                <th class="text-right">{{ translate('Total Weight') }}</th>
                                <th class="text-right">{{ translate('Total CBM') }}</th>
                                <th class="text-right">{{ translate('Total Value (USD)') }}</th>
                            </tr>
                        </thead>
                        <tbody id="supplier_summary_body">
                            {{-- Server-side initial render --}}
                            @if(empty($supplierSummary))
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        {{ translate('No supplier-wise data yet. Add items or fill supplier details.') }}
                                    </td>
                                </tr>
                            @else
                                @foreach($supplierSummary as $summary)
                                    <tr>
                                        <td>{{ $summary['name'] }}</td>
                                        <td class="text-right">{{ $summary['packages'] }}</td>
                                        <td class="text-right">{{ number_format($summary['weight'], 4) }}</td>
                                        <td class="text-right">{{ number_format($summary['cbm'], 4) }}</td>
                                        <td class="text-right">{{ number_format($summary['value'], 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===================== ADD PRODUCTS (SAME PAGE) ===================== --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="las la-plus-circle mr-1"></i>
                    {{ translate('Add Products to this BL Cart') }}
                </h6>
                <small class="text-muted d-none d-md-inline">
                    {{ translate('Search product by Part No / Name, enter quantity & dollar price, fill import details and add directly into this cart.') }}
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
                                {{-- Default first row --}}
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

                                        {{-- Hidden fields (per row) --}}
                                        <input type="hidden" name="import_print_name[]"   class="field-import_print_name">
                                        <input type="hidden" name="weight_per_carton[]"   class="field-weight_per_carton">
                                        <input type="hidden" name="cbm_per_carton[]"      class="field-cbm_per_carton">
                                        <input type="hidden" name="quantity_per_carton[]" class="field-quantity_per_carton">
                                        <input type="hidden" name="supplier_id[]"         class="field-supplier_id">
                                        <input type="hidden" name="supplier_invoice_no[]" class="field-supplier_invoice_no">
                                        <input type="hidden" name="supplier_invoice_date[]" class="field-supplier_invoice_date">
                                        <input type="hidden" name="terms[]"              class="field-terms">
                                        <input type="hidden" name="total_no_of_packages[]" class="field-total_no_of_packages">
                                        <input type="hidden" name="total_weight[]"         class="field-total_weight">
                                        <input type="hidden" name="total_cbm[]"            class="field-total_cbm">
                                        {{-- NEW: import image id --}}
                                        <input type="hidden" name="import_photo_id[]"      class="field-import_photo_id">
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
                                class="btn btn-primary btn-sm"
                                id="add_to_cart_btn">
                            <i class="las la-cart-plus mr-1"></i>
                            {{ translate('Add to Cart (Stay on Page)') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Hidden row template for ADD section --}}
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

                    <input type="hidden" name="import_print_name[]"   class="field-import_print_name">
                    <input type="hidden" name="weight_per_carton[]"   class="field-weight_per_carton">
                    <input type="hidden" name="cbm_per_carton[]"      class="field-cbm_per_carton">
                    <input type="hidden" name="quantity_per_carton[]" class="field-quantity_per_carton">
                    <input type="hidden" name="supplier_id[]"         class="field-supplier_id">
                    <input type="hidden" name="supplier_invoice_no[]" class="field-supplier_invoice_no">
                    <input type="hidden" name="supplier_invoice_date[]" class="field-supplier_invoice_date">
                    <input type="hidden" name="terms[]"              class="field-terms">
                    <input type="hidden" name="total_no_of_packages[]" class="field-total_no_of_packages">
                    <input type="hidden" name="total_weight[]"         class="field-total_weight">
                    <input type="hidden" name="total_cbm[]"            class="field-total_cbm">
                    {{-- NEW: import image id --}}
                    <input type="hidden" name="import_photo_id[]"      class="field-import_photo_id">
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

        {{-- IMPORT DETAILS MODAL (shared for add section rows) --}}
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

                            {{-- Terms --}}
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ translate('Supplier Invoice No') }}</label>
                                    <input type="text" id="modal_supplier_invoice_no" class="form-control form-control-sm">
                                </div>
                            </div>

                            {{-- Supplier Invoice Date --}}
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ translate('Supplier Invoice Date') }}</label>
                                    <input type="date" id="modal_supplier_invoice_date" class="form-control form-control-sm">
                                </div>
                            </div>

                            {{-- Auto Calculated Totals --}}
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ translate('Total No. of Packages') }}</label>
                                    <input type="number" id="modal_total_no_of_packages" class="form-control form-control-sm" readonly>
                                    <small class="text-muted d-block">
                                        {{ translate('Calculated as ceil(Quantity / Qty per Carton)') }}
                                    </small>
                                </div>
                                <div class="form-group mb-1">
                                    <label>{{ translate('Total Weight') }}</label>
                                    <input type="number" step="0.0001" id="modal_total_weight" class="form-control form-control-sm" readonly>
                                </div>
                                <div class="form-group mb-0">
                                    <label>{{ translate('Total CBM') }}</label>
                                    <input type="number" step="0.0001" id="modal_total_cbm" class="form-control form-control-sm" readonly>
                                </div>
                            </div>
                        </div>

                        {{-- NEW: Import Image uploader --}}
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-group mb-1">
                                    <label>{{ translate('Import Image') }}</label>
                                    <div class="input-group" data-toggle="aizuploader" data-type="image">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                {{ translate('Browse') }}
                                            </div>
                                        </div>
                                        <div class="form-control file-amount">
                                            {{ translate('Choose File') }}
                                        </div>
                                        {{-- IMPORTANT: this hidden will receive upload id; we copy it to row --}}
                                        <input type="hidden"
                                               id="modal_import_photo_id"
                                               class="selected-files">
                                    </div>
                                    <div class="file-preview box sm" id="modal_import_photo_preview_box"></div>
                                    <small class="text-muted">
                                        {{ translate('This image will be saved as import photo for this cart item.') }}
                                    </small>
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

        {{-- ===================== CART TABLE (UPDATE EXISTING) ===================== --}}
        <div class="card" id="cart-section">
            <form action="{{ route('import.cart.update') }}" method="POST">
                @csrf

                <input type="hidden" name="bl_id" value="{{ $bl->id }}">
                <input type="hidden" name="import_company_id" value="{{ $bl->import_company_id }}">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="las la-shopping-cart mr-1"></i>
                        {{ translate('Cart Items') }}
                    </h6>

                    @if(count($cartItems) > 0)
                        <div>
                            {{-- Clear cart --}}
                            <button type="submit"
                                    formaction="{{ route('import.cart.clear') }}"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('{{ translate('Clear all items from this cart?') }}');">
                                <i class="las la-trash-alt mr-1"></i>{{ translate('Clear Cart') }}
                            </button>
                        </div>
                    @endif
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 aiz-table">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <label class="aiz-checkbox mb-0">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </th>
                                    <th>{{ translate('Item') }}</th>
                                    <th width="120" class="text-right">{{ translate('Unit Price (USD)') }}</th>
                                    <th width="140" class="text-center">{{ translate('Quantity') }}</th>
                                    <th width="140" class="text-right">{{ translate('Line Total (USD)') }}</th>
                                    <th width="80" class="text-center">{{ translate('Update') }}</th>
                                    <th width="80" class="text-center">{{ translate('Remove') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cartItems as $row)
                                    @php
                                        $product = $row->product ?? null;

                                        $thumbUrl = $product && $product->thumbnail_img
                                            ? uploaded_asset($product->thumbnail_img)
                                            : static_asset('assets/img/placeholder.jpg');

                                        $lineTotal = (float)$row->quantity * (float)$row->dollar_price;

                                        $partNo = $row->part_no ?? ($product->part_no ?? '-');
                                    @endphp

                                    {{-- MAIN ROW --}}
                                    <tr class="cart-item-row" data-cart-id="{{ $row->id }}">
                                        {{-- Bulk select --}}
                                        <td>
                                            <label class="aiz-checkbox mb-0">
                                                <input type="checkbox"
                                                       class="check-one"
                                                       name="cart_id[]"
                                                       value="{{ $row->id }}">
                                                <span class="aiz-square-check"></span>
                                            </label>
                                        </td>

                                        {{-- Item --}}
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 mr-2">
                                                    <img src="{{ $thumbUrl }}"
                                                         class="size-50px img-fit rounded"
                                                         alt="img">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="font-weight-600">
                                                        {{ $row->item_name ?? ($product->name ?? '-') }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ translate('Part No') }}: {{ $partNo }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Unit Price --}}
                                        <td class="text-right">
                                            <input type="number"
                                                   step="0.0001"
                                                   name="dollar_price[{{ $row->id }}]"
                                                   value="{{ $row->dollar_price }}"
                                                   class="form-control form-control-sm text-right unit-price-input">
                                        </td>

                                        {{-- Quantity --}}
                                        <td class="text-center">
                                            <div class="input-group input-group-sm justify-content-center">
                                                <div class="input-group-prepend">
                                                    <button class="btn btn-outline-secondary btn-qty-minus" type="button">-</button>
                                                </div>
                                                <input type="number"
                                                       min="1"
                                                       class="form-control text-center qty-input"
                                                       name="qty[{{ $row->id }}]"
                                                       value="{{ $row->quantity }}">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary btn-qty-plus" type="button">+</button>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Line Total --}}
                                        <td class="text-right">
                                            <span class="line-total">
                                                {{ number_format($lineTotal, 2) }}
                                            </span>
                                        </td>

                                        {{-- Update this row only --}}
                                        <td class="text-center">
                                            <button  type="button"
                                                    class="btn btn-icon btn-sm btn-soft-primary btn-update-row"
                                                    data-update-url="{{ route('import.cart.update_row', $row->id) }}"
                                                    data-cart-id="{{ $row->id }}"
                                                    title="{{ translate('Update this row only') }}">
                                                <i class="las la-save"></i>
                                            </button>
                                        </td>

                                        {{-- Remove --}}
                                        <td class="text-center">
                                            <button type="submit"
                                                    formaction="{{ route('import.cart.remove') }}"
                                                    name="cart_id"
                                                    value="{{ $row->id }}"
                                                    class="btn btn-icon btn-sm btn-soft-danger"
                                                    onclick="return confirm('{{ translate('Remove this item from cart?') }}');">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    {{-- IMPORT DETAILS EDIT ROW --}}
                                    <tr class="import-details-row" data-cart-id="{{ $row->id }}">
                                        <td></td>
                                        <td colspan="6">
                                            <div class="row">
                                                {{-- Import Print Name --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Import Print Name') }}</div>
                                                    <input type="text"
                                                           class="form-control form-control-sm"
                                                           name="import_print_name[{{ $row->id }}]"
                                                           value="{{ $row->import_print_name }}">
                                                </div>

                                                {{-- Weight / Carton --}}
                                                <div class="col-md-2 col-6 mb-2">
                                                    <div class="title">{{ translate('Weight / Carton') }}</div>
                                                    <input type="number"
                                                           step="0.0001"
                                                           class="form-control form-control-sm weight-per-carton-input"
                                                           name="weight_per_carton[{{ $row->id }}]"
                                                           value="{{ $row->weight_per_carton }}">
                                                </div>

                                                {{-- CBM / Carton --}}
                                                <div class="col-md-2 col-6 mb-2">
                                                    <div class="title">{{ translate('CBM / Carton') }}</div>
                                                    <input type="number"
                                                           step="0.0001"
                                                           class="form-control form-control-sm cbm-per-carton-input"
                                                           name="cbm_per_carton[{{ $row->id }}]"
                                                           value="{{ $row->cbm_per_carton }}">
                                                </div>

                                                {{-- Qty / Carton --}}
                                                <div class="col-md-2 col-6 mb-2">
                                                    <div class="title">{{ translate('Qty / Carton') }}</div>
                                                    <input type="number"
                                                           step="0.0001"
                                                           class="form-control form-control-sm qty-per-carton-input"
                                                           name="quantity_per_carton[{{ $row->id }}]"
                                                           value="{{ $row->quantity_per_carton }}">
                                                </div>

                                                {{-- Supplier --}}
                                                <div class="col-md-3 col-12 mb-2">
                                                    <div class="title">{{ translate('Supplier') }}</div>
                                                    <select name="supplier_id[{{ $row->id }}]"
                                                            class="form-control form-control-sm">
                                                        <option value="">{{ translate('Select') }}</option>
                                                        @foreach($suppliers as $supplier)
                                                            <option value="{{ $supplier->id }}"
                                                                @if($row->supplier_id == $supplier->id) selected @endif>
                                                                {{ $supplier->supplier_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row">
                                                {{-- Supplier Invoice No --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Supplier Invoice No') }}</div>
                                                    <input type="text"
                                                           class="form-control form-control-sm"
                                                           name="supplier_invoice_no[{{ $row->id }}]"
                                                           value="{{ $row->supplier_invoice_no }}">
                                                </div>

                                                {{-- Supplier Invoice Date --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Supplier Invoice Date') }}</div>
                                                    <input type="date"
                                                           class="form-control form-control-sm"
                                                           name="supplier_invoice_date[{{ $row->id }}]"
                                                           value="{{ $row->supplier_invoice_date ? \Carbon\Carbon::parse($row->supplier_invoice_date)->format('Y-m-d') : '' }}">
                                                </div>

                                                {{-- Terms --}}
                                                <div class="col-md-6 col-12 mb-2">
                                                    <div class="title">{{ translate('Terms') }}</div>
                                                    <input type="text"
                                                           class="form-control form-control-sm"
                                                           name="terms[{{ $row->id }}]"
                                                           value="{{ $row->terms }}">
                                                </div>
                                            </div>

                                            <div class="row">
                                                {{-- Total No. of Packages --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Total No. of Packages') }}</div>
                                                    <input type="number"
                                                           class="form-control form-control-sm total-packages-input"
                                                           name="total_no_of_packages[{{ $row->id }}]"
                                                           value="{{ $row->total_no_of_packages }}"
                                                           readonly>
                                                    <small class="text-muted">
                                                        {{ translate('ceil(Qty / Qty per Carton)') }}
                                                    </small>
                                                </div>

                                                {{-- Total Weight --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Total Weight') }}</div>
                                                    <input type="number"
                                                           step="0.0001"
                                                           class="form-control form-control-sm total-weight-input"
                                                           name="total_weight[{{ $row->id }}]"
                                                           value="{{ $row->total_weight }}"
                                                           readonly>
                                                </div>

                                                {{-- Total CBM --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Total CBM') }}</div>
                                                    <input type="number"
                                                           step="0.0001"
                                                           class="form-control form-control-sm total-cbm-input"
                                                           name="total_cbm[{{ $row->id }}]"
                                                           value="{{ $row->total_cbm }}"
                                                           readonly>
                                                </div>

                                                {{-- Import Image in existing rows (optional view) --}}
                                                <div class="col-md-3 col-6 mb-2">
                                                    <div class="title">{{ translate('Import Image') }}</div>
                                                    @php
                                                        $importPhotoUrl = $row->import_photo_id
                                                            ? uploaded_asset($row->import_photo_id)
                                                            : null;
                                                    @endphp
                                                    @if($importPhotoUrl)
                                                        <img src="{{ $importPhotoUrl }}"
                                                             class="img-fit size-60px rounded border"
                                                             alt="import photo">
                                                    @else
                                                        <span class="text-muted small">
                                                            {{ translate('No image') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="las la-info-circle mr-1"></i>
                                            {{ translate('No items in this cart yet. Use the Add Products section above to add items.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(count($cartItems) > 0)
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        {{-- BULK UPDATE CART --}}
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="las la-sync mr-1"></i>{{ translate('Update Cart') }}
                        </button>

                        <button type="submit"
                            formaction="{{ route('import.cart.proceed') }}"
                            class="btn btn-sm btn-success">
                            <i class="las la-arrow-right mr-1"></i>{{ translate('Proceed') }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
    {{-- Select2 for product search --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        (function($){
            "use strict";

            // ====== ADD PRODUCTS SECTION (top) ======
            let rowTemplateHtml = '';
            let $currentRow = null;

            // Default TERMS from BL port_of_loading
            let defaultTermsFromBL = @json($bl->port_of_loading);
            let placeholderImg     = '{{ static_asset('assets/img/placeholder.jpg') }}';
            let notSetLabel        = '{{ translate('Not Set') }}';

            // ---------- MAIN TOTALS + SUPPLIER SUMMARY ----------
            function recalcTotals() {
                var totalQty        = 0;
                var totalValue      = 0;
                var totalPackages   = 0;
                var totalWeight     = 0;
                var totalCbm        = 0;

                // Supplier-wise aggregation
                // key = supplierId || '__na__'
                var supplierAgg = {};
                function addSupplierAgg(supplierId, supplierName, pkg, wt, cbm, val) {
                    if (!supplierId) {
                        supplierId = '__na__';
                    }
                    if (!supplierName || supplierName.trim() === '') {
                        supplierName = notSetLabel;
                    }
                    if (!supplierAgg[supplierId]) {
                        supplierAgg[supplierId] = {
                            name: supplierName,
                            packages: 0,
                            weight: 0,
                            cbm: 0,
                            value: 0
                        };
                    }
                    supplierAgg[supplierId].packages += pkg;
                    supplierAgg[supplierId].weight   += wt;
                    supplierAgg[supplierId].cbm      += cbm;
                    supplierAgg[supplierId].value    += val;
                }

                // 1) EXISTING CART ROWS (bottom table)
                $('.cart-item-row').each(function(){
                    var $row        = $(this);
                    var cartId      = $row.data('cart-id');
                    var $detailsRow = $('.import-details-row[data-cart-id="' + cartId + '"]');

                    var qty   = parseFloat($row.find('.qty-input').val()) || 0;
                    var price = parseFloat($row.find('.unit-price-input').val()) || 0;
                    var line  = qty * price;

                    // Line total
                    $row.find('.line-total').text(line.toFixed(2));

                    totalQty   += qty;
                    totalValue += line;

                    var rowTotalPackages = 0;
                    var rowTotalWeight   = 0;
                    var rowTotalCbm      = 0;

                    // ----- PACKAGES / WEIGHT / CBM -----
                    if ($detailsRow.length) {
                        var qtyPerCarton   = parseFloat($detailsRow.find('.qty-per-carton-input').val()) || 0;
                        var wtPerCarton    = parseFloat($detailsRow.find('.weight-per-carton-input').val()) || 0;
                        var cbmPerCarton   = parseFloat($detailsRow.find('.cbm-per-carton-input').val()) || 0;

                        var existingPackages = parseFloat($detailsRow.find('.total-packages-input').val()) || 0;
                        var existingWeight   = parseFloat($detailsRow.find('.total-weight-input').val())   || 0;
                        var existingCbm      = parseFloat($detailsRow.find('.total-cbm-input').val())      || 0;

                        if (qty > 0 && qtyPerCarton > 0) {
                            // ✅ Recalculate using current qty & carton settings (always ceil)
                            rowTotalPackages = Math.ceil(qty / qtyPerCarton);
                            $detailsRow.find('.total-packages-input').val(rowTotalPackages);

                            if (wtPerCarton > 0) {
                                rowTotalWeight = wtPerCarton * rowTotalPackages;
                                $detailsRow.find('.total-weight-input').val(rowTotalWeight.toFixed(4));
                            } else if (existingWeight > 0) {
                                rowTotalWeight = existingWeight;
                                $detailsRow.find('.total-weight-input').val(existingWeight.toFixed(4));
                            } else {
                                rowTotalWeight = 0;
                                $detailsRow.find('.total-weight-input').val('');
                            }

                            if (cbmPerCarton > 0) {
                                rowTotalCbm = cbmPerCarton * rowTotalPackages;
                                $detailsRow.find('.total-cbm-input').val(rowTotalCbm.toFixed(4));
                            } else if (existingCbm > 0) {
                                rowTotalCbm = existingCbm;
                                $detailsRow.find('.total-cbm-input').val(existingCbm.toFixed(4));
                            } else {
                                rowTotalCbm = 0;
                                $detailsRow.find('.total-cbm-input').val('');
                            }
                        } else {
                            // ❗ keep existing values instead of clearing
                            if (existingPackages > 0) {
                                rowTotalPackages = existingPackages;
                                $detailsRow.find('.total-packages-input').val(existingPackages);
                            } else {
                                rowTotalPackages = 0;
                                $detailsRow.find('.total-packages-input').val('');
                            }

                            if (existingWeight > 0) {
                                rowTotalWeight = existingWeight;
                                $detailsRow.find('.total-weight-input').val(existingWeight.toFixed(4));
                            } else {
                                rowTotalWeight = 0;
                                $detailsRow.find('.total-weight-input').val('');
                            }

                            if (existingCbm > 0) {
                                rowTotalCbm = existingCbm;
                                $detailsRow.find('.total-cbm-input').val(existingCbm.toFixed(4));
                            } else {
                                rowTotalCbm = 0;
                                $detailsRow.find('.total-cbm-input').val('');
                            }
                        }

                        totalPackages += rowTotalPackages;
                        totalWeight   += rowTotalWeight;
                        totalCbm      += rowTotalCbm;

                        // Supplier info for this existing row
                        var $supplierSelect = $detailsRow.find('select[name^="supplier_id"]');
                        var supplierId   = $supplierSelect.val() || '';
                        var supplierName = $supplierSelect.find('option:selected').text() || notSetLabel;

                        addSupplierAgg(
                            supplierId,
                            supplierName,
                            rowTotalPackages,
                            rowTotalWeight,
                            rowTotalCbm,
                            line
                        );
                    }
                });

                // 2) ADD PRODUCTS ROWS (top table) – live preview from hidden fields
                $('#import_products_table tbody tr.import-row').each(function () {
                    var $row = $(this);

                    var qty   = parseFloat($row.find('input[name="quantity[]"]').val()) || 0;
                    var price = parseFloat($row.find('input[name="dollar_price[]"]').val()) || 0;
                    var line  = qty * price;

                    totalQty   += qty;
                    totalValue += line;

                    var addPackages = parseFloat($row.find('.field-total_no_of_packages').val()) || 0;
                    var addWeight   = parseFloat($row.find('.field-total_weight').val()) || 0;
                    var addCbm      = parseFloat($row.find('.field-total_cbm').val()) || 0;

                    totalPackages += addPackages;
                    totalWeight   += addWeight;
                    totalCbm      += addCbm;

                    // Supplier info from ADD row
                    var supplierId   = $row.find('.field-supplier_id').val() || '';
                    var supplierName = $row.find('.supplier-display').text() || notSetLabel;

                    if (supplierId || addPackages || addWeight || addCbm || line) {
                        addSupplierAgg(
                            supplierId,
                            supplierName,
                            addPackages,
                            addWeight,
                            addCbm,
                            line
                        );
                    }
                });

                // 3) TOTAL ITEMS = saved cart rows + add-rows with a product selected
                var cartItemCount = $('.cart-item-row').length;
                var addItemCount = $('#import_products_table tbody tr.import-row').filter(function(){
                    return $(this).find('.product-select').val();
                }).length;

                $('#total_items').text(cartItemCount + addItemCount);

                // 4) Header totals
                $('#total_qty').text(totalQty);
                $('#total_value').text(totalValue.toFixed(2));

                $('#total_packages').text(totalPackages);
                $('#total_weight').text(totalWeight.toFixed(4));
                $('#total_cbm').text(totalCbm.toFixed(4));

                // 5) Render supplier-wise table
                var $tbody = $('#supplier_summary_body');
                $tbody.empty();

                var hasData = false;
                $.each(supplierAgg, function(key, data){
                    if (!data) return;
                    hasData = true;
                    var rowHtml =
                        '<tr>' +
                        '<td>' + $('<div>').text(data.name).html() + '</td>' +
                        '<td class="text-right">' + data.packages + '</td>' +
                        '<td class="text-right">' + data.weight.toFixed(4) + '</td>' +
                        '<td class="text-right">' + data.cbm.toFixed(4) + '</td>' +
                        '<td class="text-right">' + data.value.toFixed(2) + '</td>' +
                        '</tr>';
                    $tbody.append(rowHtml);
                });

                if (!hasData) {
                    $tbody.append(
                        '<tr>' +
                        '<td colspan="5" class="text-center text-muted">' +
                        '{{ translate('No supplier-wise data yet. Add items or fill supplier details.') }}' +
                        '</td>' +
                        '</tr>'
                    );
                }
            }

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

            // For ADD modal (top section)
            function recalcModalTotals() {
                if (!$currentRow || $currentRow.length === 0) {
                    $('#modal_total_no_of_packages').val('');
                    $('#modal_total_weight').val('');
                    $('#modal_total_cbm').val('');
                    return;
                }

                var qty            = parseFloat($currentRow.find('input[name="quantity[]"]').val()) || 0;
                var qtyPerCarton   = parseFloat($('#modal_quantity_per_carton').val()) || 0;
                var weightPerCarton= parseFloat($('#modal_weight_per_carton').val()) || 0;
                var cbmPerCarton   = parseFloat($('#modal_cbm_per_carton').val()) || 0;

                var totalPackages = '';
                var totalWeight   = '';
                var totalCbm      = '';

                if (qty > 0 && qtyPerCarton > 0) {
                    // ✅ Always round UP
                    totalPackages = Math.ceil(qty / qtyPerCarton);

                    if (weightPerCarton > 0) {
                        totalWeight = weightPerCarton * totalPackages;
                    }
                    if (cbmPerCarton > 0) {
                        totalCbm = cbmPerCarton * totalPackages;
                    }
                }

                if (totalPackages !== '' && !isNaN(totalPackages)) {
                    $('#modal_total_no_of_packages').val(totalPackages);
                } else {
                    $('#modal_total_no_of_packages').val('');
                }

                if (totalWeight !== '' && !isNaN(totalWeight)) {
                    $('#modal_total_weight').val(totalWeight.toFixed(4));
                } else {
                    $('#modal_total_weight').val('');
                }

                if (totalCbm !== '' && !isNaN(totalCbm)) {
                    $('#modal_total_cbm').val(totalCbm.toFixed(4));
                } else {
                    $('#modal_total_cbm').val('');
                }
            }

            $(document).ready(function(){
                // -------- ADD SECTION INIT --------
                rowTemplateHtml = $('#import-row-template').html();

                let $firstRow = $('#import_products_table tbody tr.import-row:first');
                initProductSelect($firstRow.find('.product-select'));

                $('#modal_supplier_id').selectpicker('refresh');

                // Add new row
                $('#add_row_btn').on('click', function (e) {
                    e.preventDefault();

                    let $tbody = $('#import_products_table tbody');
                    let $newRow = $(rowTemplateHtml);

                    $newRow.find('input').val('');
                    $newRow.find('input[name="quantity[]"]').val(1);

                    $tbody.append($newRow);
                    initProductSelect($newRow.find('.product-select'));

                    recalcTotals();
                });

                // Remove row in ADD section
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

                        $row.find('.field-import_print_name').val('');
                        $row.find('.field-weight_per_carton').val('');
                        $row.find('.field-cbm_per_carton').val('');
                        $row.find('.field-quantity_per_carton').val('');
                        $row.find('.field-supplier_id').val('');
                        $row.find('.field-supplier_invoice_no').val('');
                        $row.find('.field-supplier_invoice_date').val('');
                        $row.find('.field-terms').val('');
                        $row.find('.field-total_no_of_packages').val('');
                        $row.find('.field-total_weight').val('');
                        $row.find('.field-total_cbm').val('');
                        $row.find('.field-import_photo_id').val('');
                        $row.removeAttr('data-import-photo-url');
                    } else {
                        let $productSelect = $row.find('.product-select');
                        if ($productSelect.data('select2')) {
                            $productSelect.select2('destroy');
                        }
                        $row.remove();
                    }

                    recalcTotals();
                });

                // Product select → auto-fill hidden fields for ADD rows
                $(document).on('select2:select', '.product-select', function (e) {
                    let data = e.params.data || {};
                    let $row = $(this).closest('tr.import-row');
                    if (!$row.length) return;

                    // Dollar price
                    if (typeof data.dollar_price !== 'undefined' && data.dollar_price !== null) {
                        $row.find('input[name="dollar_price[]"]').val(data.dollar_price);
                    }

                    $row.find('.field-import_print_name').val(data.import_print_name || '');
                    $row.find('.field-weight_per_carton').val(
                        typeof data.weight_per_carton !== 'undefined' && data.weight_per_carton !== null
                            ? data.weight_per_carton : ''
                    );
                    $row.find('.field-cbm_per_carton').val(
                        typeof data.cbm_per_carton !== 'undefined' && data.cbm_per_carton !== null
                            ? data.cbm_per_carton : ''
                    );
                    $row.find('.field-quantity_per_carton').val(
                        typeof data.quantity_per_carton !== 'undefined' && data.quantity_per_carton !== null
                            ? data.quantity_per_carton : ''
                    );

                    // supplier from master
                    if (typeof data.supplier_id !== 'undefined' && data.supplier_id) {
                        $row.find('.field-supplier_id').val(data.supplier_id);

                        if (typeof data.supplier_name !== 'undefined' && data.supplier_name) {
                            $row.find('.supplier-display').text(data.supplier_name);
                        }
                    } else {
                        $row.find('.field-supplier_id').val('');
                        $row.find('.supplier-display').text('-');
                    }

                    // NEW: import image from master
                    if (typeof data.import_photo_id !== 'undefined' && data.import_photo_id) {
                        $row.find('.field-import_photo_id').val(data.import_photo_id);
                        if (data.import_photo_url) {
                            $row.attr('data-import-photo-url', data.import_photo_url);
                        } else {
                            $row.removeAttr('data-import-photo-url');
                        }
                    } else {
                        $row.find('.field-import_photo_id').val('');
                        $row.removeAttr('data-import-photo-url');
                    }

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

                    recalcTotals();
                });

                // Open import details modal for ADD rows
                $(document).on('click', '.btn-edit-details', function () {
                    $currentRow = $(this).closest('tr.import-row');
                    if (!$currentRow || $currentRow.length === 0) return;

                    $('#modal_import_print_name').val($currentRow.find('.field-import_print_name').val());
                    $('#modal_weight_per_carton').val($currentRow.find('.field-weight_per_carton').val());
                    $('#modal_cbm_per_carton').val($currentRow.find('.field-cbm_per_carton').val());
                    $('#modal_quantity_per_carton').val($currentRow.find('.field-quantity_per_carton').val());
                    $('#modal_supplier_invoice_no').val($currentRow.find('.field-supplier_invoice_no').val());
                    $('#modal_supplier_invoice_date').val($currentRow.find('.field-supplier_invoice_date').val());

                    let existingTerms = $currentRow.find('.field-terms').val();
                    if (existingTerms && existingTerms.length > 0) {
                        $('#modal_terms').val(existingTerms);
                    } else {
                        $('#modal_terms').val(defaultTermsFromBL || '');
                    }

                    let supplierId = $currentRow.find('.field-supplier_id').val() || '';
                    $('#modal_supplier_id').val(supplierId);
                    $('#modal_supplier_id').selectpicker('refresh');

                    // Load existing totals (if any)
                    $('#modal_total_no_of_packages').val($currentRow.find('.field-total_no_of_packages').val());
                    $('#modal_total_weight').val($currentRow.find('.field-total_weight').val());
                    $('#modal_total_cbm').val($currentRow.find('.field-total_cbm').val());

                    // Load import image
                    let importPhotoId = $currentRow.find('.field-import_photo_id').val() || '';
                    $('#modal_import_photo_id').val(importPhotoId);

                    let $previewBox = $('#modal_import_photo_preview_box');
                    $previewBox.html('');
                    let imgUrl = $currentRow.attr('data-import-photo-url') || '';
                    if (imgUrl) {
                        $previewBox.html(
                            '<img src="' + imgUrl + '" class="img-fit size-80px rounded border" alt="import image">'
                        );
                    }

                    recalcModalTotals();

                    $('#importDetailsModal').modal('show');
                });

                // Recalculate modal totals when carton fields / qty change
                $('#modal_quantity_per_carton, #modal_weight_per_carton, #modal_cbm_per_carton').on('input change', function(){
                    recalcModalTotals();
                    recalcTotals();
                });

                // Also whenever qty in that row changes while modal open
                $(document).on('input change', 'input[name="quantity[]"]', function(){
                    if ($currentRow && $currentRow.length && $.contains($currentRow[0], this)) {
                        recalcModalTotals();
                    }
                    recalcTotals();
                });

                // Save modal back to ADD row
                $('#save_import_details_btn').on('click', function () {
                    if (!$currentRow || $currentRow.length === 0) return;

                    let importPrintName   = $('#modal_import_print_name').val() || '';
                    let weightPerCarton   = $('#modal_weight_per_carton').val() || '';
                    let cbmPerCarton      = $('#modal_cbm_per_carton').val() || '';
                    let qtyPerCarton      = $('#modal_quantity_per_carton').val() || '';
                    let supplierId        = $('#modal_supplier_id').val() || '';
                    let supplierText      = $('#modal_supplier_id option:selected').text() || '-';
                    let supplierInvNo     = $('#modal_supplier_invoice_no').val() || '';
                    let supplierInvDate   = $('#modal_supplier_invoice_date').val() || '';
                    let terms             = $('#modal_terms').val() || '';
                    let totalPackages     = $('#modal_total_no_of_packages').val() || '';
                    let totalWeight       = $('#modal_total_weight').val() || '';
                    let totalCbm          = $('#modal_total_cbm').val() || '';
                    let importPhotoId     = $('#modal_import_photo_id').val() || '';

                    $currentRow.find('.field-import_print_name').val(importPrintName);
                    $currentRow.find('.field-weight_per_carton').val(weightPerCarton);
                    $currentRow.find('.field-cbm_per_carton').val(cbmPerCarton);
                    $currentRow.find('.field-quantity_per_carton').val(qtyPerCarton);
                    $currentRow.find('.field-supplier_id').val(supplierId);
                    $currentRow.find('.field-supplier_invoice_no').val(supplierInvNo);
                    $currentRow.find('.field-supplier_invoice_date').val(supplierInvDate);
                    $currentRow.find('.field-terms').val(terms);
                    $currentRow.find('.field-total_no_of_packages').val(totalPackages);
                    $currentRow.find('.field-total_weight').val(totalWeight);
                    $currentRow.find('.field-total_cbm').val(totalCbm);
                    $currentRow.find('.field-import_photo_id').val(importPhotoId);

                    $currentRow.find('.supplier-display').text(supplierId ? supplierText : '-');

                    // Update row-level stored image URL from preview (if any)
                    let $modalImg = $('#modal_import_photo_preview_box').find('img').first();
                    if ($modalImg.length) {
                        $currentRow.attr('data-import-photo-url', $modalImg.attr('src'));
                    } else {
                        if (importPhotoId) {
                            if (!$currentRow.attr('data-import-photo-url')) {
                                $currentRow.attr('data-import-photo-url', '');
                            }
                        } else {
                            $currentRow.removeAttr('data-import-photo-url');
                        }
                    }

                    if (importPrintName || supplierId || supplierInvNo || supplierInvDate || weightPerCarton || cbmPerCarton || qtyPerCarton || terms || importPhotoId) {
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

                    recalcTotals();
                });

                // IMPORTANT CHANGE:
                // ❌ Pehle yaha recalcTotals() call ho raha tha, jo refresh ke baad
                //    server ke PHP totals ko 0 se overwrite kar sakta tha.
                //
                // isko hata diya gaya hai taa ki initial page load par
                // header totals sirf PHP wale hi rahein.
                //
                // // -------- CART TOTALS INIT (existing rows) --------
                // recalcTotals();
            });

            // Check all
            $(document).on('change', '.check-all', function(){
                $('.check-one').prop('checked', this.checked);
            });

            // Qty +/-
            $(document).on('click', '.btn-qty-plus', function(){
                var $input = $(this).closest('.input-group').find('.qty-input');
                var val = parseInt($input.val() || 1);
                $input.val(val + 1);
                recalcTotals();
            });

            $(document).on('click', '.btn-qty-minus', function(){
                var $input = $(this).closest('.input-group').find('.qty-input');
                var val = parseInt($input.val() || 1);
                if (val > 1) {
                    $input.val(val - 1);
                }
                recalcTotals();
            });

            // Manual qty / price change (cart section)
            $(document).on('input change', '.qty-input', function(){
                recalcTotals();
            });
            $(document).on('input change', '.unit-price-input', function(){
                recalcTotals();
            });

            // When carton fields change for existing rows → recalc per-row totals
            $(document).on('input change', '.qty-per-carton-input, .weight-per-carton-input, .cbm-per-carton-input', function(){
                recalcTotals();
            });

            // ====== AJAX individual row update ======
            $(document).on('click', '.btn-update-row', function (e) {
                e.preventDefault();

                var $btn    = $(this);
                var cartId  = $btn.data('cart-id');
                var url     = $btn.data('update-url');

                var qty                 = $('input[name="qty[' + cartId + ']"]').val();
                var price               = $('input[name="dollar_price[' + cartId + ']"]').val();
                var importPrintName     = $('input[name="import_print_name[' + cartId + ']"]').val();
                var weightPerCarton     = $('input[name="weight_per_carton[' + cartId + ']"]').val();
                var cbmPerCarton        = $('input[name="cbm_per_carton[' + cartId + ']"]').val();
                var qtyPerCarton        = $('input[name="quantity_per_carton[' + cartId + ']"]').val();
                var supplierId          = $('select[name="supplier_id[' + cartId + ']"]').val();
                var supplierInvoiceNo   = $('input[name="supplier_invoice_no[' + cartId + ']"]').val();
                var supplierInvoiceDate = $('input[name="supplier_invoice_date[' + cartId + ']"]').val();
                var terms               = $('input[name="terms[' + cartId + ']"]').val();
                var totalPackages       = $('input[name="total_no_of_packages[' + cartId + ']"]').val();
                var totalWeight         = $('input[name="total_weight[' + cartId + ']"]').val();
                var totalCbm            = $('input[name="total_cbm[' + cartId + ']"]').val();

                var payload = {
                    _token: '{{ csrf_token() }}',
                    bl_id: '{{ $bl->id }}',
                };

                payload['qty[' + cartId + ']']                    = qty;
                payload['dollar_price[' + cartId + ']']           = price;
                payload['import_print_name[' + cartId + ']']      = importPrintName;
                payload['weight_per_carton[' + cartId + ']']      = weightPerCarton;
                payload['cbm_per_carton[' + cartId + ']']         = cbmPerCarton;
                payload['quantity_per_carton[' + cartId + ']']    = qtyPerCarton;
                payload['supplier_id[' + cartId + ']']            = supplierId;
                payload['supplier_invoice_no[' + cartId + ']']    = supplierInvoiceNo;
                payload['supplier_invoice_date[' + cartId + ']']  = supplierInvoiceDate;
                payload['terms[' + cartId + ']']                  = terms;
                payload['total_no_of_packages[' + cartId + ']']   = totalPackages;
                payload['total_weight[' + cartId + ']']           = totalWeight;
                payload['total_cbm[' + cartId + ']']              = totalCbm;

                $btn.prop('disabled', true).addClass('btn-loading');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: payload,
                    success: function (resp) {
                        // Recalculate totals on UI
                        recalcTotals();

                        // Optional: update header totals if backend sends them
                        if (resp && typeof resp.total_items !== 'undefined') {
                            $('#total_items').text(resp.total_items);
                        }
                        if (resp && typeof resp.total_qty !== 'undefined') {
                            $('#total_qty').text(resp.total_qty);
                        }
                        if (resp && typeof resp.total_value !== 'undefined') {
                            $('#total_value').text(parseFloat(resp.total_value).toFixed(2));
                        }
                        if (resp && typeof resp.total_packages !== 'undefined') {
                            $('#total_packages').text(resp.total_packages);
                        }
                        if (resp && typeof resp.total_weight !== 'undefined') {
                            $('#total_weight').text(parseFloat(resp.total_weight).toFixed(4));
                        }
                        if (resp && typeof resp.total_cbm !== 'undefined') {
                            $('#total_cbm').text(parseFloat(resp.total_cbm).toFixed(4));
                        }

                        if (window.AIZ && AIZ.plugins && typeof AIZ.plugins.notify === 'function') {
                            AIZ.plugins.notify('success', resp.message || 'Row updated successfully.');
                        } else {
                            alert(resp.message || 'Row updated successfully.');
                        }
                    },
                    error: function (xhr) {
                        let msg = 'Something went wrong.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        if (window.AIZ && AIZ.plugins && typeof AIZ.plugins.notify === 'function') {
                            AIZ.plugins.notify('danger', msg);
                        } else {
                            alert(msg);
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false).removeClass('btn-loading');
                    }
                });
            });

        })(jQuery);
    </script>

    @if(session('scroll_to_cart'))
        <script>
            (function($){
                "use strict";
                $(document).ready(function () {
                    $('html, body').animate({
                        scrollTop: $(document).height()
                    }, 800);
                });
            })(jQuery);
        </script>
    @endif
@endsection
