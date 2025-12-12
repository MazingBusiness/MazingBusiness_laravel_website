@extends('backend.layouts.app')

@section('content')

<style>
/* Stylish Table */
.table-modern {
    width: 100%;
    background-color: #fff;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.04);
}
.table-modern thead {
    background-color: #358DB0;
    color: #fff;
    font-weight: 600;
}
.table-modern th,
.table-modern td {
    padding: 12px 10px;
    vertical-align: middle;
    text-align: center;
}
.table-modern tbody tr:hover {
    background-color: #f5faff;
}

.toggle-btn {
    border: none;
    background: none;
    color: #358DB0;
    font-size: 20px;
    cursor: pointer;
}

.details-row {
    background-color: #f9f9f9;
    transition: all 0.3s ease-in-out;
}
.details-row td {
    padding: 0;
    border: none;
}
.inner-table th,
.inner-table td {
    padding: 8px;
    font-size: 13px;
}
.inner-table th {
    background-color: #174ba9;
    color: white;
}
tfoot td {
    font-weight: 600;
}
.download-btn {
    font-weight: 500;
    padding: 5px 12px;
    font-size: 13px;
    border-radius: 6px;
}
</style>

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3 text-dark">📦 Purchase Invoices</h1>
</div>

<div class="card">

    <div class="card-body">

           <form method="GET" action="{{ route('purchase.invoices.list') }}" class="mb-4">
                <div class="input-group" style="max-width: 400px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by PN No" value="{{ request('search') }}">
                    <div class="input-group-append">
                         @if(request('search'))
                            <a href="{{ route('purchase.invoices.list') }}" class="btn btn-outline-secondary" title="Clear Search">
                                &times;
                            </a>
                        @endif
                        <button class="btn btn-primary" type="submit">
                            <i class="las la-search"></i> Search
                        </button>
                       
                    </div>
                </div>
            </form>
        <table class="table table-modern">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Purchase No</th>
                    <th>PO No</th>
                    <th>Supplier Info</th>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Products</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchases as $index => $purchase)
                    <tr @if (is_null($purchase->zoho_bill_id)) style="background-color: #fff8dc;" @endif>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $purchase->purchase_no }}</strong></td>
                        <td>{!! str_replace(',', '<br>', $purchase->purchase_order_no) !!}</td>
                        <td class="text-left">
                            @if ($purchase->address)
                                @php
                                    $company = $purchase->address->company_name ?? null;
                                    $phone = $purchase->address->phone ?? null;
                                    $fullAddress = trim(($purchase->address->address ?? '') . ', ' . ($purchase->address->city ?? '') . ', ' . ($purchase->address->state->name ?? ''));
                                @endphp

                                @if ($company || $phone || $fullAddress)
                                    <strong>{{ $company ?: 'No data found' }}</strong><br>
                                    <small><i class="las la-phone"></i> {{ $phone ?: 'No data found' }}</small><br>
                                    <small><i class="las la-map-marker"></i> {{ $fullAddress !== ', ,' ? $fullAddress : 'No data found' }}</small>
                                @else
                                    <span class="text-muted">No data found</span>
                                @endif
                            @elseif (!empty($purchase->seller_info))
                                <strong>{{ $purchase->seller_info['seller_name'] ?? 'No data found' }}</strong><br>
                                <small><i class="las la-phone"></i> {{ $purchase->seller_info['seller_phone'] ?? 'No data found' }}</small><br>
                                <small><i class="las la-map-marker"></i> {{ $purchase->seller_info['seller_address'] ?? 'No data found' }}</small>
                            @else
                                <span class="text-muted">No data found</span>
                            @endif
                        </td>

                        <td>{{ $purchase->seller_invoice_no }}</td>
                        <td>{{ $purchase->seller_invoice_date }}</td>
                        <td>
                            <button class="toggle-btn" onclick="toggleDetails({{ $index }})" id="btn-{{ $index }}">
                                <i class="las la-plus-circle"></i>
                            </button>
                        </td>
                      <td class="text-center" style="white-space: nowrap;">
                        <a href="{{ route('purchase.invoice.export', $purchase->id) }}" class="text-success mr-3" title="Download Excel" style="font-size: 15px; display: inline-flex; align-items: center;">
                            <i class="las la-file-excel" style="font-size: 22px; margin-right: 4px;"></i> Excel
                        </a>
                        <a href="{{ route('purchase.invoice.pdf', $purchase->id) }}" class="text-danger" title="Download PDF" style="font-size: 15px; display: inline-flex; align-items: center;">
                            <i class="las la-file-pdf" style="font-size: 22px; margin-right: 4px;"></i> PDF
                        </a>

                         <a href="{{ route('admin.editManualPurchaseOrder', $purchase->id) }}" class="btn btn-sm btn-warning"  style="background: none; border:none;" title="Edit Purchase Invoice">
                                <i class="las la-pen"></i> Edit
                            </a>
                       @if ($purchase->purchase_invoice_type === 'customer' && $purchase->zoho_creditnote_id)

                            @if ($purchase->credit_note_irp_status == 1)
                                {{-- ✅ IRN Generated → Show Cancel Button --}}
                                <button style="border: none;" class="text-danger cancel-irp-btn"
                                    data-id="{{ $purchase->zoho_creditnote_id }}"
                                    title="Cancel IRP" style="font-size: 15px; display: inline-flex; align-items: center; margin-left: 10px;">
                                    <i class="las la-times-circle" style="font-size: 22px; margin-right: 4px;"></i> Cancel IRP
                                </button>
                            @else
                                {{-- ❗ Not Generated → Show Push Button --}}
                                <button style="border:none;" class="text-primary irp-btn"
                                    data-id="{{ $purchase->zoho_creditnote_id }}"
                                    title="Push to IRP" style="font-size: 15px; display: inline-flex; align-items: center; margin-left: 10px;">
                                    <i class="las la-file-alt" style="font-size: 22px; margin-right: 4px;"></i> Generate IRP
                                </button>
                            @endif

                        @endif
                    </td>

                    </tr>

                    <tr class="collapse-row details-row" id="row-{{ $index }}" style="display: none;">
                        <td colspan="8">
                            <div class="p-3">
                                <h6 class="text-dark mb-2">🧾 Product Details</h6>
                                <table class="table inner-table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>PO No</th>
                                            <th>Part No</th>
                                            <th>HSN</th>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Rate</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $total = 0; @endphp
                                        @foreach($purchase->purchaseInvoiceDetails as $i => $d)
                                            @php
                                                //$product = \App\Models\Product::where('part_no', $d->part_no)->first();
                                                $taxed = round($d->price + ($d->price * $d->tax / 100), 2);
                                                $sub = $taxed * $d->qty;
                                                $total += $sub;
                                            @endphp
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $d->purchase_order_no }}</td>
                                                <td>{{ $d->part_no }}</td>
                                                <td>{{ $d->hsncode }}</td>
                                                <td>{{ $products[$d->part_no]->name ?? 'N/A' }}</td>
                                                <td>{{ $d->qty }}</td>
                                                <td>₹{{ number_format($taxed, 2) }}</td>
                                                <td class="text-right">₹{{ number_format($sub, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="7" class="text-right">Total</td>
                                            <td class="text-right font-weight-bold">₹{{ number_format($total, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="row align-items-center mt-3">
            <div class="col-md-6 text-muted">
                Showing {{ $purchases->firstItem() ?? 0 }} to {{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }} entries
            </div>
            <div class="col-md-6 d-flex justify-content-end">
                {!! $purchases->links('pagination::bootstrap-4') !!}
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
function toggleDetails(index) {
    const row = document.getElementById('row-' + index);
    const btn = document.getElementById('btn-' + index).querySelector('i');
    const isOpen = row.style.display === 'table-row';

    row.style.display = isOpen ? 'none' : 'table-row';
    btn.classList.toggle('la-plus-circle', isOpen);
    btn.classList.toggle('la-minus-circle', !isOpen);
}


$(document).on('click', '.irp-btn', function () {
    let creditnoteId = $(this).data('id');

    if (!creditnoteId) {
        AIZ.plugins.notify('warning', 'Credit Note ID missing.');
        return;
    }

    if (!confirm('Are you sure you want to push this Credit Note to IRP?')) return;

    $.ajax({
        url: '/zoho/creditnote/generate-irp/' + creditnoteId,
        method: 'GET',
        beforeSend: function () {
            AIZ.plugins.notify('info', 'Pushing to IRP...');
        },
        success: function (response) {
            console.log('IRP Response:', response);

            let status = response.status || 'info';
            let msg = response.message ?? JSON.stringify(response) ?? 'No message returned from server.';

            if (status === 'success') {
                AIZ.plugins.notify('success', msg);
            } else if (status === 'info') {
                AIZ.plugins.notify('warning', msg);
            } else {
                AIZ.plugins.notify('danger', msg);
            }
        },
        error: function (xhr) {
            let msg = 'Failed to push IRP.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    msg = res.message || msg;
                } catch (e) {
                    // not JSON, leave default msg
                }
            }
            AIZ.plugins.notify('danger', msg);
        }
    });
});




</script>
@endsection
