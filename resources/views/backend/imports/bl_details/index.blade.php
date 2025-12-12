@extends('backend.layouts.app')

@section('content')
<style>
    .meta-page-header {
        border-bottom: 1px solid #edf1f7;
        padding-bottom: 10px;
        margin-bottom: 18px;
    }
    .meta-page-title {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
    }
    .meta-page-subtitle {
        font-size: 13px;
        color: #6b7280;
    }
    .meta-toolbar-card {
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgba(15,23,42,.04);
    }
    .meta-toolbar-input .form-control {
        border-radius: 999px 0 0 999px;
        border-right: 0;
        box-shadow: none !important;
    }
    .meta-toolbar-input .input-group-text {
        border-radius: 999px 0 0 999px;
        border-right: 0;
        background: #fff;
    }
    .meta-toolbar-input .btn-primary {
        border-radius: 0 999px 999px 0;
    }
    .meta-count-chip {
        border-radius: 999px;
        font-size: 12px;
        background: #eef2ff;
        color: #3730a3;
        border: 1px solid #e0e7ff;
    }
    .meta-table-card {
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(15,23,42,.05);
    }
    .meta-table thead tr {
        background: #f9fafb;
    }
    .meta-table thead th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid #e5e7eb !important;
        color: #6b7280;
    }
    .meta-table tbody tr {
        border-bottom: 1px solid #f3f4f6;
    }
    .meta-table tbody tr:hover {
        background: #f9fafb;
    }
    .meta-bl-no {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
    }
    .meta-bl-date {
        font-size: 12px;
        color: #6b7280;
    }
    .meta-company-name {
        font-size: 13px;
        font-weight: 500;
        color: #111827;
    }
    .meta-company-sub {
        font-size: 12px;
        color: #9ca3af;
    }
    .meta-badge-soft {
        font-size: 11px;
        border-radius: 999px;
        padding: 2px 8px;
    }
    .meta-pagination-info {
        font-size: 12px;
        color: #6b7280;
        padding-top: 6px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page header --}}
        <div class="meta-page-header d-flex justify-content-between align-items-center">
            <div>
                <div class="meta-page-title">{{ translate('BL Details') }}</div>
                <div class="meta-page-subtitle">
                    {{ translate('Manage all Bill of Lading records linked to import companies and suppliers.') }}
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <i class="las la-check-circle mr-1"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        {{-- Toolbar: search + count --}}
        <div class="card meta-toolbar-card mb-3 border-0">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    {{-- Search --}}
                    <div class="col-lg-7 mb-2 mb-lg-0">
                        <form method="GET" action="{{ route('import_bl_details.index') }}">
                            <div class="input-group input-group-sm meta-toolbar-input">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">
                                        <i class="las la-search text-gray-500"></i>
                                    </span>
                                </div>
                                <input type="text"
                                       name="search"
                                       class="form-control"
                                       value="{{ request('search') }}"
                                       placeholder="{{ translate('Search by BL No, company, supplier, port...') }}">
                                <div class="input-group-append">
                                    @if(request('search'))
                                        <a href="{{ route('import_bl_details.index') }}"
                                           class="btn btn-light border-left-0">
                                            <i class="las la-times"></i>
                                        </a>
                                    @endif
                                    <button class="btn btn-primary" type="submit">
                                        {{ translate('Search') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Count chip --}}
                    <div class="col-lg-5 text-lg-right">
                        @php
                            $totalBl = method_exists($blDetails, 'total')
                                ? $blDetails->total()
                                : $blDetails->count();
                        @endphp
                        <span class="badge meta-count-chip d-inline-flex align-items-center mr-lg-3 pr-5">
                            <i class="las la-database mr-1" style="font-size: 13px;"></i>
                            <span>{{ translate('Total BL Records') }}:</span>
                            <strong class="ml-1">{{ $totalBl }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Listing card --}}
        <div class="card meta-table-card border-0">
            <div class="card-header border-0 bg-white">
                <div class="d-flex align-items-center">
                    <span class="rounded-circle bg-soft-primary text-primary p-2 mr-2">
                        <i class="las la-ship"></i>
                    </span>
                    <div>
                        <div style="font-size: 14px; font-weight: 600;">
                            {{ translate('Bill of Lading Records') }}
                        </div>
                        <div class="text-muted" style="font-size: 12px;">
                            {{ translate('Each BL is mapped to an import company and optionally a supplier.') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 meta-table">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>{{ translate('BL Info') }}</th>
                                <th>{{ translate('Company') }}</th>
                                <th>{{ translate('Supplier') }}</th>
                                <th>{{ translate('Packages / Weights / CBM') }}</th>
                                <th>{{ translate('Ports') }}</th>
                                <th class="text-center">{{ translate('BL PDF') }}</th>
                                <th class="text-right">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($blDetails as $index => $bl)
                                <tr>
                                    {{-- Sr No --}}
                                    <td>
                                        @php
                                            $rowNumber = method_exists($blDetails, 'firstItem')
                                                ? $blDetails->firstItem() + $index
                                                : $index + 1;
                                        @endphp
                                        {{ $rowNumber }}
                                    </td>

                                    {{-- BL Info --}}
                                    <td>
                                        <div class="meta-bl-no">
                                            {{ $bl->bl_no ?? '-' }}
                                        </div>
                                        <div class="meta-bl-date">
                                            @if($bl->ob_date)
                                                <i class="las la-calendar mr-1"></i>
                                                {{ \Carbon\Carbon::parse($bl->ob_date)->format('d M Y') }}
                                            @else
                                                <span class="text-muted">{{ translate('On board date not set') }}</span>
                                            @endif
                                        </div>
                                        @if($bl->vessel_name)
                                            <div class="meta-company-sub">
                                                <i class="las la-anchor mr-1"></i>
                                                {{ $bl->vessel_name }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Company --}}
                                    <td>
                                        @if($bl->importCompany)
                                            <div class="meta-company-name">
                                                {{ $bl->importCompany->company_name }}
                                            </div>
                                            <div class="meta-company-sub">
                                                {{ $bl->importCompany->city }},
                                                {{ $bl->importCompany->country }}
                                            </div>
                                        @else
                                            <span class="badge badge-soft-secondary meta-badge-soft">
                                                {{ translate('Not linked') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Supplier --}}
                                    <td>
                                        @if($bl->supplier)
                                            <div class="meta-company-name">
                                                {{ $bl->supplier->supplier_name }}
                                            </div>
                                            <div class="meta-company-sub">
                                                {{ $bl->supplier->city }},
                                                {{ $bl->supplier->country }}
                                            </div>
                                        @else
                                            <span class="badge badge-soft-warning meta-badge-soft">
                                                {{ translate('No supplier mapped') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Packages / Weights / CBM --}}
                                    <td>
                                        <div class="small">
                                            <strong>{{ translate('Packages') }}:</strong>
                                            {{ $bl->no_of_packages ?? '-' }}
                                        </div>
                                        <div class="small">
                                            <strong>{{ translate('Gross Wt') }}:</strong>
                                            {{ $bl->gross_weight ?? '-' }} kg
                                        </div>
                                        <div class="small">
                                            <strong>{{ translate('Net Wt') }}:</strong>
                                            {{ $bl->net_weight ?? '-' }} kg
                                        </div>
                                        <div class="small">
                                            <strong>{{ translate('Gross CBM') }}:</strong>
                                            {{ $bl->gross_cbm ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- Ports --}}
                                    <td>
                                        <div class="small">
                                            <strong>{{ translate('Loading') }}:</strong>
                                            {{ $bl->port_of_loading ?? '-' }}
                                        </div>
                                        <div class="small">
                                            <strong>{{ translate('Delivery') }}:</strong>
                                            {{ $bl->place_of_delivery ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- BL PDF --}}
                                    <td class="text-center">
                                        @php
                                            $baseUrl = rtrim(env('UPLOADS_BASE_URL', url('public')), '/');
                                            $pdfUrl = $bl->pdf_path
                                                ? $baseUrl . '/' . ltrim($bl->pdf_path, '/')
                                                : null;
                                        @endphp

                                        @if($pdfUrl)
                                            <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="las la-file-pdf mr-1"></i>
                                                {{ translate('View') }}
                                            </a>
                                        @else
                                            <span class="badge badge-soft-warning meta-badge-soft">
                                                {{ translate('Not Uploaded') }}
                                            </span>
                                        @endif
                                    </td>

                                   {{-- Actions --}}
                                    <td class="text-right">
                                       {{-- <a href="{{ route('import_bl.products.list', $bl->id) }}"
                                           class="btn btn-sm btn-primary">
                                            {{ translate('Proceed') }}
                                        </a>--}}
                                        <a href="{{ route('import.cart.index', $bl->id) }}"
                                           class="btn btn-sm btn-primary">
                                            {{ translate('Proceed') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="las la-info-circle mr-1"></i>
                                        {{ translate('No BL records found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if(method_exists($blDetails, 'links'))
                <div class="card-footer py-3 bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div class="meta-pagination-info mb-2 mb-md-0">
                            @if($blDetails->total() > 0)
                                {{ translate('Showing') }}
                                <strong>{{ $blDetails->firstItem() }}</strong>
                                {{ translate('to') }}
                                <strong>{{ $blDetails->lastItem() }}</strong>
                                {{ translate('of') }}
                                <strong>{{ $blDetails->total() }}</strong>
                                {{ translate('BL records') }}
                            @else
                                {{ translate('No records to display') }}
                            @endif
                        </div>
                        <div class="aiz-pagination mb-0">
                            {{ $blDetails->appends(request()->input())->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
