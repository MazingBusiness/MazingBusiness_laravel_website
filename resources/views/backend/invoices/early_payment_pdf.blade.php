<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Early Payment — {{ $party_code }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#174e84; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; color:#174e84; }
    th { background:#f1f1f1; }
    .no-border td, .no-border th { border: 0 !important; }
    .right { text-align:right; }
    .muted { color:#333; }
  </style>
</head>
<body>
{{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
<!-- Header -->
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="no-border">
  <tr>
    <td style="text-align: right;">
      <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display:block;" />
    </td>
  </tr>
</table>

<!-- Static Address and User Details -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" class="no-border" style="margin-top: 20px;">
  <tr>
    <td width="50%" style="text-align: left; font-size: 14px; font-weight: bold;">
      ACE TOOLS PRIVATE LIMITED<br>
      Building No./Flat No.: Khasra No. 58/15,<br>
      Pal Colony, Village Rithala,Delhi<br>
      New Delhi - 110085
    </td>
    <td width="50%" style="text-align: right; font-size: 14px;">
      {{-- 🆕 Use $address passed from controller (avoid DB calls in blade) --}}
      {{ $address->company_name ?? '' }}<br>
      {{ $address->address ?? '' }}<br>
      {{ $address->address_2 ?? '' }}<br>
      Pincode: {{ $address->postal_code ?? '' }}<br>
    </td>
  </tr>
</table>

{{-- 🆕 Due / Overdue info block --}}
<table width="100%" border="0" cellpadding="10" cellspacing="0" class="no-border" style="margin-top: 10px;">
  <tr>
    <td width="50%" style="text-align: center; font-size: 14px;">
      <strong>Overdue Balance</strong><br>
      <span class="muted">₹ {{ number_format((float)$overdueAmount, 2) }}</span>
    </td>
    <td width="50%" style="text-align: center; font-size: 14px;">
      <strong>Due Amount</strong><br>
      <span class="muted">₹ {{ number_format((float)$dueAmount, 2) }}</span>
    </td>
  </tr>
</table>

<!-- Reward Balance -->
{{-- <p style="font-size: 14px; text-align: left; margin: 16px 0 8px;">
  <strong>Reward Balance: </strong> ₹ {{ number_format((float)$rewardAmount, 2) }}
  <span class="muted">({{ $last_dr_or_cr }})</span>
</p> --}}

<!-- Invoices Table (party-wise, all rows in one PDF) -->
<table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 8px;">
  <thead>
    <tr>
      <th style="text-align:center;">Invoice No</th>
      <th style="text-align:center;">Invoice Amount</th>
      <th style="text-align:center;">Remaining Amount</th>
      <th style="text-align:center;">Cash Discount %</th>
      <th style="text-align:center;">Cash Discount Amount</th>
      <th style="text-align:center;">Last Payment Date</th>
      <th style="text-align:center;">Payable Amount</th>  <!-- NEW -->
    </tr>
  </thead>

  <tbody>
    @php
      $totalInvoice = 0;
      $totalReward  = 0;
      $totalRemain  = 0;
      // Running total for "Payable Amount" starts from previous due
      $runningPayable = (float) ($previousDue ?? 0);
    @endphp

    {{-- Fixed row directly under header --}}
    <tr>
      <td style="text-align:center;">
        <strong>Previous Due Amount</strong>
      </td>
      <td class="right">—</td>
      <td class="right">₹ {{ number_format((float)($previousDue ?? 0), 2) }}</td>
      <td class="right">—</td>
      <td class="right">—</td>
      <td style="text-align:center;">—</td>
      <td class="right"><strong>₹ {{ number_format($runningPayable, 2) }}</strong></td>
    </tr>

    @forelse($rows as $r)
      @php
        $invAmt  = (float)($r['invoice_amount']    ?? 0);
        $remain  = (float)($r['remaining_amount']  ?? 0);
        $perc    = (float)($r['reward_percentage'] ?? 0);
        $rewAmt  = (float)($r['reward_amount']     ?? 0);

        $totalInvoice += $invAmt;
        $totalReward  += $rewAmt;
        $totalRemain  += $remain;

        // Running Payable: add this row's remaining to previous running amount
        $runningPayable += $remain;
      @endphp
      <tr>
        <td style="text-align:center; line-height:1.2;">
          <strong>{{ strtoupper($r['invoice_no']) }}</strong><br>
          ({{ \Carbon\Carbon::parse($r['invoice_date'])->format('d-m-Y') }})
        </td>
        <td class="right">₹ {{ number_format($invAmt, 2) }}</td>
        <td class="right">₹ {{ number_format($remain, 2) }}</td>
        <td class="right">{{ number_format($perc, 2) }}%</td>
        <td class="right">₹ {{ number_format($rewAmt, 2) }}</td>
        <td style="text-align:center;">
          {{ \Carbon\Carbon::parse($r['last_payment_date'])->format('d-m-Y') }}
        </td>
        <td class="right"><strong>₹ {{ number_format($runningPayable, 2) }}</strong></td> <!-- NEW -->
      </tr>
    @empty
      <tr>
        <td colspan="7" style="text-align:center;">No Invoices Found</td>
      </tr>
    @endforelse

    {{-- Totals row --}}
    <tr>
      <td style="text-align:center;"><strong>Total</strong></td>
      <td class="right"><strong>₹ {{ number_format($totalInvoice, 2) }}</strong></td>
      <td class="right"><strong>₹ {{ number_format($totalRemain, 2) }}</strong></td>
      <td></td>
      <td class="right"><strong>₹ {{ number_format($totalReward, 2) }}</strong></td>
      <td></td>
      <td></td>
    </tr>
  </tbody>
</table>

{{-- ===== Summary: Total Payable & Total Cash Discount (place this BEFORE the Bank Details table) ===== --}}
@php
  // Final running payable already includes Previous Due + each row's Remaining
  $finalPayable      = (float) ($runningPayable ?? 0);
  $totalCashDiscount = (float) ($totalReward    ?? 0);
@endphp

<table width="100%" border="0" cellpadding="10" cellspacing="0" class="no-border" style="margin: 14px 0;">
  <tr>
    <td width="50%" style="text-align: center; font-size: 14px;">
      <strong>Total Payable Amount</strong><br>
      <span class="muted">&#8377; {{ number_format($finalPayable, 2) }}</span>
    </td>
    <td width="50%" style="text-align: center; font-size: 14px;">
      <strong>Total Cash Discount Amount</strong><br>
      <span class="muted">&#8377; {{ number_format($totalCashDiscount, 2) }}</span>
    </td>
  </tr>
</table>

<!-- Bank Details and QR Code -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" class="no-border" style="margin-top: 20px;">
  <tr>
    <td width="50%" style="font-size: 14px;">
      <strong>Bank Details:</strong><br>
      A/C Name: ACE TOOLS PRIVATE LIMITED<br>
      Branch : NAJAFGARH ROAD, NEW DELHI<br>
      A/C No: 235605001202<br>
      IFSC Code: ICIC0002356<br>
      Bank Name: ICICI Bank<br>
    </td>
    <td width="50%" style="text-align: right;">
      <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width: 100px; height: 100px;">
      <br><span style="font-size: 12px; color:#333;">Scan the barcode with any UPI app to pay.</span>
    </td>
  </tr>
</table>

<!-- Footer -->
<table width="100%" border="0" cellpadding="0" cellspacing="0" class="no-border">
  <tr bgcolor="#174e84">
    <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
      ACE TOOLS PVT LTD - REWARDS
    </td>
  </tr>
</table>
{{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
</body>
</html>