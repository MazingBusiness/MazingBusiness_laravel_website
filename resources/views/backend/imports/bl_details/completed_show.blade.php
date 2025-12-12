@extends('backend.layouts.app')

@section('content')
@php
    // Base URL from .env (e.g. https://mazingbusiness.com/public)
    $uploadsBase = rtrim(env('UPLOADS_BASE_URL', ''), '/');
@endphp

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">{{ translate('Completed BL Details') }}</h5>
                <div class="small text-muted">
                    {{ translate('BL No') }}:
                    <strong>{{ $bl->bl_no ?? ('BL#'.$bl->id) }}</strong>
                </div>
            </div>

            <div class="d-flex align-items-center">
                {{-- Same CI / PL ZIP download buttons --}}
                <a href="{{ route('import_bl_details.pending.download_ci_zip', $bl->id) }}"
                   class="btn btn-sm btn-outline-primary mr-2">
                    <i class="las la-file-download mr-1"></i>{{ translate('Download CI (ZIP)') }}
                </a>

                <a href="{{ route('import_bl_details.pending.download_pl_zip', $bl->id) }}"
                   class="btn btn-sm btn-outline-success mr-2">
                    <i class="las la-file-download mr-1"></i>{{ translate('Download PL (ZIP)') }}
                </a>
                
                {{-- NEW BUTTON --}}
                <a href="{{ route('import.bl-details.items_poster_pdf', $bl->id) }}"
                   class="btn btn-sm btn-outline-success mr-2"
                   target="_blank">
                    Download Items Image PDF
                </a>
                
                <a href="{{ route('import.bl-details.items_poster_doc', $bl->id) }}"
                   class="btn btn-sm btn-outline-success mr-2"
                   target="_blank">
                    Download Items Image Word
                </a>
                
                <a href="{{ route('import_bl_details.completed') }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="las la-arrow-left mr-1"></i>{{ translate('Back to Completed BL') }}
                </a>
            </div>
        </div>

        {{-- Top cards --}}
        <div class="row">

            {{-- Import Company Details --}}
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('Import Company Details') }}</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $company = $bl->importCompany;

                            $buyerStampUrl = null;
                            if ($company && $company->buyer_stamp) {
                                $buyerStampUrl = $uploadsBase
                                    ? $uploadsBase.'/'.ltrim($company->buyer_stamp, '/')
                                    : asset($company->buyer_stamp);
                            }
                        @endphp

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

                        @if($buyerStampUrl)
                            <div class="mt-2">
                                <a href="{{ $buyerStampUrl }}"
                                   target="_blank"
                                   class="btn btn-xs btn-soft-success">
                                    <i class="las la-file mr-1"></i>{{ translate('View Buyer Stamp') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- BL Details --}}
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">{{ translate('BL Details') }}</h6>
                    </div>
                    <div class="card-body">
                        @php
                            // BL PDF
                            $blPdfUrl = null;
                            if ($bl->pdf_path) {
                                $blPdfUrl = $uploadsBase
                                    ? $uploadsBase.'/'.ltrim($bl->pdf_path, '/')
                                    : asset($bl->pdf_path);
                            }

                            // Bill of Entry PDF
                            $boePdfUrl = null;
                            if ($bl->bill_of_entry_pdf) {
                                $boePdfUrl = $uploadsBase
                                    ? $uploadsBase.'/'.ltrim($bl->bill_of_entry_pdf, '/')
                                    : asset($bl->bill_of_entry_pdf);
                            }

                            // Supplier dropdown (same logic)
                            $supplierIdsCombined = [];

                            if (!empty($bl->supplier_ids)) {
                                $supplierIdsCombined = array_filter(
                                    array_map('trim', explode(',', $bl->supplier_ids)),
                                    fn($v) => $v !== ''
                                );
                            }

                            if (empty($supplierIdsCombined)) {
                                $supplierIdsCombined = $bl->items
                                    ->pluck('supplier_id')
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all();
                            }

                            $supplierOptions = collect();
                            if (!empty($supplierIdsCombined)) {
                                $supplierOptions = \App\Models\Supplier::whereIn('id', $supplierIdsCombined)
                                    ->orderBy('supplier_name')
                                    ->get();
                            }
                        @endphp

                        <div><strong>{{ translate('BL No') }}:</strong> {{ $bl->bl_no ?? '-' }}</div>

                        {{-- Suppliers dropdown --}}
                        <div class="mb-2">
                            <strong>{{ translate('Suppliers in this BL') }}:</strong>
                            @if($supplierOptions->isNotEmpty())
                                <select class="form-control form-control-sm mt-1" >
                                    @foreach($supplierOptions as $sup)
                                        <option>
                                            {{ $sup->supplier_name }}
                                            @if($sup->city)
                                                — {{ $sup->city }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span> - </span>
                            @endif
                        </div>

                        <div><strong>{{ translate('O/B Date') }}:</strong>
                            {{ $bl->ob_date ? \Carbon\Carbon::parse($bl->ob_date)->format('d/m/Y') : '-' }}
                        </div>
                        <div><strong>{{ translate('Vessel Name') }}:</strong> {{ $bl->vessel_name ?? '-' }}</div>
                        <div><strong>{{ translate('Port of Loading') }}:</strong> {{ $bl->port_of_loading ?? '-' }}</div>
                        <div><strong>{{ translate('Place of Delivery') }}:</strong> {{ $bl->place_of_delivery ?? '-' }}</div>

                        <hr class="my-2">

                        <div class="row">
                            <div class="col-4">
                                <strong>{{ translate('Packages') }}:</strong>
                                {{ number_format((float)($bl->no_of_packages ?? 0), 0) }}
                            </div>
                            <div class="col-4">
                                <strong>{{ translate('Gross Wt') }}:</strong>
                                {{ number_format((float)($bl->gross_weight ?? 0), 2) }}
                            </div>
                            <div class="col-4">
                                <strong>{{ translate('Gross CBM') }}:</strong>
                                {{ number_format((float)($bl->gross_cbm ?? 0), 3) }}
                            </div>
                        </div>

                        {{-- BL & BOE download buttons --}}
                        <div class="mt-3 d-flex align-items-center flex-wrap">
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
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    {{ translate('BL Item Details') }}
                    <span class="badge badge-soft-primary ml-2">
                        {{ $bl->items->count() }} {{ translate('items') }}
                    </span>
                </h6>
            </div>
            <div class="card-body">
                @if($bl->items->isEmpty())
                    <div class="alert alert-info mb-0">
                        {{ translate('No items found under this BL.') }}
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>{{ translate('Product') }}</th>
                                    <th>{{ translate('Item Name') }}</th>
                                    <th class="text-right">{{ translate('Qty') }}</th>
                                    <th class="text-right">{{ translate('Unit Price (USD)') }}</th>
                                    <th class="text-right">{{ translate('Total Packages') }}</th>
                                    <th class="text-right">{{ translate('Total Weight') }}</th>
                                    <th class="text-right">{{ translate('Total CBM') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bl->items as $i => $item)
                                    @php
                                        $productLabel = '-';
                                        if ($item->product) {
                                            $productLabel = trim(
                                                ($item->product->part_no ?? '').' '.
                                                ($item->product->name ?? '')
                                            );
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $productLabel }}</td>
                                        <td>{{ $item->item_name ?? '-' }}</td>
                                        <td class="text-right">
                                            {{ number_format((float)($item->quantity ?? 0), 0) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($item->dollar_price ?? 0), 4) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($item->total_no_of_packages ?? 0), 0) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($item->total_weight ?? 0), 2) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($item->total_cbm ?? 0), 3) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totPackages = (float) $bl->items->sum('total_no_of_packages');
                                    $totWeight   = (float) $bl->items->sum('total_weight');
                                    $totCbm      = (float) $bl->items->sum('total_cbm');
                                @endphp
                                <tr>
                                    <th colspan="5" class="text-right">{{ translate('Totals') }}</th>
                                    <th class="text-right">{{ number_format($totPackages, 0) }}</th>
                                    <th class="text-right">{{ number_format($totWeight, 2) }}</th>
                                    <th class="text-right">{{ number_format($totCbm, 3) }}</th>
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
