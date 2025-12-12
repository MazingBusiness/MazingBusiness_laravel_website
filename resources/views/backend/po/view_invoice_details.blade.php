@extends('backend.layouts.app')

@section('content')
<style type="text/css">
    .section-heading {
    background-color: #358DB0 !important;
    color: #fff;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 18px;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    margin-bottom: 20px;

    /* Animation */
    opacity: 0;
    animation: fadeInUp 0.6s ease-in-out forwards;
    animation-delay: 0.2s;
}


  .table-wrapper {
        position: relative;
        min-height: 250px;
    }

   .table-loader {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7); /* light overlay */
    backdrop-filter: blur(2px); /* optional: slight blur */
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: opacity 0.4s ease, visibility 0.4s ease;
}

.table-loader.hide-loader {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.spinner-border {
    width: 3.5rem;
    height: 3.5rem;
    border-width: 0.4em;
    color: #358DB0;
}

    .fade-in-table {
        animation: fadeSlideUp 0.6s ease-in-out;
    }

    @keyframes fadeSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .styled-invoice-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        background-color: #fff;
        font-size: 14px;
    }

    .styled-invoice-table thead {
        background-color: #358DB0;
        color: #ffffff;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.5px;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.15);
    }

    .styled-invoice-table thead th {
        padding: 14px 10px;
        vertical-align: middle;
    }

    .styled-invoice-table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .styled-invoice-table tbody tr:hover {
        background-color: #eef4ff;
        transition: background-color 0.2s ease-in-out;
    }

    .readonly-field {
        border: none;
        background-color: transparent;
        box-shadow: none;
        pointer-events: none;
        font-weight: 500;
        color: #333;
    }

    .badge-soft-primary {
        background-color: rgba(53, 141, 176, 0.1);
        color: #358DB0;
        font-size: 13px;
        border-radius: 8px;
    }

    .total-footer {
        background-color: #eaf4fb;
        border-top: 2px solid #d1e5f1;
    }

    .total-footer td {
        padding: 16px;
        font-size: 15px;
        color: #333;
        font-weight: 600;
        vertical-align: middle;
    }

    .total-footer td:last-child {
        font-size: 20px;
        font-weight: 700;
        color: #2e7ca0;
    }

</style>
<div class="aiz-titlebar text-left mt-2 mb-4">
    <h1 class="h3 text-dark">📦 Purchase Invoice Products- <span class="text-primary">{{ $invoice->purchase_no }}</span></h1>
</div>
<!-- ✅ Seller Info Card -->
<div class="card shadow-sm border mb-4">
    <!-- Collapsible Header -->
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center section-heading" 
         data-toggle="collapse" 
         data-target="#supplierInfoCollapse" 
         style="cursor: pointer;">
        <h5 class="mb-0 ">
            🧾 Supplier Information
        </h5>
        <i class="las la-angle-down rotate-icon" id="supplierToggleIcon"></i>
    </div>

    <!-- Collapsible Content -->
    <div class="collapse show" id="supplierInfoCollapse">
        <div class="card-body">
            @php
                $seller = $invoice->seller_info ?? [];
                $address = $invoice->address ?? null;
            @endphp

            <ul class="list-unstyled mb-0">
                @if ($invoice->addresses_id && $address)
                    <li><strong>Name:</strong> {{ $address->company_name ?? 'N/A' }}</li>
                    <li><strong>Phone:</strong> {{ $address->phone ?? 'N/A' }}</li>
                    <li><strong>Address:</strong> {{ $address->address ?? 'N/A' }}</li>
                    @if (!empty($address->gstin))
                        <li><strong>GSTIN:</strong> {{ $address->gstin }}</li>
                    @endif
                @else
                    <li><strong>Name:</strong> {{ $seller['seller_name'] ?? 'N/A' }}</li>
                    <li><strong>Phone:</strong> {{ $seller['seller_phone'] ?? 'N/A' }}</li>
                    <li><strong>Address:</strong> {{ $seller['seller_address'] ?? 'N/A' }}</li>
                    @if (!empty($seller['seller_gstin']))
                        <li><strong>GSTIN:</strong> {{ $seller['seller_gstin'] }}</li>
                    @endif
                @endif
            </ul>
        </div>
    </div>
</div>

<!-- Table Wrapper with Loader -->
<div class="table-wrapper position-relative">
    <!-- Loader Spinner -->
    <div id="tableLoader" class="table-loader text-center d-flex flex-column align-items-center justify-content-center">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div class="loader-text">Loading data...</div>
    </div>

    <!-- Table Content (hidden initially) -->
    <div id="tableContent" class="d-none">
        <div class="card shadow-lg border-0 mb-4 fade-in-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table styled-invoice-table mb-0">
                        <thead>
                            <tr class="text-uppercase text-center">
                                <th>#</th>
                                <th>PO No.</th>
                                <th>Part No.</th>
                                <th>HSN Code</th>
                                <th class="text-left">Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $total = 0; @endphp
                            @foreach ($invoice->purchaseInvoiceDetails as $index => $detail)
                                @php
                                    $product = \App\Models\Product::where('part_no', $detail->part_no)->first();
                                    // Base price from detail
									$price = $detail->price ?? 0;

									// Add tax to price
									$taxedPrice = round($price + ($price * $detail->tax / 100), 2);

									// Calculate subtotal for current line item
									$subtotal = $taxedPrice * $detail->qty;

									// Add to total
									$total += $subtotal;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-soft-primary px-2 py-1">{{ $detail->purchase_order_no }}</span>
                                    </td>
                                    <td class="text-center">{{ $detail->part_no }}</td>
                                    <td class="text-center">{{ $detail->hsncode }}</td>
                                    <td>{{ $product->name ?? 'N/A' }}</td>
                                    <td class="text-center">
                                        <input type="text" class="form-control form-control-sm text-center readonly-field" value="{{ $detail->qty }}" readonly>
                                    </td>
                                    <td class="text-center">
                                        <input type="text" class="form-control form-control-sm text-center readonly-field" value="{{ $taxedPrice }}" readonly>
                                    </td>
                                    <td class="text-right font-weight-semibold text-dark">
                                        ₹{{ number_format($subtotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="total-footer">
                            <tr>
                                <td colspan="7" class="text-right text-uppercase font-weight-bold">Total</td>
                                <td class="text-right font-weight-bold h5">
                                    ₹{{ number_format($total, 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
@section('script')
<script type="text/javascript">

    /** Table Loading Animation start **/
    document.addEventListener("DOMContentLoaded", function () {
        
        setTimeout(function () {
            const loader = document.getElementById('tableLoader');
            loader.classList.add('hide-loader');

            const content = document.getElementById('tableContent');
            content.classList.remove('d-none');
            content.classList.add('fade-in-table');
        }, 1000);
    });
    /** Table Loading Animatin End **/


</script>
@endsection