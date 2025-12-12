{{-- resources/views/backend/sales/invoice_underpriced_pdf.blade.php --}}
@php
    use Carbon\Carbon;

    // Helpers
    $fmtDate = function($d) {
        if (empty($d)) return 'N/A';
        try { return Carbon::parse($d)->format('d-m-Y'); } catch (\Throwable $e) { return (string)$d; }
    };
    $num = function($v, $dec = 2) {
        if ($v === null || $v === '') return number_format(0, $dec);
        return number_format((float)$v, $dec);
    };

    $inv = is_array($invoice ?? null) ? (object)$invoice : ($invoice ?? (object)[]);
    $lines = collect($lines ?? []);
    $productRates = $productRates ?? [];

    // Shipping state name (for place of supply)
    $shippingStateName = null;
    if (isset($shipping) && is_object($shipping) && isset($shipping->state) && is_object($shipping->state)) {
        $shippingStateName = $shipping->state->name ?? null;
    } elseif (is_array($shipping ?? null) && isset($shipping['state']['name'])) {
        $shippingStateName = $shipping['state']['name'];
    }

    $headerImageUrl = 'https://mazingbusiness.com/public/assets/img/pdfHeader.png';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice — {{ $inv->invoice_no ?? 'N/A' }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; margin: 0; color: #333; }
    .container { width: 100%; margin: 0 auto; border: 1px solid #000; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }
    .no-border td, .no-border th { border: none; }
    .header-image img { width: 100%; max-height: 80px; }
    .highlight { color: #174e84; font-weight: bold; }
    .right { text-align: right; }
    .center { text-align: center; }
    .muted { color:#666; }
</style>
</head>
<body>
<div class="container">

    {{-- Header Image --}}
    <table class="header-image no-border">
        <tr><td><img src="{{ $headerImageUrl }}" alt="Header"></td></tr>
    </table>

    {{-- Company / GST --}}
    <table class="no-border" style="margin-top: 4px;">
        <tr>
            <td style="width:70%; vertical-align: middle;">
                <strong>GSTIN:</strong> {{ $branchDetails['gstin'] ?? 'N/A' }}
            </td>
            <td style="width:30%; text-align: right; vertical-align: middle;">
                {{-- E-way button omitted; add if needed --}}
            </td>
        </tr>
        <tr>
            <td colspan="2" class="highlight center" style="padding-top: 8px;">
                {{ $branchDetails['company_name'] ?? '' }}<br>
                {{ $branchDetails['address_1'] ?? '' }}
                @if(!empty($branchDetails['address_2'])), {{ $branchDetails['address_2'] }} @endif
                @if(!empty($branchDetails['address_3'])), {{ $branchDetails['address_3'] }} @endif
                <br>
                {{ $branchDetails['city'] ?? '' }}, {{ $branchDetails['state'] ?? '' }} - {{ $branchDetails['postal_code'] ?? '' }}<br>
                Tel.: {{ $branchDetails['phone'] ?? '' }} | Email: {{ $branchDetails['email'] ?? '' }}
            </td>
        </tr>
    </table>

    {{-- Invoice & Billing (safe) --}}
    <table class="no-border" style="margin-top: 6px;">
        <tr>
            {{-- Left --}}
            <td width="30%">
                <strong>Invoice No:</strong> {{ $inv->invoice_no ?? '' }}<br>
                <strong>Dated:</strong> {{ $fmtDate($inv->created_at ?? null) }}<br>
                <strong>Place of Supply:</strong> {{ $shippingStateName ?? 'N/A' }}
            </td>

            {{-- Middle: Billed To --}}
            <td width="35%">
                <strong>Billed To:</strong><br>
                {{ $shipping->company_name ?? 'N/A' }}<br>
                {{ $shipping->address ?? '' }} {{ $shipping->address_2 ?? '' }}<br>
                {{ $shipping->city ?? '' }} - {{ $shipping->postal_code ?? '' }}<br>
                <strong>GSTIN:</strong> {{ $shipping->gstin ?? 'N/A' }}
            </td>

            {{-- Right: Transport (safe) --}}
            <td width="35%">
                <strong>LR Number:</strong> {{ optional($logistic)->lr_no ?? 'N/A' }}<br>
                <strong>LR Date:</strong> {{ !empty(optional($logistic)->lr_date) ? $fmtDate(optional($logistic)->lr_date) : 'N/A' }}<br>
                <strong>Transport:</strong> {{ optional($logistic)->transport_name ?? 'N/A' }}<br>
                <strong>No. of Boxes:</strong> {{ optional($logistic)->no_of_boxes ?? 'N/A' }}<br>
                <strong>Sales Person:</strong> {{ $shipping->phone ?? 'N/A' }}
            </td>
        </tr>
    </table>

    {{-- PRODUCT LISTING (ONLY UNDER-PRICED ITEMS) --}}
    @php
        $sn = 1;
        $tableTotalQty = 0;
        $underCount = 0;
    @endphp

    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="width:6%;">S.N.</th>
                <th>Item Name</th>
                <th style="width:16%;">Part No</th>
                <th style="width:14%;" class="right">Purchase Price</th>
                <th style="width:14%;" class="right">Billed Price</th>
                <th style="width:10%;" class="right">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $row)
                @php
                    // Support both array/object rows
                    $pn   = is_array($row) ? ($row['part_no'] ?? '')      : ($row->part_no ?? '');
                    $name = is_array($row) ? ($row['item_name'] ?? '')    : ($row->item_name ?? '');
                    $rate = (float) (is_array($row) ? ($row['rate'] ?? ($row['price'] ?? 0)) : ($row->rate ?? ($row->price ?? 0)));
                    $qty  = (float) (is_array($row) ? ($row['billed_qty'] ?? 0) : ($row->billed_qty ?? 0));

                    $key  = strtoupper(trim((string)$pn));
                    $purchase = (float) ($productRates[$key] ?? 0);

                    $isUnder = $purchase > $rate;
                @endphp

                @if($pn && $isUnder)
                    @php $underCount++; $tableTotalQty += $qty; @endphp
                    <tr>
                        <td>{{ $sn++ }}</td>
                        <td>{{ $name ?: 'Item' }}</td>
                        <td>{{ $pn }}</td>
                        <td class="right">₹{{ $num($purchase) }}</td>
                        <td class="right">₹{{ $num($rate) }}</td>
                        <td class="right">{{ $num($qty, 2) }}</td>
                    </tr>
                @endif
            @endforeach

            @if($underCount === 0)
                <tr>
                    <td colspan="6" class="center muted">No items billed below purchase price.</td>
                </tr>
            @else
                <tr>
                    <td colspan="5" class="right"><strong>Total Qty (Under-priced):</strong></td>
                    <td class="right"><strong>{{ $num($tableTotalQty, 2) }}</strong></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Footer Image Strip --}}
    <div style="text-align: center; margin-top: 16px; border-top: 1px solid #999; padding-top: 8px;">
        <div class="no-border" style="display: flex; justify-content: center; align-items: center; gap: 10px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a1.jpg" alt="Image 1" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a2.jpg" alt="Image 2" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a3.jpg" alt="Image 3" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a4.jpg" alt="Image 4" style="max-width: 100px;">
        </div>
    </div>

</div>
</body>
</html>
