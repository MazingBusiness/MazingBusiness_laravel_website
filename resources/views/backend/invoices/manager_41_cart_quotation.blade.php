<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $quotation_id ?? 'SALES INVOICE' }}</title>
@php
if (!function_exists('inr_number_to_words')) {
    function inr_number_to_words($n): string {
        $n = (int)$n;
        $ones = [
            '', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
            'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'
        ];
        $tens = ['', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

        if ($n == 0) return 'Zero';
        if ($n < 20) return $ones[$n];
        if ($n < 100) {
            return $tens[intval($n/10)] . (($n % 10) ? ' ' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intval($n/100)] . ' Hundred' . (($n % 100) ? ' and ' . inr_number_to_words($n % 100) : '');
        }
        if ($n < 100000) { // up to 99,999
            return inr_number_to_words(intval($n/1000)) . ' Thousand' . (($n % 1000) ? ' ' . inr_number_to_words($n % 1000) : '');
        }
        if ($n < 10000000) { // up to 99,99,999
            return inr_number_to_words(intval($n/100000)) . ' Lakh' . (($n % 100000) ? ' ' . inr_number_to_words($n % 100000) : '');
        }
        // up to 99,99,99,999
        return inr_number_to_words(intval($n/10000000)) . ' Crore' . (($n % 10000000) ? ' ' . inr_number_to_words($n % 10000000) : '');
    }

    function inr_amount_in_words($amount): string {
        $amount = (float)$amount;
        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);

        $words = inr_number_to_words($rupees) . ' Rupees';
        if ($paise > 0) {
            $words .= ' and ' . inr_number_to_words($paise) . ' Paise';
        }
        return $words . ' Only';
    }
}
@endphp
  @php
    // ---------- derive + sanitize ----------
    $first     = ($cart_items ?? collect())->first();
    $items     = $cart_items ?? collect();              // alias so the template stays readable
    $itemCount = $items->count();

    // auto height of the middle white space
    $auto_gap_mm = $itemCount <= 2 ? 195 : ($itemCount <= 4 ? 185 : ($itemCount <= 7 ? 160 : 120));

    // party / header
    $companyName = $first->company_name ?? 'THE MAZING RETAIL PVT LIMITED';
    $mcName      = strtoupper($first->warehouse_name ?? 'MUMBAI');

    // dates
    try {
        $createdAt = \Carbon\Carbon::parse($first->created_at ?? now());
    } catch (\Throwable $e) {
        $createdAt = now();
    }

    // totals
    $totalQty   = (int) ($items->sum('quantity') ?? 0);
    $subTotal   = (float) ($items->sum('total') ?? 0);        // total = quantity * price (already selected in query)
    $grandTotal = $subTotal;                                  // no extra charges here (adjust if you add tax/shipping)

    // amount in words (optional – leave blank if you don’t have a converter)
    $amountWords = ''; // e.g., use your own helper if available

    // clean up "Only"
    $words = trim($amountWords);
    $words = preg_replace('/\s*only\.?\s*$/i', '', $words);
  @endphp

  @php
  // QR square ka size (mm me). Zarurat ho to 16–22mm try kar sakte ho.
  $qrSizeMm   = 18;
  $cellPadMm  = 1.5;                         // cell padding
  $cellHgtMm  = $qrSizeMm + (2 * $cellPadMm); // bank/QR cell ek hi height
