@extends('backend.layouts.app')

@section('content')
<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">{{ translate('Commercial Invoice Listing') }}</h5>
                <div class="small text-muted">
                    {{ translate('All CI headers with supplier-wise item details from CI tables') }}
                </div>
            </div>
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('import_ci.index') }}" class="mb-3">
            <div class="input-group">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="{{ $search ?? request('search') }}"
                    placeholder="{{ translate('Search Supplier Invoice No, BL No/ID, Importer, Supplier...') }}"
                >
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">
                        <i class="las la-search mr-1"></i>{{ translate('Search') }}
                    </button>

                    @if(request('search'))
                        <a href="{{ route('import_ci.index') }}"
                           class="btn btn-outline-danger">
                            <i class="las la-times"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Card --}}
        <div class="card">
            <div class="card-body">

                @if($ciHeaders->isEmpty())
                    <div class="alert alert-info mb-0">
                        {{ translate('No Commercial Invoices found.') }}
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 18%;">{{ translate('Importer') }}</th>
                                    <th style="width: 18%;">{{ translate('Supplier') }}</th>
                                    <th style="width: 10%;">{{ translate('BL No') }}</th>
                                    <th style="width: 13%;">{{ translate('Supplier Invoice No') }}</th>
                                    <th style="width: 12%;">{{ translate('Supplier Invoice Date') }}</th>
                                    <th class="text-right" style="width: 8%;">{{ translate('Packages') }}</th>
                                    <th class="text-right" style="width: 8%;">{{ translate('Gross Wt') }}</th>
                                    <th class="text-right" style="width: 8%;">{{ translate('Gross CBM') }}</th>
                                    <th class="text-center" style="width: 7%;">{{ translate('Items') }}</th>
                                    <th class="text-center" style="width: 7%;">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sl = ($ciHeaders->currentPage() - 1) * $ciHeaders->perPage() + 1;
                                @endphp

                                @foreach($ciHeaders as $ci)
                                    @php
                                        $company  = $ci->importCompany;
                                        $supplier = $ci->supplier;

                                        // ✅ BL display: real BL No from relation, fallback bl_id
                                        if (!empty(optional($ci->bl)->bl_no)) {
                                            $blDisplay = $ci->bl->bl_no;
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
                                    @endphp

                                    <tr>
                                        <td>{{ $sl++ }}</td>

                                        {{-- Importer --}}
                                        <td>
                                            @if($company)
                                                <div class="font-weight-600">
                                                    {{ $company->company_name ?? '-' }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $company->city ?? '' }}{{ $company->city && $company->country ? ', ' : '' }}{{ $company->country ?? '' }}
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>

                                        {{-- Supplier --}}
                                        <td>
                                            @if($supplier)
                                                <div class="font-weight-600">
                                                    {{ $supplier->supplier_name ?? '-' }}
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $supplier->city ?? '' }}{{ $supplier->city && $supplier->country ? ', ' : '' }}{{ $supplier->country ?? '' }}
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>

                                        {{-- BL No --}}
                                        <td>{{ $blDisplay }}</td>

                                        {{-- Supplier Invoice No & Date --}}
                                        <td>{{ $ci->supplier_invoice_no ?? '-' }}</td>
                                        <td>{{ $invDate }}</td>

                                        {{-- Totals --}}
                                        <td class="text-right">
                                            {{ $packages ? number_format($packages, 0) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $grossWt ? number_format($grossWt, 2) : '-' }}
                                        </td>
                                        <td class="text-right">
                                            {{ $grossCbm ? number_format($grossCbm, 3) : '-' }}
                                        </td>

                                        {{-- Items count --}}
                                        <td class="text-center">
                                            @if($items->isNotEmpty())
                                                <span class="badge badge-soft-primary">
                                                    {{ $items->count() }}
                                                </span>
                                            @else
                                                <span class="badge badge-soft-secondary">
                                                    {{ translate('No Items') }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- View button --}}
                                        <td class="text-center">
                                            <a href="{{ route('import_ci.show', $ci->id) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="las la-eye mr-1"></i>{{ translate('View') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="aiz-pagination mt-3">
                        {{ $ciHeaders->appends(['search' => request('search')])->links() }}
                    </div>
                @endif

            </div>
        </div>

    </div>
</div>
@endsection
