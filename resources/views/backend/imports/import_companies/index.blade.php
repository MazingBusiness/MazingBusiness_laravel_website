@extends('backend.layouts.app')

@section('content')
<style>
    /* Meta-style tweaks */
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
    .meta-company-name {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
    }
    .meta-company-email {
        font-size: 12px;
        color: #6b7280;
    }
    .meta-location-main {
        font-size: 13px;
        color: #111827;
    }
    .meta-location-sub {
        font-size: 12px;
        color: #9ca3af;
    }
    .meta-badge-soft {
        font-size: 11px;
        border-radius: 999px;
        padding: 2px 8px;
    }
    /* Actions button */
    .meta-action-btn {
        border-radius: 999px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        color: #4b5563;
    }
    .meta-action-btn:hover {
        background: #e5e7eb;
        color: #111827;
    }
    .meta-pagination-info {
        font-size: 12px;
        color: #6b7280;
        padding-top: 6px;
    }

    /* Collapsible details row */
    .meta-details-wrapper {
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
    }
    .meta-details-inner {
        padding: 12px 18px;
    }
    .meta-details-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7280;
        margin-bottom: 6px;
    }
    .meta-details-table {
        width: 100%;
        font-size: 11px;
    }
    .meta-details-table th {
        width: 130px;
        font-weight: 600;
        color: #374151;
        padding: 2px 0;
        vertical-align: top;
    }
    .meta-details-table td {
        padding: 2px 0;
        color: #111827;
    }

    /* Buyer stamp preview */
    .meta-stamp-card {
        margin-top: 8px;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px dashed #d1d5db;
        background: #ffffff;
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .meta-stamp-card img {
        max-width: 140px;
        max-height: 90px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        object-fit: contain;
        background: #f9fafb;
    }

    .btn-details-toggle {
        border-radius: 999px;
        padding: 2px 10px;
        font-size: 11px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page header (Meta-style) --}}
        <div class="meta-page-header d-flex justify-content-between align-items-center">
            <div>
                <div class="meta-page-title">{{ translate('Import Company List') }}</div>
                <div class="meta-page-subtitle">
                    {{ translate('Manage all import companies used in BL, Commercial Invoice & Packing List.') }}
                </div>
            </div>
            <div>
                <a href="{{ route('import_companies.create') }}" class="btn btn-primary">
                    <i class="las la-plus mr-1"></i>{{ translate('Add Import Company') }}
                </a>
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

        {{-- Toolbar: search + count (Meta style) --}}
        <div class="card meta-toolbar-card mb-3 border-0">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    {{-- Search --}}
                    <div class="col-lg-7 mb-2 mb-lg-0">
                        <form method="GET" action="{{ route('import_companies.index') }}">
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
                                       placeholder="{{ translate('Search by company name, city or GSTIN...') }}">
                                <div class="input-group-append">
                                    @if(request('search'))
                                        <a href="{{ route('import_companies.index') }}"
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
                            $totalCompanies = method_exists($companies, 'total')
                                ? $companies->total()
                                : $companies->count();
                        @endphp
                        <span class="badge meta-count-chip d-inline-flex align-items-center mr-lg-3 pr-5">
                            <i class="las la-database mr-1" style="font-size: 13px;"></i>
                            <span>{{ translate('Total Companies') }}:</span>
                            <strong class="ml-1">{{ $totalCompanies }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Listing Card --}}
        <div class="card meta-table-card border-0">
            <div class="card-header border-0 bg-white">
                <div class="d-flex align-items-center">
                    <span class="rounded-circle bg-soft-primary text-primary p-2 mr-2">
                        <i class="las la-building"></i>
                    </span>
                    <div>
                        <div style="font-size: 14px; font-weight: 600;">
                            {{ translate('Companies') }}
                        </div>
                        <div class="text-muted" style="font-size: 12px;">
                            {{ translate('Configured import entities synced across BL, CI & PL workflows.') }}
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
                                <th>{{ translate('Company') }}</th>
                                <th>{{ translate('Location') }}</th>
                                <th>{{ translate('GSTIN / IEC') }}</th>
                                <th>{{ translate('Contact') }}</th>
                                <th class="text-center">{{ translate('BLs') }}</th>
                                <th class="text-center">{{ translate('Buyer Stamp') }}</th>
                                <th class="text-right">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($companies as $index => $company)
                                @php
                                    $rowNumber = method_exists($companies, 'firstItem')
                                        ? $companies->firstItem() + $index
                                        : $index + 1;

                                    // BL count from withCount() if available, otherwise relation
                                    $blCount = isset($company->bl_details_count)
                                        ? $company->bl_details_count
                                        : (isset($company->blDetails) ? $company->blDetails->count() : 0);

                                    $collapseId = 'companyDetails-' . $company->id;

                                    $baseUrl = rtrim(env('UPLOADS_BASE_URL', url('public')), '/');
                                    $buyerStampUrl = $company->buyer_stamp
                                        ? $baseUrl . '/' . ltrim($company->buyer_stamp, '/')
                                        : null;

                                    $stampExt = $company->buyer_stamp
                                        ? strtolower(pathinfo($company->buyer_stamp, PATHINFO_EXTENSION))
                                        : null;
                                @endphp

                                {{-- MAIN ROW --}}
                                <tr>
                                    {{-- Sr No --}}
                                    <td>{{ $rowNumber }}</td>

                                    {{-- Company --}}
                                    <td>
                                        <div class="meta-company-name">
                                            {{ $company->company_name }}
                                        </div>
                                        @if($company->email)
                                            <div class="meta-company-email">
                                                <i class="las la-envelope mr-1"></i>
                                                {{ $company->email }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Location --}}
                                    <td>
                                        <div class="meta-location-main">
                                            <i class="las la-map-marker-alt mr-1 text-muted"></i>
                                            {{ $company->city }},
                                            {{ $company->state }}
                                        </div>
                                        <div class="meta-location-sub">
                                            {{ $company->country }}
                                            @if($company->pincode)
                                                • {{ $company->pincode }}
                                            @endif
                                        </div>
                                    </td>

                                    {{-- GSTIN / IEC --}}
                                    <td>
                                        @if($company->gstin)
                                            <div class="mb-1">
                                                <span style="width: auto;" class="badge badge-soft-success meta-badge-soft">
                                                    GSTIN
                                                </span>
                                                <span class="small ml-1">{{ $company->gstin }}</span>
                                            </div>
                                        @endif
                                        @if($company->iec_no)
                                            <div>
                                                <span style="width: auto;" class="badge badge-soft-info meta-badge-soft">
                                                    IEC
                                                </span>
                                                <span class="small ml-1">{{ $company->iec_no }}</span>
                                            </div>
                                        @endif
                                        @if(!$company->gstin && !$company->iec_no)
                                            <span class="badge badge-soft-secondary meta-badge-soft">
                                                {{ translate('Not Available') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Contact --}}
                                    <td>
                                        @if($company->phone)
                                            <div class="small">
                                                <i class="las la-phone mr-1 text-muted"></i>
                                                {{ $company->phone }}
                                            </div>
                                        @endif
                                        @if($company->email)
                                            <div class="small text-muted">
                                                {{ $company->email }}
                                            </div>
                                        @endif
                                        @if(!$company->phone && !$company->email)
                                            <span class="badge badge-soft-secondary meta-badge-soft">
                                                {{ translate('Not Available') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- BL count --}}
                                    <td class="text-center">
                                        <span class="badge badge-soft-primary meta-badge-soft">
                                            {{ $blCount }}
                                        </span>
                                    </td>

                                    {{-- Buyer Stamp --}}
                                    <td class="text-center">
                                        @if($buyerStampUrl)
                                            <a href="{{ $buyerStampUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="las la-file-image mr-1"></i>
                                                {{ translate('View') }}
                                            </a>
                                        @else
                                            <span class="badge badge-soft-warning meta-badge-soft">
                                                {{ translate('Not Uploaded') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Actions + Details toggle --}}
                                    <td class="text-right">
                                        <div class="d-inline-flex align-items-center">
                                            <button type="button"
                                                    class="btn btn-xs btn-outline-secondary btn-details-toggle mr-1"
                                                    data-toggle="collapse"
                                                    data-target="#{{ $collapseId }}"
                                                    aria-expanded="false"
                                                    aria-controls="{{ $collapseId }}">
                                                <i class="las la-chevron-down"></i> {{ translate('Details') }}
                                            </button>

                                            <div class="dropdown">
                                                <button style="width: calc(3.02rem + 2px);"
                                                        class="btn btn-icon btn-sm meta-action-btn dropdown-toggle"
                                                        type="button" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                    <i class="las la-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">

                                                    {{-- Edit --}}
                                                    @if(Route::has('import_companies.edit'))
                                                        <a href="{{ route('import_companies.edit', $company->id) }}"
                                                           class="dropdown-item">
                                                            <i class="las la-edit mr-2"></i>
                                                            {{ translate('Edit Company') }}
                                                        </a>
                                                    @endif

                                                    {{-- Delete --}}
                                                    @if(Route::has('import_companies.destroy'))
                                                        <form action="{{ route('import_companies.destroy', $company->id) }}"
                                                              method="POST"
                                                              onsubmit="return confirm('{{ translate('Are you sure you want to delete this company?') }}');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="las la-trash-alt mr-2"></i>
                                                                {{ translate('Delete') }}
                                                            </button>
                                                        </form>
                                                    @endif

                                                    {{-- Add BL Details --}}
                                                    <button type="button"
                                                            class="dropdown-item js-add-bl-btn"
                                                            data-company-id="{{ $company->id }}"
                                                            data-company-name="{{ $company->company_name }}">
                                                        <i class="las la-file-alt mr-2"></i>
                                                        {{ translate('Add BL Details') }}
                                                    </button>

                                                    @if(
                                                        !$company->buyer_stamp &&
                                                        !Route::has('import_companies.edit') &&
                                                        !Route::has('import_companies.destroy')
                                                    )
                                                        <span class="dropdown-item text-muted small">
                                                            {{ translate('No actions configured') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{-- DETAILS ROW --}}
                                <tr class="collapse" id="{{ $collapseId }}">
                                    <td colspan="8" class="p-0 border-0">
                                        <div class="meta-details-wrapper">
                                            <div class="meta-details-inner">
                                                <div class="row">
                                                    {{-- Company Details --}}
                                                    <div class="col-md-5 mb-3">
                                                        <div class="meta-details-title">{{ translate('Company Details') }}</div>
                                                        <table class="meta-details-table">
                                                            <tr>
                                                                <th>{{ translate('Company Name') }}</th>
                                                                <td>{{ $company->company_name }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Address Line 1') }}</th>
                                                                <td>{{ $company->address_line_1 ?? '–' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Address Line 2') }}</th>
                                                                <td>{{ $company->address_line_2 ?? '–' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('City / State') }}</th>
                                                                <td>
                                                                    {{ $company->city ?? '–' }}
                                                                    @if($company->state)
                                                                        , {{ $company->state }}
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Country / PIN') }}</th>
                                                                <td>
                                                                    {{ $company->country ?? '–' }}
                                                                    @if($company->pincode)
                                                                        ({{ $company->pincode }})
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('GSTIN') }}</th>
                                                                <td>{{ $company->gstin ?? '–' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('IEC No.') }}</th>
                                                                <td>{{ $company->iec_no ?? '–' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Contact') }}</th>
                                                                <td>
                                                                    @if($company->phone)
                                                                        {{ $company->phone }}
                                                                    @else
                                                                        –
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Email') }}</th>
                                                                <td>{{ $company->email ?? '–' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th>{{ translate('Created At') }}</th>
                                                                <td>
                                                                    {{ $company->created_at
                                                                        ? $company->created_at->format('d M Y, h:i A')
                                                                        : '–' }}
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        {{-- Buyer Stamp preview --}}
                                                        <div class="meta-details-title mt-3">{{ translate('Buyer Stamp') }}</div>
                                                        @if($buyerStampUrl)
                                                            <div class="meta-stamp-card">
                                                                @if(in_array($stampExt, ['jpg','jpeg','png','gif','webp']))
                                                                    <img src="{{ $buyerStampUrl }}" alt="Buyer Stamp">
                                                                    <small class="text-muted">
                                                                        {{ translate('Image stamp') }} ·
                                                                        <a href="{{ $buyerStampUrl }}" target="_blank">
                                                                            {{ translate('Open full size') }}
                                                                        </a>
                                                                    </small>
                                                                @elseif($stampExt === 'pdf')
                                                                    <small>
                                                                        <i class="las la-file-pdf mr-1"></i>
                                                                        {{ translate('PDF stamp') }}
                                                                    </small>
                                                                    <small>
                                                                        <a href="{{ $buyerStampUrl }}" target="_blank">
                                                                            {{ translate('View PDF') }}
                                                                        </a>
                                                                    </small>
                                                                @else
                                                                    <small class="text-muted">
                                                                        {{ translate('Stamp file uploaded') }}
                                                                        <a href="{{ $buyerStampUrl }}" target="_blank">
                                                                            ({{ translate('Open') }})
                                                                        </a>
                                                                    </small>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <small class="text-muted">
                                                                {{ translate('No stamp uploaded for this company.') }}
                                                            </small>
                                                        @endif
                                                    </div>

                                                    {{-- BL Details --}}
                                                    <div class="col-md-7 mb-3">
                                                        <div class="meta-details-title">
                                                            {{ translate('BL Details') }}
                                                            ({{ $blCount }})
                                                        </div>

                                                        @php
                                                            // Prefer already-loaded relation to avoid extra queries
                                                            $blList = isset($company->blDetails)
                                                                ? $company->blDetails
                                                                : (method_exists($company, 'blDetails') ? $company->blDetails()->get() : collect());
                                                        @endphp

                                                        @if($blList && $blList->count())
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered mb-0" style="font-size: 11px;">
                                                                    <thead class="bg-light">
                                                                        <tr>
                                                                            <th>{{ translate('BL No.') }}</th>
                                                                            <th>{{ translate('O/B Date') }}</th>
                                                                            <th>{{ translate('Vessel') }}</th>
                                                                            <th class="text-right">{{ translate('Pkgs') }}</th>
                                                                            <th class="text-right">{{ translate('Gross WT') }}</th>
                                                                            <th class="text-right">{{ translate('CBM') }}</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($blList as $bl)
                                                                            <tr>
                                                                                <td>{{ $bl->bl_no ?? '—' }}</td>
                                                                                <td>
                                                                                    @if(!empty($bl->ob_date))
                                                                                        {{ \Carbon\Carbon::parse($bl->ob_date)->format('d-m-Y') }}
                                                                                    @else
                                                                                        —
                                                                                    @endif
                                                                                </td>
                                                                                <td>{{ $bl->vessel_name ?? '—' }}</td>
                                                                                <td class="text-right">
                                                                                    {{ $bl->no_of_packages ?? 0 }}
                                                                                </td>
                                                                                <td class="text-right">
                                                                                    {{ $bl->gross_weight ?? 0 }}
                                                                                </td>
                                                                                <td class="text-right">
                                                                                    {{ $bl->gross_cbm ?? 0 }}
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @else
                                                            <small class="text-muted">
                                                                {{ translate('No BL created yet for this company.') }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="las la-info-circle mr-1"></i>
                                        {{ translate('No import companies found. Please add a new company.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination (Laravel) --}}
            @if(method_exists($companies, 'links'))
                <div class="card-footer py-3 bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center">
                        <div class="meta-pagination-info mb-2 mb-md-0">
                            @if($companies->total() > 0)
                                {{ translate('Showing') }}
                                <strong>{{ $companies->firstItem() }}</strong>
                                {{ translate('to') }}
                                <strong>{{ $companies->lastItem() }}</strong>
                                {{ translate('of') }}
                                <strong>{{ $companies->total() }}</strong>
                                {{ translate('companies') }}
                            @else
                                {{ translate('No records to display') }}
                            @endif
                        </div>
                        <div class="aiz-pagination mb-0">
                            {{ $companies->appends(request()->input())->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>

    {{-- BL Details Modal --}}
    <div class="modal fade" id="blDetailsModal" tabindex="-1" role="dialog" aria-labelledby="blDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
           <form action="{{ route('import_bl_details.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="import_company_id" id="bl_import_company_id">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="blDetailsModalLabel">
                            {{ translate('Add BL Details') }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        {{-- Company info display --}}
                        <div class="mb-3">
                            <strong>{{ translate('Company') }}:</strong>
                            <span id="bl_company_name" class="text-primary"></span>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>{{ translate('BL No.') }} <span class="text-danger">*</span></label>
                                <input type="text" name="bl_no" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>{{ translate('On Board Date') }}</label>
                                <input type="date" name="ob_date" class="form-control">
                            </div>
                            <div class="form-group col-md-4">
                                <label>{{ translate('Vessel Name') }}</label>
                                <input type="text" name="vessel_name" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>{{ translate('No. of Packages') }}</label>
                                <input type="number" step="1" name="no_of_packages" class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label>{{ translate('Gross Weight (kg)') }}</label>
                                <input type="number"
                                       step="0.001"
                                       name="gross_weight"
                                       id="bl_gross_weight"
                                       class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label>{{ translate('Net Weight (kg)') }}</label>
                                <input type="number"
                                       step="0.001"
                                       name="net_weight"
                                       id="bl_net_weight"
                                       class="form-control"
                                       readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>{{ translate('Gross CBM') }}</label>
                                <input type="number" step="0.0001" name="gross_cbm" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>{{ translate('Port of Loading') }}</label>
                                <input type="text" name="port_of_loading" class="form-control">
                            </div>
                            <div class="form-group col-md-6">
                                <label>{{ translate('Place of Delivery') }}</label>
                                <input type="text" name="place_of_delivery" class="form-control">
                            </div>
                        </div>

                        {{-- BL PDF upload --}}
                        <div class="form-group">
                            <label>{{ translate('BL PDF (optional)') }}</label>
                            <input type="file"
                                   name="bl_pdf"
                                   class="form-control-file"
                                   accept=".pdf">
                            <small class="text-muted">
                                {{ translate('Upload signed BL copy as PDF. Max size 5 MB.') }}
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-light" data-dismiss="modal">
                            {{ translate('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="las la-save mr-1"></i> {{ translate('Save BL Details') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- next mode start form here -->
</div>
@endsection

@section('script')
<script>
    (function ($) {
        "use strict";

        // "Add BL Details" from dropdown -> open modal
        $(document).on('click', '.js-add-bl-btn', function () {
            const companyId   = $(this).data('company-id');
            const companyName = $(this).data('company-name') || '';

            $('#bl_import_company_id').val(companyId);
            $('#bl_company_name').text(companyName);

            // clear old values
            $('#bl_gross_weight').val('');
            $('#bl_net_weight').val('');

            $('#blDetailsModal').modal('show');
        });

        // Gross Weight change -> Net Weight = 75% of Gross Weight
        $(document).on('input change blur', '#bl_gross_weight', function () {
            const gwRaw = $(this).val();
            const gw    = parseFloat(gwRaw);

            if (isNaN(gw) || gw <= 0) {
                $('#bl_net_weight').val('');
                return;
            }

            const nw = gw * 0.75; // 75%
            $('#bl_net_weight').val(nw.toFixed(3)); // 3 decimal places
        });

    })(jQuery);
</script>
@endsection