@endphp

  <style>
    /* ---------- Page ---------- */
    @page{ margin:10mm; }
    html,body{
      margin:0;
      font-family: DejaVu Sans, Arial, sans-serif;
      color:#1d1d1f;
      font-size:12px;
      line-height:1.3;
    }
    
    .title.superbold{
        font-weight: 900 !important;      /* max weight */
        font-size: 18px !important;       /* a bit larger for impact */
        letter-spacing: .6px;             /* cleaner caps */
        /* optional: faux extra-bold (works in most PDF renderers) */
        text-shadow: 0 .2px 0 #000, .2px 0 0 #000, 0 -.2px 0 #000, -.2px 0 0 #000;
      }

    /* ---------- Tables ---------- */
    table{ border-collapse:collapse; width:100%; }
    th,td{
      border:1px solid #4a4a4a;
      padding:7px 8px;
      vertical-align:top;
      word-break:break-word;
    }
    th{ font-weight:700; color:#111; }
    .num{ text-align:right; font-variant-numeric: tabular-nums; }
    .text-center{ text-align:center; }
    .text-right{ text-align:right; }
    .small{ font-size:11px; color:#555; }
    .muted{ color:#5a5a5a; }
    .tabless td,.tabless th{ border:none; padding:3px 0; }

    /* Column widths */
    .w-sr{ width:40px; } .w-qty{ width:60px; } .w-unit{ width:60px; }
    .w-rate{ width:92px; } .w-amt{ width:118px; }
    .col-left{ width:60%; } .col-right{ width:40%; }

    /* ---------- Outer frame ---------- */
    .frame{
      border:1.4px solid #0e0e0e;
      padding:0;
      box-sizing:border-box;
      min-height:277mm;
      background:#fff;
    }

    /* join inner tables to frame sides */
    .join-frame-lr{ border-left:none !important; border-right:none !important; }
    .join-frame-lr th:first-child, .join-frame-lr td:first-child{ border-left:none !important; }
    .join-frame-lr th:last-child,  .join-frame-lr td:last-child{  border-right:none !important; }

    .spacer{ height:6px; border:none; }
    .items-wrap{ min-height: {{ $gap_mm ?? $auto_gap_mm }}mm; }

    /* ---------- Header / Title ---------- */
    .title{
      margin:0; padding:10px 8px 6px;
      letter-spacing:.35px;
      font-weight:800; font-size:15px;
    }
    .strap{
      border-top:1px solid #111; border-bottom:1px solid #111;
      height:0; margin:0 8px 6px;
    }

    /* ---------- Meta area ---------- */
    .meta td{ padding:3px 0; }
    .meta .label{ width:54%; color:#2f2f31; }
    .meta-card{
      background:#f7f7f7;
      border-top-color:#3f3f3f; border-bottom-color:#3f3f3f;
    }

    /* ---------- Items table polish ---------- */
    .items-table thead th{
      background:#f1f1f1;
      border-top:1.2px solid #323232;
      border-bottom:1.2px solid #323232;
    }
    .items-table tbody tr:nth-child(odd) td{ background:#fbfbfb; }
    .desc .small{ margin-top:2px; }

    .items-table tfoot td{ font-weight:700; }
    .items-table tfoot .label-right{ text-align:right; }
    .items-table tfoot tr.subtotal-row td{
      background:#fafafa;
      border-top:1.2px solid #2c2c2c;
    }
    .items-table tfoot tr.grand-row td{
      border-top:1.8px solid #0f0f0f;
      background:#efefef;
    }
    .items-table tfoot tr.grand-row td.amount{
      background:#e7e7e7;
      font-weight:800;
      color:#000;
    }

    .words td{
      background:#f8f8f8;
      border-top:1.2px solid #3a3a3a;
    }

    .rupee:before{ content:"\20B9\00A0"; }


    /* Compact bank block */

.qr-wrap { text-align:center; }
.compact-bank td{ border:none; padding:1px 2px; font-size:9.5px; line-height:1.1; }
  </style>
</head>
<body>
  {{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
  <div class="frame">

    <!-- ===== Title ===== -->
    <table class="join-frame-lr">
        <tr>
          <td class="no-border text-center">
            <h2 class="title superbold">QUOTATION</h2>
          </td>
        </tr>
    </table>
    <div class="strap"></div>

    <!-- ===== Meta ===== -->
    <table class="join-frame-lr meta-card">
      <tr>
        <td class="col-left">
          <strong>TO</strong><br>
          {{ $companyName }}<br>
          <table class="w-100 tabless small meta" style="margin-top:6px;">
            <tr>
              <td class="label">MC NAME :</td>
              <td>{{ $mcName }}</td>
            </tr>
          </table>
        </td>
        <td class="col-right">
          <table class="w-100 tabless small meta">
            <tr>
              <td class="label">Sales Inv. No. :</td>
              <td class="text-right">{{ $quotation_id ?? '-' }}</td>
            </tr>
            <tr>
              <td class="label">Date :</td>
              <td class="text-right">{{ $createdAt->format('d-m-Y') }}</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <div class="spacer"></div>

    <!-- ===== Items + Totals ===== -->
    <div class="items-wrap">
      <table class="join-frame-lr items-table">
        <thead>
          <tr>
            <th class="w-sr text-center">Sr.</th>
            <th>Item Description</th>
            <th class="w-qty text-center">Qty</th>
            <th class="w-unit text-center">Unit</th>
            <th class="w-rate text-right">Rate</th>
            <th class="w-amt text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $i => $row)
            @php
              // 'price' is unit rate; 'total' came as quantity*price in query
              $rate   = (float) ($row->price ?? 0);
              $amount = (float) ($row->total ?? ($rate * (int)($row->quantity ?? 0)));
            @endphp
            <tr>
              <td class="text-center">{{ $i + 1 }}</td>
              <td class="desc">
                {{ $row->product_name ?? '-' }}
                @if(!empty($row->variation ?? null))
                  <div class="small">({{ $row->variation }})</div>
                @endif
              </td>
              <td class="text-center">{{ (int)($row->quantity ?? 0) }}</td>
              <td class="text-center">Pcs</td>
              <td class="num">{{ number_format($rate, 2) }}</td>
              <td class="num">{{ number_format($amount, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center muted">No items.</td></tr>
          @endforelse
        </tbody>

        <tfoot>
          <tr class="subtotal-row">
            <td></td>
            <td class="label-right">Sub Total</td>
            <td class="text-center">{{ $totalQty }}</td>
            <td class="text-center">Pcs</td>
            <td></td>
            <td class="num">{{ number_format($subTotal, 2) }}</td>
          </tr>
          <tr class="grand-row">
            <td></td>
            <td class="label-right"><strong>Grand Total <span class="rupee"></span></strong></td>
            <td></td><td></td><td></td>
            <td class="num amount"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>

       <!-- ===== Amount in words ===== -->
    <table class="join-frame-lr words" style="margin-top:6px;">
      <tr>
        <td>
          {{ $words !== '' ? $words : inr_amount_in_words($grandTotal) }}
        </td>
      </tr>
    </table>
    <!-- ===== Note ===== -->
    <table class="join-frame-lr" style="margin-top:6px;">
      <tr>
        <td style="background:#ffffff; solid #ffffff;">
          <strong>Note:</strong>
          <div class="small" style="margin-top:4px;">
            1) Rates are excluding of GST<br>
            2) GST extra applicable on all items
          </div>
        </td>
      </tr>
    </table>
    <!-- ===== Bank Details + QR (equal height) ===== -->
    <table class="join-frame-lr" style="margin-top:4px;">
      <tr>
        <!-- LEFT: Bank details (same height as QR) -->
        <td class="col-left" style="vertical-align:top; height: {{ $cellHgtMm }}mm; padding: {{ $cellPadMm }}mm;">
          <strong style="font-size:11px;">Bank Details</strong>
          <table class="w-100 tabless compact-bank" style="margin-top:3px;">
            <tr><td class="label">Name :</td><td>ACE TOOLS</td></tr>
            <tr><td class="label">Bank :</td><td>ICICI BANK</td></tr>
            <tr><td class="label">Branch :</td><td>NAJAFGARH ROAD, NEW DELHI</td></tr>
            <tr><td class="label">A/C No :</td><td>235605001041</td></tr>
            <tr><td class="label">IFSC :</td><td>ICIC0002356</td></tr>
          </table>
        </td>
    
        <!-- RIGHT: QR (same height as bank cell) -->
        <td class="col-right text-center" style="vertical-align:top; height: {{ $cellHgtMm }}mm; padding: {{ $cellPadMm }}mm; width:30%;">
         
          <img
            src="https://mazingbusiness.com/public/images/quotation_qr.png"
            alt="Payment QR"
            style="width: {{ $qrSizeMm }}mm; height: {{ $qrSizeMm }}mm; object-fit:contain; border:.6px solid #ccc; padding:1mm;"
          >
        </td>
      </tr>
    </table>
  </div>
  {{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
</body>
</html>
