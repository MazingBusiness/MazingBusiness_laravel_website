{{-- resources/views/backend/sales/challan_forced_notification_pdf.blade.php --}}
@php
    use Carbon\Carbon;

    // ---------- Helpers ----------
    $fmtDate = function($d) {
        if (empty($d)) return 'N/A';
        try { return Carbon::parse($d)->format('d-m-Y'); } catch (\Throwable $e) { return (string)$d; }
    };
    $num = function($v, $dec = 2) {
        if ($v === null || $v === '') return number_format(0, $dec);
        return number_format((float)$v, $dec);
    };

    // Normalize props (allow array/object)
    $invoice   = is_array($invoice ?? null) ? (object)$invoice : ($invoice ?? (object)[]);
    $challan   = is_array($challan ?? null) ? (object)$challan : ($challan ?? (object)[]);
    $details   = collect($details ?? []);
    $shipping  = $shipping ?? null; // May be Address model or array
    $logistic  = $logistic ?? null;
    $eway      = $eway ?? null;

    $eway_irn = is_array($eway) ? ($eway['irn_no'] ?? null)
               : (is_object($eway) ? ($eway->irn_no ?? null) : null);

    // Header image (hosted)
    $headerImageUrl = 'https://mazingbusiness.com/public/assets/img/pdfHeader.png';

    // Branch data (array expected)
    $branch = (array) ($branchDetails ?? []);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forced Challan — {{ $challan->challan_no ?? 'N/A' }}</title>
    <style>
        /* DOMPDF-safe styles */
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #333; margin: 0; }
        .container { width: 100%; margin: 0 auto; border: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }
        .no-border td, .no-border th { border: none; }
        .header-image img { width: 100%; max-height: 80px; }
        .right   { text-align: right; }
        .center  { text-align: center; }
        .bold    { font-weight: bold; }
        .muted   { color: #666; }
        .small   { font-size: 11px; }

        .banner {
            background: #ffe6e6;
            border: 1px solid #ff7b7b;
            color: #8a1f1f;
            padding: 8px 10px;
            margin: 8px 10px;
            border-radius: 4px;
            font-weight: 700;
            text-align: center;
        }
        .legend {
            background: #fff6d9;
            border: 1px solid #f3d27a;
            color: #725c10;
            padding: 6px 8px;
            margin: 8px 10px 4px;
            border-radius: 4px;
            font-weight: 600;
        }

        .row-warn { background: #fff0f0; }     /* highlight under-priced rows */
        .price-high { color: #b00000; font-weight: 700; } /* billed < purchase */
        .mono { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; }

        .section-title {
            background: #174e84;
            color: #fff;
            padding: 6px 8px;
            font-weight: 700;
        }

        .foot-strip {
            text-align: center;
            margin-top: 16px;
            border-top: 1px solid #999;
            padding-top: 8px;
        }
        .foot-strip img { max-width: 100px; margin: 0 5px; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header Image --}}
    <table class="header-image no-border">
        <tr>
            <td>
                <img src="{{ $headerImageUrl }}" alt="Header">
            </td>
        </tr>
    </table>

    {{-- Forced Banner --}}
    <div class="banner">
        ⚠️ FORCED CHALLAN NOTIFICATION — Items billed below purchase price
        <div class="small muted" style="margin-top:3px;">
            Challan: <span class="bold">{{ $challan->challan_no ?? 'N/A' }}</span>
            &nbsp;|&nbsp; Date: <span class="bold">{{ $fmtDate($challan->challan_date ?? now()) }}</span>
        </div>
    </div>

    {{-- Branch + E-way row --}}
    <table class="no-border" style="margin: 0 10px;">
        <tr>
            <td style="width:70%; vertical-align: middle;">
                <strong>GSTIN:</strong> {{ $branch['gstin'] ?? 'N/A' }}
            </td>
            <td style="width:30%; text-align: right; vertical-align: middle;">
                @if (!empty($eway_irn) && $eway_irn !== '-')
                    <a href="{{ route('admin.download.ewaybill', $invoice->id ?? 0) }}"
                       target="_blank"
                       style="font-size: 12px; background-color: #174e84; color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; display: inline-block;">
                        Download E-Way Bill
                    </a>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" class="center" style="padding-top: 6px;">
                <span class="bold" style="color:#174e84;">
                    {{ $branch['company_name'] ?? '' }}
                </span><br>
                {{ $branch['address_1'] ?? '' }}
                @if(!empty($branch['address_2'])), {{ $branch['address_2'] }} @endif
                @if(!empty($branch['address_3'])), {{ $branch['address_3'] }} @endif
                <br>
                {{ $branch['city'] ?? '' }}, {{ $branch['state'] ?? '' }} - {{ $branch['postal_code'] ?? '' }}<br>
                Tel.: {{ $branch['phone'] ?? '' }} | Email: {{ $branch['email'] ?? '' }}
            </td>
        </tr>
    </table>

    {{-- Header Cards --}}
    <table style="width: 100%; margin-top: 6px;">
        <tr>
            <!-- Left: Challan / Invoice refs -->
            <td width="32%">
                <div class="section-title">Document</div>
                <table>
                    <tr>
                        <td class="muted">Challan No</td>
                        <td class="right bold mono">{{ $challan->challan_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Challan Date</td>
                        <td class="right mono">{{ $fmtDate($challan->challan_date ?? now()) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Invoice No</td>
                        <td class="right mono">{{ $invoice->invoice_no ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Invoice Date</td>
                        <td class="right mono">{{ $fmtDate($invoice->created_at ?? null) }}</td>
                    </tr>
                </table>
            </td>

            <!-- Middle: Billed/Ship To -->
            <td width="38%">
                <div class="section-title">Billed / Shipped To</div>
                @php
                    // Normalize shipping display (Address model or array/object)
                    $shipName   = $shipping->company_name ?? ($shipping['company_name'] ?? 'N/A');
                    $shipAddr   = trim(($shipping->address ?? ($shipping['address'] ?? '')) . ' ' . ($shipping->address_2 ?? ($shipping['address_2'] ?? '')));
                    $shipCity   = ($shipping->city ?? ($shipping['city'] ?? ''));
                    $shipState  = is_object($shipping->state ?? null) ? ($shipping->state->name ?? '') : ($shipping['state']['name'] ?? ($shipping['state'] ?? ''));
                    $shipPin    = ($shipping->postal_code ?? ($shipping['postal_code'] ?? ''));
                    $shipGST    = ($shipping->gstin ?? ($shipping['gstin'] ?? 'N/A'));
                    $shipPhone  = ($shipping->phone ?? ($shipping['phone'] ?? ''));
                @endphp
                <table>
                    <tr><td class="bold">{{ $shipName }}</td></tr>
                    <tr><td>{{ $shipAddr }}</td></tr>
                    <tr><td>{{ $shipCity }} {{ $shipCity && $shipPin ? '-' : '' }} {{ $shipPin }}</td></tr>
                    <tr><td><span class="muted">State:</span> {{ $shipState ?: 'N/A' }}</td></tr>
                    <tr><td><span class="muted">GSTIN:</span> {{ $shipGST }}</td></tr>
                    <tr><td><span class="muted">Phone:</span> {{ $shipPhone ?: 'N/A' }}</td></tr>
                </table>
            </td>

            <!-- Right: Transport (optional) -->
            <td width="30%">
                <div class="section-title">Transport</div>
                <table>
                    <tr>
                        <td class="muted">LR Number</td>
                        <td class="right mono">{{ $logistic->lr_no ?? 'N/A' }}</td>
                    </tr>
                    {{--  <tr>
                        <td class="muted">LR Date</td>
                        <td class="right mono">{{ $logistic->lr_date ? $fmtDate($logistic->lr_date) : 'N/A' }}</td>
                    </tr> --}}
                    <tr>
                        <td class="muted">Transport</td>
                        <td class="right mono">{{ $logistic->transport_name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="muted">No. of Boxes</td>
                        <td class="right mono">{{ $logistic->no_of_boxes ?? 'N/A' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Legend --}}
    <div class="legend">
        Only items shown below are those where <strong>Billed Price &lt; Purchase Price</strong>.
    </div>

    {{-- PRODUCT LISTING (ONLY HIGHLIGHTED ROWS) --}}
    @php
        $sn = 1;
        $totalQty = 0;
        $underCount = 0;
    @endphp
    <table style="margin: 6px 10px 0;">
        <thead>
            <tr>
                <th style="width:6%;">S.N.</th>
                <th>Item Name</th>
                <th style="width:18%;">Part No</th>
                <th style="width:14%;" class="right">Purchase Price</th>
                <th style="width:14%;" class="right">Billed Price</th>
                <th style="width:10%;" class="right">Qty</th>
            </tr>
        </thead>
        <tbody>
        @foreach($details as $d)
            @php
                $p        = $d->product_data ?? null;
                $itemName = $p->name ?? ($p->getTranslation('name') ?? 'Item');
                $partNo   = $p->part_no ?? '';
                $purchase = (float) ($p->purchase_price ?? $p->unit_price ?? 0);
                $billed   = (float) ($d->rate ?? 0);
                $qty      = (float) ($d->quantity ?? 0);
                $isUnder  = $billed < $purchase;
            @endphp
            @continue(!$isUnder) {{-- skip non-highlighted rows --}}
            @php
                $underCount++;
                $totalQty += $qty;
            @endphp
            <tr class="row-warn">
                <td>{{ $sn++ }}</td>
                <td>{{ $itemName }}</td>
                <td class="mono">{{ $partNo }}</td>
                <td class="right mono">₹{{ $num($purchase) }}</td>
                <td class="right mono price-high">₹{{ $num($billed) }}</td>
                <td class="right mono">{{ $num($qty, 2) }}</td>
            </tr>
        @endforeach

            @if ($underCount === 0)
                <tr>
                    <td colspan="6" class="center muted">No items billed below purchase price.</td>
                </tr>
            @else
                <tr>
                    <td colspan="5" class="right bold">Total Qty (Under-priced):</td>
                    <td class="right bold mono">{{ $num($totalQty, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Summary strip --}}
    <table class="no-border" style="margin: 8px 10px;">
        <tr>
            <td class="small">
                <strong>Forced Rows:</strong> {{ $underCount }} &nbsp;|&nbsp;
                <strong>Generated On:</strong> {{ $fmtDate(now()) }} {{ now()->format('H:i') }}
            </td>
            <td class="small right">
                <span class="muted">This document is auto-generated for notification purposes.</span>
            </td>
        </tr>
    </table>

    {{-- Footer Image Strip --}}
    <div class="foot-strip">
        <img style="width:100px;height:100px;" src="https://mazingbusiness.com/public/invoice_image/a1.jpg" alt="Image 1">
        <img style="width:100px;height:100px;" src="https://mazingbusiness.com/public/invoice_image/a2.jpg" alt="Image 2">
        <img style="width:100px;height:100px;" src="https://mazingbusiness.com/public/invoice_image/a3.jpg" alt="Image 3">
        <img style="width:100px;height:100px;" src="https://mazingbusiness.com/public/invoice_image/a4.jpg" alt="Image 4">
    </div>

</div>
</body>
</html>
