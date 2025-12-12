@extends('frontend.layouts.user_panel')

@section('panel_content')
<div class="aiz-titlebar mt-2 mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1 class="h3">{{ translate('Order ID') }}: {{ $order->code }}</h1>
        </div>
    </div>
</div>

<!-- Order Summary -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">{{ translate('Order Summary') }}</h5>
    </div>
    <div class="card-body row">
        <div class="col-lg-6">
            <table class="table table-borderless">
                <tr><td class="fw-bold w-50">{{ translate('Order Code') }}:</td><td>{{ $order->code }}</td></tr>
                <tr><td class="fw-bold">{{ translate('Customer') }}:</td><td>{{ json_decode($order->shipping_address)->name ?? '-' }}</td></tr>
                @if ($order->user_id && !empty($order->user->email))
                <tr><td class="fw-bold">{{ translate('Email') }}:</td><td>{{ $order->user->email }}</td></tr>
                @endif
                <tr>
                    <td class="fw-bold">{{ translate('Shipping Address') }}:</td>
                    <td>
                        {{ json_decode($order->shipping_address)->address }},
                        {{ json_decode($order->shipping_address)->city }},
                        {{ json_decode($order->shipping_address)->state ?? '' }},
                        {{ json_decode($order->shipping_address)->postal_code }},
                        {{ json_decode($order->shipping_address)->country }}
                    </td>
                </tr>
            </table>
        </div>
        <div class="col-lg-6">
            <table class="table table-borderless">
                <tr><td class="fw-bold w-50">{{ translate('Order Date') }}:</td><td>{{ date('d-m-Y H:i A', $order->date) }}</td></tr>
                <tr><td class="fw-bold">{{ translate('Order Status') }}:</td><td>{{ translate($orderStatus) }}</td></tr>
                <tr><td class="fw-bold">{{ translate('Total Invoiced Amount') }}:</td><td>{{ single_price($totalInvoicedAmount ?? 0) }}</td></tr>
            </table>
        </div>
    </div>
</div>

<!-- Order Details -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">{{ translate('Order Details') }}</h5>
    </div>
    <div class="card-body p-0">
        @if (count($groupedDetails) > 0)
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="bg-light text-center">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('Product') }}</th>
                        <th>{{ translate('Part Number') }}</th>
                        <th>{{ translate('Ordered Quantity') }}</th>
                        <th>{{ translate('Approved Quantity') }}</th>
                        <th>{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groupedDetails as $part_number => $details)
                        @php $groupKey = \Str::random(5); @endphp
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>{{ $details[0]->product_name ?? '-' }}</td>
                            <td class="text-center">{{ $part_number }}</td>
                            <td class="text-center">{{ $details[0]->quantity ?? '-' }}</td>
                            <td class="text-center">{{ $details[0]->total_approved_qty ?? '-' }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary toggle-btn" data-target="#collapse-{{ $groupKey }}">+</button>
                            </td>
                        </tr>
                        <tr id="collapse-{{ $groupKey }}">
                            <td colspan="6" class="p-0 border-top-0">
                                <div class="collapse-content" style="display: none;">
                                    <div class="p-3 bg-white rounded border">
                                        <table class="table table-bordered table-sm mb-0">
                                            <thead class="thead-light text-center">
                                                <tr>
                                                    <th>{{ translate('Billed Quantity') }}</th>
                                                    <th>{{ translate('Rate') }}</th>
                                                    <th>{{ translate('Price') }}</th>
                                                    <th>{{ translate('Invoice No') }}</th>
                                                    @php
                                                        $hasCancelled = collect($details)->contains(function ($item) {
                                                            return strtolower($item->status) === 'cancelled';
                                                        });
                                                    @endphp
                                                    <th>
                                                        {{ $hasCancelled ? translate('Cancelled From') : translate('Place of Dispatch') }}
                                                    </th>
                                                    <th>{{ translate('Status') }}</th>
                                                    <th>{{ translate('Actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($details as $detail)
                                                <tr class="text-center">
                                                    <td>{{ $detail->billed_qty ?? 'N/A' }}</td>
                                                    <td>{{ number_format((float)($detail->rate ?? 0), 2) }}</td>
                                                    <td>{{ number_format((float)($detail->price ?? 0), 2) }}</td>
                                                    <td>
                                                        {{ $detail->invoice_no ?? 'N/A' }}
                                                        @if (!empty($detail->invoice_date))<br><small>({{ $detail->invoice_date }})</small>@endif
                                                    </td>
                                                    <td>{{ $detail->dispatch_id ?? 'N/A' }}</td>
                                                    <td>
                                                        <span  style="width:auto;" class="badge 
                                                        @if ($detail->status === 'Completed') badge-success
                                                        @elseif ($detail->status === 'Pending for Dispatch') badge-warning
                                                        @elseif ($detail->status === 'Material in transit') badge-primary
                                                        @elseif ($detail->status === 'Internal Branch Transit') badge-info
                                                        @elseif ($detail->status === 'Cancelled') badge-danger
                                                        @else badge-secondary
                                                        @endif">
                                                        {{ $detail->status ?? 'N/A' }}
                                                    </span>
                                                    </td>
                                                    <td>
                                                        @if ($detail->status === 'Completed' && !empty($detail->invoice_no))
                                                            @php 
                                                                $logistics = DB::table('order_logistics')->where('invoice_no', $detail->invoice_no)->first();
                                                                $attachments = $logistics ? explode(',', $logistics->attachment ?? '') : [];
                                                            @endphp

                                                            <div style="display: inline-flex; gap: 5px;">
                                                                <a href="{{ route('generate.invoice', ['invoice_no' => encrypt($detail->invoice_no)]) }}"
                                                                   target="_blank"
                                                                   style="background-color: #28a745; color: white; padding: 5px 10px; border-radius: 12px; font-size: 12px; text-decoration: none;">
                                                                    Invoice
                                                                </a>

                                                                @if (!empty($attachments[0]))
                                                                    <a href="{{ $attachments[0] }}"
                                                                       target="_blank"
                                                                       style="background-color: #fd7e14; color: white; padding: 5px 10px; border-radius: 12px; font-size: 12px; text-decoration: none;">
                                                                        Logistic
                                                                    </a>
                                                                @endif
                                                            </div>

                                                        @else
                                                            <span style="color: #6c757d;">No Actions</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-center m-4">{{ translate('No order details available.') }}</p>
        @endif
    </div>
</div>

<!-- Order Amount -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">{{ translate('Order Amount') }}</h5>
    </div>
    <div class="card-body row">
        <div class="col-lg-6">
            <table class="table table-borderless">
                <tr><td class="fw-bold w-50">{{ translate('Subtotal') }}</td><td>{{ single_price($totalInvoicedAmount ?? 0) }}</td></tr>
                <tr><td class="fw-bold">{{ translate('Shipping') }}</td><td>{{ single_price($order->shipping_cost ?? 0) }}</td></tr>
            </table>
        </div>
        <div class="col-lg-6">
            <table class="table table-borderless">
                <tr><td class="fw-bold w-50">{{ translate('Total Amount') }}</td><td>{{ single_price($totalInvoicedAmount ?? 0) }}</td></tr>
            </table>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function () {
        $('.toggle-btn').on('click', function () {
            const btn = $(this);
            const targetId = btn.data('target');
            const content = $(targetId).find('.collapse-content');

            if (content.is(':visible')) {
                content.slideUp(300);
                btn.text('+');
            } else {
                content.slideDown(300);
                btn.text('-');
            }
        });
    });
</script>
@endsection
