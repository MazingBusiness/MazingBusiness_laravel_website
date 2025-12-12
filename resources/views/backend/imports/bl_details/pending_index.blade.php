@extends('backend.layouts.app')

@section('content')
<div class="aiz-main-content">
    <div class="px-15px px-lg-25px">
        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">{{ translate('Pending Bills of Lading') }}</h5>
                <div class="small text-muted">
                    {{ translate('Showing BL records with status "pending".') }}
                </div>
            </div>
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('import_bl_details.pending') }}" class="mb-3">
            <div class="input-group">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="{{ $search ?? request('search') }}"
                    placeholder="{{ translate('Search BL No, Supplier, Import Company...') }}"
                >

                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">
                        <i class="las la-search mr-1"></i>{{ translate('Search') }}
                    </button>

                    @if(request('search'))
                        <a href="{{ route('import_bl_details.pending') }}"
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

                @if($blHeaders->isEmpty())
                    <div class="alert alert-info mb-0">
                        {{ translate('No pending BL found.') }}
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>{{ translate('BL No') }}</th>
                                    <!-- <th>{{ translate('Supplier') }}</th> -->
                                    <th>{{ translate('Import Company') }}</th>
                                    <th>{{ translate('OB Date') }}</th>
                                    <th class="text-right">{{ translate('Packages') }}</th>
                                    <th class="text-right">{{ translate('Gross Wt') }}</th>
                                    <th class="text-right">{{ translate('CBM') }}</th>
                                    <th class="text-center">{{ translate('Items') }}</th>
                                    <th style="width: 80px;" class="text-center">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($blHeaders as $index => $bl)
                                    @php
                                        $rowId = 'bl-items-' . $bl->id;
                                        $items = $bl->items ?? collect();
                                    @endphp

                                    {{-- Header row --}}
                                    <tr>
                                        <td>{{ $blHeaders->firstItem() + $index }}</td>
                                        <td>{{ $bl->bl_no ?? '-' }}</td>
                                       {{-- <td>{{ optional($bl->supplier)->supplier_name ?? '-' }}</td>--}}
                                        <td>{{ optional($bl->importCompany)->company_name ?? '-' }}</td>
                                        <td>
                                            @if($bl->ob_date)
                                                {{ \Carbon\Carbon::parse($bl->ob_date)->format('d/m/Y') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($bl->no_of_packages ?? 0), 0) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($bl->gross_weight ?? 0), 2) }}
                                        </td>
                                        <td class="text-right">
                                            {{ number_format((float)($bl->gross_cbm ?? 0), 3) }}
                                        </td>
                                        <td class="text-center">
                                            <span style="width:auto;" class="badge badge-soft-primary">
                                                {{ $items->count() }} {{ translate('items') }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                          
                                                <a class="btn btn-xs btn-outline-primary"
                                                   href="{{ route('import_bl_details.pending.show', $bl->id) }}">
                                                    <i class="las la-eye mr-1"></i>
                                                </a>
                                            
                                        </td>
                                    </tr>

                                    {{-- Collapsible item row --}}
                                    <tr class="collapse" id="{{ $rowId }}">
                                        <td colspan="10" class="bg-soft-secondary p-0">
                                            <div class="p-3">
                                                <h6 class="mb-2">
                                                    {{ translate('BL Items for') }}:
                                                    <span class="font-weight-bold">
                                                        {{ $bl->bl_no ?? ('BL#' . $bl->id) }}
                                                    </span>
                                                </h6>

                                                @if($items->isEmpty())
                                                    <div class="alert alert-soft-warning mb-0">
                                                        {{ translate('No items found for this BL.') }}
                                                    </div>
                                                @else
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-striped mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 40px;">#</th>
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
                                                                @foreach($items as $iIndex => $item)
                                                                    @php
                                                                        $productLabel = '-';
                                                                        if ($item->product) {
                                                                            $productLabel = trim(
                                                                                ($item->product->part_no ?? '') . ' ' .
                                                                                ($item->product->name ?? '')
                                                                            );
                                                                        }
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $iIndex + 1 }}</td>
                                                                        <td>{{ $productLabel }}</td>
                                                                        <td>{{ $item->item_name ?? '-' }}</td>
                                                                        <td class="text-right">
                                                                            {{ number_format((float)($item->quantity ?? 0), 0) }}
                                                                        </td>
                                                                        <td class="text-right">
                                                                            {{ number_format((float)($item->dollar_price ?? 0), 2) }}
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
                                                        </table>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $blHeaders->appends(['search' => request('search')])->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
