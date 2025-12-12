@extends('backend.layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h5>BTR Receipts</h5>
    </div>


    <div class="card-body">

        <form method="GET" action="{{ route('btr.receipts') }}" class="form-inline mb-3">
    <label for="filter" class="mr-2 font-weight-bold">Filter BTR:</label>
    <select name="filter" id="filter" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All BTR Orders</option>
        <option value="received" {{ $filter === 'received' ? 'selected' : '' }}>Only Received</option>
        <option value="pending" {{ $filter === 'pending' ? 'selected' : '' }}>Only Pending</option>
    </select>
</form>
        <table class="table table-bordered">
            <thead class="bg-success text-white">
                <tr>
                    <th>#</th>
                    <th>Invoice No</th>
                    <th>Party</th>
                    <th>Warehouse</th>
                    <th>Sale Order</th>
                    <th>Date</th>
                    <th>PDF</th>
                    <th>Receive</th>
                    <th>Products</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $key => $inv)
                    @php
                        $party = json_decode($inv->party_info, true);
                        $companyName = $party['company_name'] ?? 'N/A';
                        $orderNos = $inv->order_nos ?? [];
                        $collapseId = 'collapse_' . $key;
                    @endphp
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $inv->invoice_no }}</td>
                        <td>{{ $inv->party_display_name ?? $companyName }}</td>
                        <td>{{ $inv->warehouse->name ?? 'N/A' }}</td>
                        <td>
                            @if (!empty($orderNos))
                                {{ implode(', ', $orderNos) }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($inv->created_at)->format('d-m-Y') }}</td>
                        <td><a href="{{ route('invoice.downloadPdf', $inv->id) }}" class="btn btn-danger btn-sm" target="_blank">PDF</a></td>
                       <td>
                                @if ($inv->btr_received_status == 1)
                                    <span class="badge badge-info py-1 px-2" style="font-size: 11px; width:60px;">Received</span>
                                @else
                                    <a href="{{ route('btr.receive', ['invoice_id' => $inv->id]) }}"
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Are you sure you want to mark this as received?')">
                                        Receive
                                    </a>
                                @endif
                            </td>
                        <td class="text-center">
                            <button type="button"
                                    class="toggle-collapse-btn"
                                    data-toggle="collapse"
                                    data-target="#{{ $collapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $collapseId }}">
                                +
                            </button>
                        </td>
                    </tr>

                    <tr id="{{ $collapseId }}" class="collapse bg-light">
                        <td colspan="9">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="bg-secondary text-white">
                                    <tr>
                                        <th>Part No</th>
                                        <th>Item Name</th>
                                        <!-- <th>HSN</th> -->
                                        <th>GST</th>
                                        <th>Qty</th>
                                        <th>Rate</th>
                                        <th>Total</th>
                                        <th>Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($inv->invoice_products as $prod)
   
                                        <tr>
                                            <td>{{ $prod->part_no }}</td>
                                            <td>{{ $prod->item_name }}</td>
                                            <!-- <td>{{ $prod->hsn_no }}</td> -->
                                            <td>{{ $prod->gst }}%</td>
                                            <td>{{ $prod->billed_qty }}</td>
                                            <td>{{ number_format($prod->rate, 2) }}</td>
                                            <td>{{ number_format($prod->billed_amt, 2) }}</td>
                                            <td>
                                                <small class="text-info"><strong>To:</strong> {{ $prod->to_company_name ?? 'N/A' }}</small><br>
                                                 <small class="text-success"><strong>Order No:</strong> {{ $prod->sale_order_no }}</small><br>
                                               <small class="text-warning"><strong>Challan No:</strong> {{ $prod->challan_no }}</small>
                                            </td>

                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No BTR Receipts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleButtons = document.querySelectorAll('.toggle-collapse-btn');

        toggleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = document.querySelector(btn.getAttribute('data-target'));
                if (target.classList.contains('show')) {
                    btn.innerText = '+';
                } else {
                    btn.innerText = '-';
                }
            });
        });

        // Listen to collapse toggle globally (optional fix for Bootstrap toggling)
        $('.collapse').on('show.bs.collapse', function () {
            const btn = document.querySelector(`[data-target="#${this.id}"]`);
            if (btn) btn.innerText = '-';
        }).on('hide.bs.collapse', function () {
            const btn = document.querySelector(`[data-target="#${this.id}"]`);
            if (btn) btn.innerText = '+';
        });
    });
</script>
@endsection

@push('styles')
<style>
    .toggle-collapse-btn {
        width: 32px;
        height: 32px;
        font-size: 18px;
        font-weight: bold;
        line-height: 1;
        border: 1px solid #ccc;
        background-color: #f8f9fa;
        color: #000;
        border-radius: 4px;
        cursor: pointer;
        padding: 0;
    }

    .toggle-collapse-btn:focus {
        outline: none;
    }
</style>
@endpush
