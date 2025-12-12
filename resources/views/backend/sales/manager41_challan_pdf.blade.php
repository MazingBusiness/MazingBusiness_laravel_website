<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $challan->challan_no ?? 'SALES INVOICE' }}</title>

  @php
    /* ---------- Controls ---------- */
    $itemCount   = is_countable($items ?? []) ? count($items) : 0;
    $auto_gap_mm = $itemCount <= 2 ? 195 : ($itemCount <= 4 ? 185 : ($itemCount <= 7 ? 160 : 120));
    $words       = trim($amountWords ?? '');
    $words       = preg_replace('/\s*only\.?\s*$/i', '', $words); // avoid "Only Only"
  @endphp

  <style>
    /* ---------- Page ---------- */
    @page{ margin:10mm; } /* tight, like showroom printouts */
    html,body{
      margin:0;
      font-family: DejaVu Sans, Arial, sans-serif;
      color:#1d1d1f;
      font-size:12px;
      line-height:1.3;
    }

    /* ---------- Tables ---------- */
    table{ border-collapse:collapse; width:100%; }
    th,td{
      border:1px solid #4a4a4a;   /* crisp inner grid */
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

    /* ---------- Outer frame (slightly bolder) ---------- */
    .frame{
      border:1.4px solid #0e0e0e;
      padding:0;               /* join inner side lines to frame */
      box-sizing:border-box;
      min-height:277mm;        /* A4 usable height (297-2*10) */
      background:#fff;
    }

    /* Sections that continue the frame’s left/right lines */
    .join-frame-lr{ border-left:none !important; border-right:none !important; }
    .join-frame-lr th:first-child, .join-frame-lr td:first-child{ border-left:none !important; }
    .join-frame-lr th:last-child,  .join-frame-lr td:last-child{  border-right:none !important; }

    .spacer{ height:6px; border:none; }
    .items-wrap{ min-height: {{ $gap_mm ?? $auto_gap_mm }}mm; } /* big middle whitespace */

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

    /* Sub/Grand Totals inside main table */
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

    /* Amount in words strip */
    .words td{
      background:#f8f8f8;
      border-top:1.2px solid #3a3a3a;
    }

    .rupee:before{ content:"\20B9\00A0"; }
  </style>
</head>
<body>

  <div class="frame">

    <!-- ===== Title ===== -->
    <table class="join-frame-lr">
      <tr>
        <td class="no-border text-center">
          <h2 class="title">{{ strtoupper($challan->user->company_name ?? 'THE MAZING RETAIL PVT LIMITED') }}</h2>
        </td>
      </tr>
    </table>
    <div class="strap"></div>

    <!-- ===== Meta ===== -->
    <table class="join-frame-lr meta-card">
      <tr>
        <td class="col-left">
          <strong>TO</strong><br>
          {{ $challan->to_company_name ?? ($challan->user->company_name ?? '-') }}<br>
          <table class="w-100 tabless small meta" style="margin-top:6px;">
            <tr><td class="label">Sales Order No. :</td><td>{{ $challan->sales_order_no ?? '-' }}</td></tr>
            <tr><td class="label">No. of CTN :</td><td>{{ $challan->no_of_ctn ?? '-' }}</td></tr>
            <tr><td class="label">MC NAME :</td><td>{{ strtoupper($challan->mc_name ?? 'MUMBAI') }}</td></tr>
          </table>
        </td>
        <td class="col-right">
          <table class="w-100 tabless small meta">
            <tr><td class="label">Sales Inv. No. :</td><td class="text-right">{{ $challan->challan_no ?? '-' }}</td></tr>
            <tr><td class="label">Date :</td><td class="text-right">{{ optional($challan->created_at)->format('d-m-Y') }}</td></tr>
            <tr><td class="label">Transport :</td><td class="text-right">{{ $challan->transport_name ?? 'Aps parcel carriers' }}</td></tr>
          </table>
        </td>
      </tr>
    </table>

    <div class="spacer"></div>

    <!-- ===== Items + Totals (inside same table) ===== -->
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
            <tr>
              <td class="text-center">{{ $i + 1 }}</td>
              <td class="desc">
                {{ $row->product_data->name ?? '-' }}
                @if(!empty($row->variation)) <div class="small">({{ $row->variation }})</div> @endif
              </td>
              <td class="text-center">{{ (int)($row->quantity ?? 0) }}</td>
              <td class="text-center">Pcs</td>
              <td class="num">{{ number_format((float)($row->rate ?? 0), 2) }}</td>
              <td class="num">{{ number_format((float)($row->final_amount ?? 0), 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center muted">No items.</td></tr>
          @endforelse
        </tbody>

        <tfoot>
          <!-- Sub Total -->
          <tr class="subtotal-row">
            <td></td>
            <td class="label-right">Sub Total</td>
            <td class="text-center">{{ (int)($totalQty ?? 0) }}</td>
            <td class="text-center">Pcs</td>
            <td></td>
            <td class="num">{{ number_format((float)($subTotal ?? 0), 2) }}</td>
          </tr>
          <!-- Grand Total -->
          <tr class="grand-row">
            <td></td>
            <td class="label-right"><strong>Grand Total <span class="rupee"></span></strong></td>
            <td></td><td></td><td></td>
            <td class="num amount"><strong>{{ number_format((float)($grandTotal ?? 0), 2) }}</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- ===== Amount in words ===== -->
    <table class="join-frame-lr words" style="margin-top:6px;">
      <tr>
        <td>Rupees {{ $words !== '' ? $words : number_format((float)($grandTotal ?? 0), 2) }} Only</td>
      </tr>
    </table>

  </div>
</body>
</html>
