@extends('backend.layouts.app')



@section('content')

<div class="aiz-main-content">

    <div class="px-15px px-lg-25px">



        {{-- Page Header --}}

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div>

                <h5 class="mb-1">{{ translate('BL Supplier Summary') }}</h5>

                <div class="small text-muted">

                    <span class="mr-2">

                        <strong>{{ translate('BL No') }}:</strong>

                        {{ $bl->bl_no ?? '-' }}

                    </span>

                    <span class="mr-2">

                        <strong>{{ translate('Company') }}:</strong>

                        {{ optional($bl->importCompany)->company_name ?? '-' }}

                    </span>

                    <br>

                    <span class="mr-2">

                        <strong>{{ translate('Port of Loading') }}:</strong>

                        {{ $bl->port_of_loading ?? '-' }}

                    </span>

                    <span class="mr-2">

                        <strong>{{ translate('Place of Delivery') }}:</strong>

                        {{ $bl->place_of_delivery ?? '-' }}

                    </span>

                </div>

            </div>



            <div class="d-flex align-items-center">

                {{-- Complete CI button --}}

                <a href="{{ route('import.bl.ci_summary.complete', $bl->id) }}"

                   class="btn btn-soft-success btn-sm mr-2">

                    <i class="las la-file-invoice mr-1"></i>{{ translate('Complete CI') }}

                </a>



                <a href="{{ route('import_bl_details.index', $bl->import_company_id) }}"

                   class="btn btn-outline-secondary btn-sm">

                    <i class="las la-arrow-left mr-1"></i> {{ translate('Back to BL List') }}

                </a>

            </div>

        </div>



        {{-- Summary + Items (editable) --}}

        <form action="{{ route('import.bl.ci_summary.update', $bl->id) }}" method="POST" id="ci-summary-form">

            @csrf



            <div class="card">

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table mb-0 aiz-table">

                            <thead class="thead-light">

                                <tr>

                                    <th style="width: 40%">{{ translate('PARTY (Supplier)') }}</th>

                                    <th class="text-right" style="width: 12%">{{ translate('CARTON') }}</th>

                                    <th class="text-right" style="width: 12%">{{ translate('WT') }}</th>

                                    <th class="text-right" style="width: 12%">{{ translate('CBM') }}</th>

                                    <th class="text-right" style="width: 24%">{{ translate('Total Value (USD)') }}</th>

                                </tr>

                            </thead>

                            <tbody>

                                {{-- Supplier rows --}}

                                @forelse($supplierRows as $row)

                                    @php

                                        $supplierId = $row->supplier_id;

                                        $items      = $supplierItems[$supplierId] ?? collect();

                                        $collapseId = 'supplier-items-' . $supplierId;

                                    @endphp



                                    {{-- SUPPLIER SUMMARY ROW --}}

                                    <tr class="bg-soft-secondary">

                                        <td>

                                            @if($items->count() > 0)

                                                <button type="button"

                                                        class="btn btn-xs btn-soft-primary mr-2 toggle-items"

                                                        data-target="#{{ $collapseId }}">

                                                    <i class="las la-plus"></i>

                                                </button>

                                            @else

                                                <button type="button"

                                                        class="btn btn-xs btn-soft-secondary mr-2"

                                                        disabled>

                                                    <i class="las la-minus"></i>

                                                </button>

                                            @endif

                                            {{ $row->supplier_name ?? '-' }}

                                        </td>

                                        <td class="text-right">

                                            {{ (int) ($row->cartons ?? 0) }}

                                        </td>

                                        <td class="text-right">

                                            {{ number_format((float) ($row->wt ?? 0), 2) }}

                                        </td>

                                        <td class="text-right">

                                            {{ number_format((float) ($row->cbm ?? 0), 2) }}

                                        </td>

                                        <td class="text-right">

                                            {{ number_format((float) ($row->value_usd ?? 0), 2) }}

                                        </td>

                                    </tr>



                                    {{-- SUPPLIER ITEMS ROWS (COLLAPSIBLE) --}}

                                    @if($items->count() > 0)

                                        <tr class="collapse show" id="{{ $collapseId }}">

                                            <td colspan="5" class="bg-soft-light">

                                                <div class="table-responsive">

                                                    <table class="table table-sm mb-0">

                                                        <thead>

                                                            <tr class="bg-soft-secondary">

                                                                <th style="width: 30%">{{ translate('Item (Print Name)') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Qty') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Cartons') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Total WT') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Total CBM') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Unit Price (USD)') }}</th>

                                                                <th class="text-right" style="width: 10%">{{ translate('Line Value (USD)') }}</th>

                                                            </tr>

                                                        </thead>

                                                        <tbody>

                                                            @foreach($items as $item)

                                                                @php

                                                                    $lineValue = (float)($item->value_total ?? ($item->item_quantity * $item->item_dollar_price));

                                                                @endphp

                                                                <tr class="ci-item-row" data-id="{{ $item->id }}">

                                                                    {{-- ITEM NAME --}}

                                                                    <td>

                                                                        <input type="text"

                                                                               class="form-control form-control-sm field-item-name"

                                                                               name="items[{{ $item->id }}][item_print_name]"

                                                                               value="{{ $item->item_print_name }}">

                                                                    </td>



                                                                    {{-- QTY --}}

                                                                    <td class="text-right">

                                                                        <input type="number"

                                                                               step="0.0001"

                                                                               class="form-control form-control-sm text-right field-qty"

                                                                               name="items[{{ $item->id }}][item_quantity]"

                                                                               value="{{ $item->item_quantity }}">

                                                                    </td>



                                                                    {{-- CARTONS --}}

                                                                    <td class="text-right">

                                                                        <input type="number"

                                                                               step="0.0001"

                                                                               class="form-control form-control-sm text-right field-cartons"

                                                                               name="items[{{ $item->id }}][cartons_total]"

                                                                               value="{{ $item->cartons_total }}">

                                                                    </td>



                                                                    {{-- TOTAL WT --}}

                                                                    <td class="text-right">

                                                                        <input type="number"

                                                                               step="0.0001"

                                                                               class="form-control form-control-sm text-right field-total-wt"

                                                                               name="items[{{ $item->id }}][weight_total]"

                                                                               value="{{ $item->weight_total }}">

                                                                    </td>



                                                                    {{-- TOTAL CBM --}}

                                                                    <td class="text-right">

                                                                        <input type="number"

                                                                               step="0.0001"

                                                                               class="form-control form-control-sm text-right field-total-cbm"

                                                                               name="items[{{ $item->id }}][cbm_total]"

                                                                               value="{{ $item->cbm_total }}">

                                                                    </td>



                                                                    {{-- UNIT PRICE --}}

                                                                    <td class="text-right">

                                                                        <input type="number"

                                                                               step="0.0001"

                                                                               class="form-control form-control-sm text-right field-unit-price"

                                                                               name="items[{{ $item->id }}][item_dollar_price]"

                                                                               value="{{ $item->item_dollar_price }}">

                                                                    </td>



                                                                    {{-- LINE VALUE --}}

                                                                    <td class="text-right">

                                                                        <span class="cell-line-value">

                                                                            {{ number_format($lineValue, 2) }}

                                                                        </span>

                                                                        <input type="hidden"

                                                                               class="field-line-value"

                                                                               name="items[{{ $item->id }}][value_total]"

                                                                               value="{{ $lineValue }}">

                                                                    </td>

                                                                </tr>

                                                            @endforeach

                                                        </tbody>

                                                    </table>

                                                </div>

                                            </td>

                                        </tr>

                                    @endif

                                @empty

                                    <tr>

                                        <td colspan="5" class="text-center text-muted py-4">

                                            <i class="las la-info-circle mr-1"></i>

                                            {{ translate('No CI items found for this BL.') }}

                                        </td>

                                    </tr>

                                @endforelse



                                {{-- TOTAL (CI side, from items) --}}

                                <tr class="font-weight-bold">

                                    <td>{{ translate('Total') }}</td>

                                    <td class="text-right" id="total_cartons">

                                        {{ (int) ($ciTotals['cartons'] ?? 0) }}

                                    </td>

                                    <td class="text-right" id="total_wt">

                                        {{ number_format((float) ($ciTotals['wt'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right" id="total_cbm">

                                        {{ number_format((float) ($ciTotals['cbm'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right" id="total_value">

                                        {{ number_format((float) ($ciTotals['value'] ?? 0), 2) }}

                                    </td>

                                </tr>



                                {{-- BL TOTAL --}}

                                <tr class="font-weight-bold">

                                    <td>{{ translate('BL') }}</td>

                                    <td class="text-right" id="bl_cartons" data-raw="{{ (float)($blTotals['cartons'] ?? 0) }}">

                                        {{ (int) ($blTotals['cartons'] ?? 0) }}

                                    </td>

                                    <td class="text-right" id="bl_wt" data-raw="{{ (float)($blTotals['wt'] ?? 0) }}">

                                        {{ number_format((float) ($blTotals['wt'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right" id="bl_cbm" data-raw="{{ (float)($blTotals['cbm'] ?? 0) }}">

                                        {{ number_format((float) ($blTotals['cbm'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right" id="bl_value" data-raw="{{ (float)($blTotals['value'] ?? 0) }}">

                                        {{ number_format((float) ($blTotals['value'] ?? 0), 2) }}

                                    </td>

                                </tr>



                                {{-- DIFF = CI - BL --}}

                                <tr class="font-weight-bold">

                                    <td>{{ translate('DIFF') }}</td>

                                    <td class="text-right diff-cell" id="diff_cartons">

                                        {{ (int) ($diffTotals['cartons'] ?? 0) }}

                                    </td>

                                    <td class="text-right diff-cell" id="diff_wt">

                                        {{ number_format((float) ($diffTotals['wt'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right diff-cell" id="diff_cbm">

                                        {{ number_format((float) ($diffTotals['cbm'] ?? 0), 2) }}

                                    </td>

                                    <td class="text-right diff-cell" id="diff_value">

                                        {{ number_format((float) ($diffTotals['value'] ?? 0), 2) }}

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>



                {{-- SAVE BUTTON --}}

                <div class="card-footer d-flex justify-content-end">

                    <button type="submit" class="btn btn-sm btn-primary">

                        <i class="las la-save mr-1"></i>{{ translate('Save Item Changes') }}

                    </button>

                </div>

            </div>

        </form>



    </div>

</div>

@endsection



@section('script')

<script>

    (function($){

        "use strict";



        // toggle show/hide of supplier items

        $(document).on('click', '.toggle-items', function () {

            var target = $(this).data('target');

            $(target).collapse('toggle');



            var $icon = $(this).find('i');

            if ($icon.hasClass('la-plus')) {

                $icon.removeClass('la-plus').addClass('la-minus');

            } else {

                $icon.removeClass('la-minus').addClass('la-plus');

            }

        });



        function recalcSummary() {

            var totalCartons = 0;

            var totalWt      = 0;

            var totalCbm     = 0;

            var totalValue   = 0;



            $('.ci-item-row').each(function () {

                var $row = $(this);



                var qty     = parseFloat($row.find('.field-qty').val()) || 0;

                var cartons = parseFloat($row.find('.field-cartons').val()) || 0;

                var wt      = parseFloat($row.find('.field-total-wt').val()) || 0;

                var cbm     = parseFloat($row.find('.field-total-cbm').val()) || 0;

                var unit    = parseFloat($row.find('.field-unit-price').val()) || 0;



                var lineVal = qty * unit;



                // update line value display + hidden

                $row.find('.cell-line-value').text(lineVal.toFixed(2));

                $row.find('.field-line-value').val(lineVal);



                totalCartons += cartons;

                totalWt      += wt;

                totalCbm     += cbm;

                totalValue   += lineVal;

            });



            // TOTAL row — display with 2 decimals for WT & CBM

            $('#total_cartons').text(totalCartons);

            $('#total_wt').text(totalWt.toFixed(2));

            $('#total_cbm').text(totalCbm.toFixed(2));

            $('#total_value').text(totalValue.toFixed(2));



            // BL raw values from data attributes

            var blCartons = parseFloat($('#bl_cartons').data('raw')) || 0;

            var blWt      = parseFloat($('#bl_wt').data('raw')) || 0;

            var blCbm     = parseFloat($('#bl_cbm').data('raw')) || 0;

            var blValue   = parseFloat($('#bl_value').data('raw')) || 0;



            var diffCartons = totalCartons - blCartons;

            var diffWt      = totalWt      - blWt;

            var diffCbm     = totalCbm     - blCbm;

            var diffValue   = totalValue   - blValue;



            $('#diff_cartons').text(diffCartons);

            $('#diff_wt').text(diffWt.toFixed(2));

            $('#diff_cbm').text(diffCbm.toFixed(2));

            $('#diff_value').text(diffValue.toFixed(2));



            // Colour diff cells: 0 => green, else red

            $('.diff-cell').each(function(){

                var val = parseFloat($(this).text()) || 0;

                $(this)

                    .removeClass('text-success text-danger')

                    .addClass(val === 0 ? 'text-success' : 'text-danger');

            });

        }



        $(document).ready(function () {

            // initial colour state

            recalcSummary();



            // live recalculation on input change

            $(document).on('input change',

                '.field-qty, .field-cartons, .field-total-wt, .field-total-cbm, .field-unit-price',

                function () {

                    recalcSummary();

                }

            );

        });



    })(jQuery);

</script>

@endsection