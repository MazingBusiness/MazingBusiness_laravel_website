@extends('backend.layouts.app')

@section('content')
<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        @php
            // ✅ Base URL from .env (e.g. UPLOADS_BASE_URL=https://mazingbusiness.com/public)
            $uploadsBase = rtrim(env('UPLOADS_BASE_URL', ''), '/');

            $company  = $ci->importCompany;
            $supplier = $ci->supplier;
            $bl       = $ci->bl;

            // BL display text
            if (!empty(optional($bl)->bl_no)) {
                $blDisplay = $bl->bl_no;
            } elseif (!empty($ci->bl_id)) {
                $blDisplay = $ci->bl_id;
            } else {
                $blDisplay = '-';
            }

            $invDate = $ci->supplier_invoice_date
                ? \Carbon\Carbon::parse($ci->supplier_invoice_date)->format('d/m/Y')
                : '-';

            $items = $ci->items ?? collect();

            $itemPackages = (float) $items->sum('total_no_of_packages');
            $itemGrossWt  = (float) $items->sum('total_weight');
            $itemCbm      = (float) $items->sum('total_cbm');

            $packages = $ci->no_of_packages ?? $itemPackages;
            $grossWt  = $ci->gross_weight   ?? $itemGrossWt;
            $grossCbm = $ci->gross_cbm      ?? $itemCbm;

            // ✅ BL PDF
            $blPdfUrl = null;
            if ($bl && $bl->pdf_path) {
                $blPdfUrl = $uploadsBase
                    ? $uploadsBase.'/'.ltrim($bl->pdf_path, '/')
                    : asset($bl->pdf_path);
            }

            // ✅ Bill of Entry PDF
            $boePdfUrl = null;
            if ($bl && $bl->bill_of_entry_pdf) {
                $boePdfUrl = $uploadsBase
                    ? $uploadsBase.'/'.ltrim($bl->bill_of_entry_pdf, '/')
                    : asset($bl->bill_of_entry_pdf);
            }
        @endphp

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">{{ translate('Commercial Invoice Details') }}</h5>
                <div class="small text-muted">
                    {{ translate('Supplier Invoice No') }}:
                    <strong>{{ $ci->supplier_invoice_no ?? '-' }}</strong>
                    &nbsp;|&nbsp;
                    {{ translate('BL No') }}:
                    <strong>{{ $blDisplay }}</strong>
                </div>
            </div>

            <div class="d-flex align-items-center">
                {{-- ✅ CI / PL download buttons (ZIP) – needs BL --}}
                @if($bl)
                    <a href="{{ route('import_bl_details.pending.download_ci_zip', $bl->id) }}"
                       class="btn btn-sm btn-outline-primary mr-2">
                        <i class="las la-file-download mr-1"></i>{{ translate('Download CI (ZIP)') }}
                    </a>

                    <a href="{{ route('import_bl_details.pending.download_pl_zip', $bl->id) }}"
                       class="btn btn-sm btn-outline-success mr-2">
                        <i class="las la-file-download mr-1"></i>{{ translate('Download PL (ZIP)') }}
                    </a>
                @endif

                <a href="{{ route('import_ci.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="las la-arrow-left mr-1"></i>{{ translate('Back to CI Listing') }}
                </a>
            </div>
        </div>

        {{-- Info cards --}}
        <div class="row">
            {{-- Importer --}}
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('Importer Details') }}</h6>
                    </div>
                    <div class="card-body">
                        @if($company)
                            <div><strong>{{ translate('Company') }}:</strong> {{ $company->company_name ?? '-' }}</div>
                            <div><strong>{{ translate('Address') }}:</strong>
                                {{ $company->address_1 ?? '' }}
                                {{ $company->address_2 ? ', '.$company->address_2 : '' }}
                            </div>
                            <div><strong>{{ translate('City') }}:</strong> {{ $company->city ?? '-' }}</div>
                            <div><strong>{{ translate('State') }}:</strong> {{ $company->state ?? '-' }}</div>
                            <div><strong>{{ translate('Country') }}:</strong> {{ $company->country ?? '-' }}</div>
                            <div><strong>{{ translate('GSTIN') }}:</strong> {{ $company->gstin ?? '-' }}</div>
                            <div><strong>{{ translate('IEC No') }}:</strong> {{ $company->iec_no ?? '-' }}</div>
                            <div><strong>{{ translate('Phone') }}:</strong> {{ $company->phone ?? '-' }}</div>
                            <div><strong>{{ translate('Email') }}:</strong> {{ $company->email ?? '-' }}</div>
                        @else
                            <div class="text-muted">-</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Supplier --}}
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('Supplier Details') }}</h6>
                    </div>
                    <div class="card-body">
                        @if($supplier)
                            <div><strong>{{ translate('Supplier') }}:</strong> {{ $supplier->supplier_name ?? '-' }}</div>
                            <div><strong>{{ translate('Address') }}:</strong> {{ $supplier->address ?? '-' }}</div>
                            <div><strong>{{ translate('City') }}:</strong> {{ $supplier->city ?? '-' }}</div>
                            <div><strong>{{ translate('State / District') }}:</strong> {{ $supplier->district ?? '-' }}</div>
                            <div><strong>{{ translate('Country') }}:</strong> {{ $supplier->country ?? '-' }}</div>
                            <div><strong>{{ translate('Zip Code') }}:</strong> {{ $supplier->zip_code ?? '-' }}</div>
                            <div><strong>{{ translate('Contact') }}:</strong> {{ $supplier->contact ?? '-' }}</div>
                            <div><strong>{{ translate('Email') }}:</strong> {{ $supplier->email ?? '-' }}</div>
                        @else
                            <div class="text-muted">-</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- BL + CI Totals + Download BL/BOE --}}
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">{{ translate('BL & CI Summary') }}</h6>
                    </div>
                    <div class="card-body">
                        <div><strong>{{ translate('BL No') }}:</strong> {{ $blDisplay }}</div>
                        @if($bl)
                            <div><strong>{{ translate('OB Date') }}:</strong>
                                {{ $bl->ob_date ? \Carbon\Carbon::parse($bl->ob_date)->format('d/m/Y') : '-' }}
                            </div>
                            <div><strong>{{ translate('Vessel Name') }}:</strong> {{ $bl->vessel_name ?? '-' }}</div>
                            <div><strong>{{ translate('Port of Loading') }}:</strong> {{ $bl->port_of_loading ?? '-' }}</div>
                            <div><strong>{{ translate('Place of Delivery') }}:</strong> {{ $bl->place_of_delivery ?? '-' }}</div>
                        @endif

                        <hr class="my-2">

                        <div><strong>{{ translate('Supplier Invoice No') }}:</strong> {{ $ci->supplier_invoice_no ?? '-' }}</div>
                        <div><strong>{{ translate('Supplier Invoice Date') }}:</strong> {{ $invDate }}</div>

                        <hr class="my-2">

                        <div><strong>{{ translate('Packages') }}:</strong>
                            {{ $packages ? number_format($packages, 0) : '-' }}
                        </div>
                        <div><strong>{{ translate('Gross Weight') }}:</strong>
                            {{ $grossWt ? number_format($grossWt, 2) : '-' }}
                        </div>
                        <div><strong>{{ translate('Gross CBM') }}:</strong>
                            {{ $grossCbm ? number_format($grossCbm, 3) : '-' }}
                        </div>

                        {{-- ✅ Download BL & BOE PDF buttons --}}
                        @if($bl)
                            <hr class="my-2">
                            <div class="d-flex flex-wrap align-items-center mt-1">
                                @if($blPdfUrl)
                                    <a class="btn btn-xs btn-outline-info mr-2 mb-1"
                                       href="{{ $blPdfUrl }}"
                                       target="_blank"
                                       download>
                                        <i class="las la-file-pdf mr-1"></i>{{ translate('Download BL PDF') }}
                                    </a>
                                @endif

                                @if($boePdfUrl)
                                    <a class="btn btn-xs btn-outline-success mb-1"
                                       href="{{ $boePdfUrl }}"
                                       target="_blank"
                                       download>
                                        <i class="las la-file-pdf mr-1"></i>{{ translate('Download Bill of Entry PDF') }}
                                    </a>
                                @else
                                    <span class="small text-muted mb-1">
                                        {{ translate('Bill of Entry not uploaded yet.') }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Items table --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    {{ translate('Item Details') }}
                    <span class="badge badge-soft-primary ml-2">
                        {{ $items->count() }} {{ translate('items') }}
                    </span>
                </h6>
            </div>
            <div class="card-body">
                @if($items->isEmpty())
                    <div class="alert alert-info mb-0">{{ translate('No items found for this CI.') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:4%;">#</th>
                                    <th style="width:18%;">{{ translate('Part No / Product') }}</th>
                                    <th style="width:28%;">{{ translate('Description') }}</th>
                                    <th class="text-right" style="width:8%;">{{ translate('Qty') }}</th>
                                    <th class="text-right" style="width:10%;">{{ translate('Unit Price (USD)') }}</th>
                                    <th class="text-right" style="width:10%;">{{ translate('Amount (USD)') }}</th>
                                    <th class="text-right" style="width:7%;">{{ translate('Cartons') }}</th>
                                    <th class="text-right" style="width:7%;">{{ translate('T G.W') }}</th>
                                    <th class="text-right" style="width:8%;">{{ translate('T CBM') }}</th>
                                    <th style="width:10%;">{{ translate('Photo') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $sl = 1; @endphp
                                @foreach($items as $item)
                                    @php
                                        $qty    = (float) $item->quantity;
                                        $price  = (float) $item->dollar_price;
                                        $amount = $qty * $price;

                                        $cartons = (float) ($item->total_no_of_packages ?? 0);
                                        $tWt     = (float) ($item->total_weight        ?? 0);
                                        $tCbm    = (float) ($item->total_cbm           ?? 0);

                                        $product = $item->product ?? null;
                                    @endphp
                                    <tr>
                                        <td>{{ $sl++ }}</td>

                                        {{-- Part No / Product --}}
                                        <td>
                                            @if($product)
                                                <div class="font-weight-600">
                                                    {{ $product->part_no ?? '-' }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $product->name ?? '' }}
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>

                                        {{-- Description --}}
                                        <td>{{ $item->item_name ?? '-' }}</td>

                                        {{-- Qty / pricing --}}
                                        <td class="text-right">
                                            {{ $qty ? number_format($qty, 0) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $price ? number_format($price, 4) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $amount ? number_format($amount, 2) : '-' }}
                                        </td>

                                        {{-- Cartons / GW / CBM --}}
                                        <td class="text-right">
                                            {{ $cartons ? number_format($cartons, 0) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $tWt ? number_format($tWt, 2) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $tCbm ? number_format($tCbm, 3) : '-' }}
                                        </td>

                                        {{-- Photo --}}
                                        <td>
                                            @if(!empty($item->import_photo_id))
                                                <a href="{{ uploaded_asset($item->import_photo_id) }}"
                                                   target="_blank"
                                                   class="btn btn-xs btn-soft-info">
                                                    <i class="las la-image mr-1"></i>{{ translate('View') }}
                                                </a>
                                            @else
                                                <span class="badge badge-soft-secondary">
                                                    {{ translate('No Photo') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totCartons = (float) $items->sum('total_no_of_packages');
                                    $totWt      = (float) $items->sum('total_weight');
                                    $totCbm     = (float) $items->sum('total_cbm');
                                    $totAmount  = (float) $items->sum(function($row) {
                                        return (float) $row->quantity * (float) $row->dollar_price;
                                    });
                                @endphp
                                <tr>
                                    <th colspan="3" class="text-right">{{ translate('Totals') }}</th>
                                    <th class="text-right">
                                        {{ number_format((float) $items->sum('quantity'), 0) }}
                                    </th>
                                    <th></th>
                                    <th class="text-right">
                                        {{ $totAmount ? number_format($totAmount, 2) : '-' }}
                                    </th>
                                    <th class="text-right">
                                        {{ $totCartons ? number_format($totCartons, 0) : '-' }}
                                    </th>
                                    <th class="text-right">
                                        {{ $totWt ? number_format($totWt, 2) : '-' }}
                                    </th>
                                    <th class="text-right">
                                        {{ $totCbm ? number_format($totCbm, 3) : '-' }}
                                    </th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
