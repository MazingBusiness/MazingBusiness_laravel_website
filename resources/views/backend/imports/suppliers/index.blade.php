@extends('backend.layouts.app')

@section('content')
<style>
    .page-header-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .page-title-meta {
        display: flex;
        flex-direction: column;
    }

    .page-title-meta h4 {
        font-weight: 600;
        margin-bottom: 2px;
    }

    .meta-subtitle {
        font-size: 12px;
        color: #6b7280;
    }

    .meta-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .meta-search-group .form-control-sm {
        border-radius: 999px 0 0 999px;
    }

    .meta-search-group .btn-sm {
        border-radius: 0 999px 999px 0;
    }

    .supplier-list-wrap {
        background: #f3f4f6;
        border-radius: 18px;
        padding: 16px 16px 8px;
    }

    .supplier-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        padding: 14px 16px;
        margin-bottom: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-start;
        transition: box-shadow 0.15s ease, transform 0.1s ease, border-color 0.15s ease;
    }

    .supplier-card:hover {
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    .supplier-avatar {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f9fafb;
        font-size: 16px;
        font-weight: 700;
        flex-shrink: 0;
        text-transform: uppercase;
    }

    .supplier-main {
        flex: 1;
        min-width: 0;
    }

    .supplier-name-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 3px;
    }

    .supplier-name {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 280px;
    }

    .supplier-tag {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .supplier-address {
        font-size: 12px;
        color: #6b7280;
        max-width: 360px;
        word-wrap: break-word;
    }

    .supplier-meta-row {
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 11px;
        color: #4b5563;
    }

    .supplier-meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 999px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
    }

    .supplier-meta-pill i {
        font-size: 12px;
    }

    .supplier-right {
        text-align: right;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        min-width: 170px;
    }

    .supplier-id {
        font-size: 11px;
        color: #9ca3af;
    }

    .supplier-stats {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .supplier-stat-pill {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .supplier-stat-pill.bl {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .supplier-stat-pill.ci {
        background: #ecfeff;
        color: #0e7490;
    }

    .supplier-stat-pill.cisum {
        background: #ecfdf3;
        color: #15803d;
    }

    .supplier-stat-pill span.badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: currentColor;
    }

    .supplier-email-link {
        font-size: 11px;
        text-decoration: none;
        color: #2563eb;
        word-break: break-all;
    }

    .supplier-email-link:hover {
        text-decoration: underline;
    }

    .supplier-empty {
        padding: 40px 0;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }

    .supplier-empty i {
        font-size: 32px;
        display: block;
        margin-bottom: 8px;
    }

    .meta-pagination-footer {
        border-top: 1px solid #e5e7eb;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 12px;
    }

    /* Collapsible extra section */
    .supplier-extra {
        flex-basis: 100%;
        margin-top: 6px;
        font-size: 11px;
    }

    .supplier-extra-inner {
        border-top: 1px solid #e5e7eb;
        margin-top: 6px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #f9fafb;
    }

    .supplier-extra-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .supplier-extra-table {
        width: 100%;
        font-size: 11px;
    }

    .supplier-extra-table th {
        width: 120px;
        font-weight: 600;
        color: #374151;
        padding: 2px 0;
        vertical-align: top;
    }

    .supplier-extra-table td {
        padding: 2px 0;
        color: #111827;
    }

    /* Stamp card */
    .supplier-stamp-card {
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

    .supplier-stamp-card img {
        max-width: 120px;
        max-height: 80px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        object-fit: contain;
        background: #f9fafb;
    }

    .supplier-stamp-card small {
        font-size: 11px;
    }

    .bank-mini-card {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        padding: 8px 10px;
        margin-bottom: 8px;
    }

    .bank-mini-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
        margin-bottom: 6px;
    }

    .bank-mini-header span.badge-currency {
        padding: 2px 6px;
        border-radius: 999px;
        background: #eef2ff;
        color: #1d4ed8;
        font-size: 10px;
        font-weight: 600;
    }

    .bank-mini-header span.badge-default {
        padding: 2px 6px;
        border-radius: 999px;
        background: #fef3c7;
        color: #92400e;
        font-size: 10px;
        font-weight: 600;
    }

    .bank-section-heading {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 2px;
        letter-spacing: 0.05em;
    }

    .bank-mini-body small {
        display: block;
        margin-bottom: 1px;
        color: #374151;
    }

    .toggle-details-btn {
        font-size: 11px;
        padding: 3px 9px;
        border-radius: 999px;
    }
</style>

<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-header-meta">
                    <div class="page-title-meta">
                        <h4 class="mb-0">Suppliers</h4>
                        <span class="meta-subtitle">
                            Central list of import suppliers for BL / CI / Packing List workflows.
                        </span>
                    </div>

                    <div class="meta-actions">
                        {{-- SEARCH FORM --}}
                        <form action="{{ route('import_suppliers.index') }}" method="GET" class="meta-search-group d-flex">
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       name="search"
                                       class="form-control form-control-sm"
                                       placeholder="Search suppliers by name, city, email..."
                                       value="{{ request('search') }}">
                                <div class="input-group-append">
                                    <button class="btn btn-sm btn-primary" type="submit">
                                        <i class="las la-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <a href="{{ route('import_suppliers.create') }}" class="btn btn-sm btn-primary">
                            <i class="las la-plus-circle mr-1"></i> Add Supplier
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        {{-- Listing Card --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="supplier-list-wrap">

                    @forelse ($suppliers as $index => $supplier)
                        @php
                            $serial     = ($suppliers->currentPage() - 1) * $suppliers->perPage() + $index + 1;
                            $initials   = strtoupper(mb_substr($supplier->supplier_name ?? 'S', 0, 1));
                            $blCount    = $supplier->bl_count ?? 0;
                            $ciCount    = $supplier->ci_count ?? 0;
                            $ciSumCount = $supplier->ci_summary_count ?? 0;
                            $detailsId  = 'supplier-details-' . $supplier->id;

                            // stamp path is partial (e.g. "import_suppliers_stamps/xyz.png")
                            $stampExt = $supplier->stamp
                                ? strtolower(pathinfo($supplier->stamp, PATHINFO_EXTENSION))
                                : null;
                            $stampUrl = $supplier->stamp
                                ? url('public/' . ltrim($supplier->stamp, '/'))
                                : null;
                        @endphp

                        <div class="supplier-card">

                            {{-- Avatar --}}
                            <div class="supplier-avatar">
                                {{ $initials }}
                            </div>

                            {{-- Main content --}}
                            <div class="supplier-main">
                                <div class="supplier-name-row">
                                    <div class="supplier-name" title="{{ $supplier->supplier_name }}">
                                        {{ $supplier->supplier_name }}
                                    </div>
                                    <span class="supplier-tag">
                                        SUPPLIER #{{ $serial }}
                                    </span>
                                </div>

                                @if($supplier->address || $supplier->city || $supplier->district || $supplier->country)
                                    <div class="supplier-address">
                                        @if($supplier->address)
                                            {{ $supplier->address }}
                                        @endif

                                        @php
                                            $locParts = [];
                                            if ($supplier->city)     $locParts[] = $supplier->city;
                                            if ($supplier->district) $locParts[] = $supplier->district;
                                            if ($supplier->country)  $locParts[] = $supplier->country;
                                        @endphp

                                        @if(!empty($locParts))
                                            <br>
                                            <span>{{ implode(', ', $locParts) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="supplier-address text-muted">
                                        <em>No address details added yet.</em>
                                    </div>
                                @endif

                                <div class="supplier-meta-row">
                                    <div class="supplier-meta-pill">
                                        <i class="las la-phone-alt"></i>
                                        @if($supplier->contact)
                                            <span>{{ $supplier->contact }}</span>
                                        @else
                                            <span class="text-muted">No phone</span>
                                        @endif
                                    </div>

                                    <div class="supplier-meta-pill">
                                        <i class="las la-envelope"></i>
                                        @if($supplier->email)
                                            <a href="mailto:{{ $supplier->email }}" class="supplier-email-link">
                                                {{ $supplier->email }}
                                            </a>
                                        @else
                                            <span class="text-muted">No email</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Right side stats + toggle --}}
                            <div class="supplier-right">
                                <div class="supplier-id">
                                    ID: {{ $supplier->id }}
                                </div>

                                <div class="supplier-stats">
                                    <div class="supplier-stat-pill bl">
                                        <span class="badge-dot"></span>
                                        <span>BL</span>
                                        <strong>{{ $blCount }}</strong>
                                    </div>
                                    <div class="supplier-stat-pill ci">
                                        <span class="badge-dot"></span>
                                        <span>CI</span>
                                        <strong>{{ $ciCount }}</strong>
                                    </div>
                                    <div class="supplier-stat-pill cisum">
                                        <span class="badge-dot"></span>
                                        <span>CI Summary</span>
                                        <strong>{{ $ciSumCount }}</strong>
                                    </div>
                                </div>

                                <button class="btn btn-xs btn-outline-secondary toggle-details-btn"
                                        type="button"
                                        data-toggle="collapse"
                                        data-target="#{{ $detailsId }}"
                                        aria-expanded="false"
                                        aria-controls="{{ $detailsId }}">
                                    <i class="las la-chevron-down"></i> Details
                                </button>
                                <a href="{{ route('import_suppliers.edit', $supplier->id) }}" class="btn btn-sm btn-outline-primary">
                                    {{ translate('Edit') }}
                                </a>
                            </div>

                            {{-- Collapsible extra details --}}
                            <div class="collapse supplier-extra" id="{{ $detailsId }}">
                                <div class="supplier-extra-inner">
                                    <div class="row">
                                        {{-- OTHER DETAILS + STAMP --}}
                                        <div class="col-md-5 mb-2">
                                            <div class="supplier-extra-title">OTHER DETAILS</div>
                                            <table class="supplier-extra-table">
                                                <tr>
                                                    <th>Country</th>
                                                    <td>{{ $supplier->country ?: '–' }}</td>
                                                </tr>
                                                <tr>
                                                    <th>State / District</th>
                                                    <td>{{ $supplier->district ?: '–' }}</td>
                                                </tr>
                                                <tr>
                                                    <th>City</th>
                                                    <td>{{ $supplier->city ?: '–' }}</td>
                                                </tr>
                                                <tr>
                                                    <th>ZIP / Postal Code</th>
                                                    <td>{{ $supplier->zip_code ?: '–' }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Created At</th>
                                                    <td>
                                                        {{ $supplier->created_at
                                                            ? $supplier->created_at->format('d M Y, h:i A')
                                                            : '–' }}
                                                    </td>
                                                </tr>
                                            </table>

                                            {{-- Supplier Stamp --}}
                                            <div class="supplier-extra-title mt-3">SUPPLIER STAMP</div>
                                            @if($stampUrl)
                                                <div class="supplier-stamp-card">
                                                    @if(in_array($stampExt, ['jpg','jpeg','png','gif','webp']))
                                                        <img src="{{ $stampUrl }}" alt="Supplier Stamp">
                                                        <small class="text-muted">
                                                            Image stamp &middot;
                                                            <a href="{{ $stampUrl }}" target="_blank">Open full size</a>
                                                        </small>
                                                    @elseif($stampExt === 'pdf')
                                                        <small>
                                                            <i class="las la-file-pdf mr-1"></i>
                                                            PDF stamp
                                                        </small>
                                                        <small>
                                                            <a href="{{ $stampUrl }}" target="_blank">
                                                                View PDF
                                                            </a>
                                                        </small>
                                                    @else
                                                        <small class="text-muted">
                                                            Stamp file uploaded
                                                            <a href="{{ $stampUrl }}" target="_blank">
                                                                (Open)
                                                            </a>
                                                        </small>
                                                    @endif
                                                </div>
                                            @else
                                                <small class="text-muted">
                                                    No stamp uploaded for this supplier.
                                                </small>
                                            @endif
                                        </div>

                                        {{-- BANK ACCOUNTS --}}
                                        <div class="col-md-7 mb-2">
                                            <div class="supplier-extra-title">BANK ACCOUNTS</div>

                                            @if(isset($supplier->bankAccounts) && $supplier->bankAccounts->count())
                                                @foreach($supplier->bankAccounts as $bank)
                                                    <div class="bank-mini-card">
                                                        <div class="bank-mini-header">
                                                            <span class="badge-currency">{{ $bank->currency ?? 'USD' }}</span>
                                                            @if($bank->is_default)
                                                                <span class="badge-default">Default</span>
                                                            @endif
                                                        </div>
                                                        <div class="bank-mini-body">
                                                            {{-- Intermediary Bank --}}
                                                            @if($bank->intermediary_bank_name || $bank->intermediary_swift_code)
                                                                <div class="mb-1">
                                                                    <div class="bank-section-heading">INTERMEDIARY BANK</div>
                                                                    @if($bank->intermediary_bank_name)
                                                                        <small>
                                                                            <strong>Bank:</strong>
                                                                            {{ $bank->intermediary_bank_name }}
                                                                        </small>
                                                                    @endif
                                                                    @if($bank->intermediary_swift_code)
                                                                        <small>
                                                                            <strong>SWIFT:</strong>
                                                                            {{ $bank->intermediary_swift_code }}
                                                                        </small>
                                                                    @endif
                                                                </div>
                                                            @endif

                                                            {{-- Account Bank --}}
                                                            @if($bank->account_bank_name || $bank->account_swift_code || $bank->account_bank_address)
                                                                <div class="mb-1">
                                                                    <div class="bank-section-heading">ACCOUNT BANK</div>
                                                                    @if($bank->account_bank_name)
                                                                        <small>
                                                                            <strong>Bank:</strong>
                                                                            {{ $bank->account_bank_name }}
                                                                        </small>
                                                                    @endif
                                                                    @if($bank->account_swift_code)
                                                                        <small>
                                                                            <strong>SWIFT:</strong>
                                                                            {{ $bank->account_swift_code }}
                                                                        </small>
                                                                    @endif
                                                                    @if($bank->account_bank_address)
                                                                        <small>
                                                                            <strong>Address:</strong>
                                                                            {{ $bank->account_bank_address }}
                                                                        </small>
                                                                    @endif
                                                                </div>
                                                            @endif

                                                            {{-- Beneficiary --}}
                                                            @if($bank->beneficiary_name || $bank->beneficiary_address || $bank->account_number)
                                                                <div class="mb-0">
                                                                    <div class="bank-section-heading">BENEFICIARY</div>
                                                                    @if($bank->beneficiary_name)
                                                                        <small>
                                                                            <strong>Name:</strong>
                                                                            {{ $bank->beneficiary_name }}
                                                                        </small>
                                                                    @endif
                                                                    @if($bank->beneficiary_address)
                                                                        <small>
                                                                            <strong>Address:</strong>
                                                                            {{ $bank->beneficiary_address }}
                                                                        </small>
                                                                    @endif
                                                                    @if($bank->account_number)
                                                                        <small>
                                                                            <strong>A/C No:</strong>
                                                                            {{ $bank->account_number }}
                                                                        </small>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <small class="text-muted">
                                                    No bank accounts added for this supplier.
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @empty
                        <div class="supplier-empty">
                            <i class="las la-clipboard-list"></i>
                            No suppliers found. Start by adding your first supplier.
                        </div>
                    @endforelse

                </div>
            </div>

            {{-- Pagination --}}
            <div class="meta-pagination-footer">
                <div>
                    @if($suppliers->total() > 0)
                        Showing
                        <strong>{{ $suppliers->firstItem() }}</strong>
                        to
                        <strong>{{ $suppliers->lastItem() }}</strong>
                        of
                        <strong>{{ $suppliers->total() }}</strong>
                        suppliers
                    @else
                        <span>No suppliers to display</span>
                    @endif
                </div>
                <div>
                    {{ $suppliers->links() }}
                </div>
            </div>
        </div>

    </div>
</div>
@endsection