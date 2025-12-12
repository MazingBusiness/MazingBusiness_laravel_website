<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Early Payment Manager Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#174e84; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; color:#174e84; }
        th { background:#f1f1f1; }
        .no-border td, .no-border th { border: 0 !important; }
        .right { text-align:right; }
        .muted { color:#333; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

@php
    $totalCustomers = count($customers ?? []);
    $custIndex      = 0;
@endphp

@foreach($customers as $cust)
    @php
        $custIndex++;

        /** Per-customer aliases */
        $party_code    = $cust['party_code']   ?? '';
        $address       = $cust['address']      ?? null;
        $overdueAmount = (float)($cust['overdueAmount'] ?? 0);
        $dueAmount     = (float)($cust['dueAmount']     ?? 0);
        $previousDue   = (float)($cust['previousDue']   ?? 0);
        $rows          = $cust['rows']         ?? [];

        $customerName  = $address->company_name ?? ($cust['customer_name'] ?? $party_code);
        $managerLabel  = $managerUser->name ?? $manager->name ?? ('Manager #'.$manager->id);
    @endphp

    <!-- Header -->
    <table width="100%" border="0" cellpadding="0" cellspacing="0" class="no-border">
        <tr>
            <td style="text-align: right;">
                <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png"
                     width="100%" alt="Header Image" style="display:block;" />
            </td>
        </tr>
    </table>

    <!-- Static Address and Customer / Manager Details -->
    <table width="100%" border="0" cellpadding="10" cellspacing="0"
           class="no-border" style="margin-top: 20px;">
        <tr>
            <td width="50%" style="text-align: left; font-size: 14px; font-weight: bold;">
                ACE TOOLS PRIVATE LIMITED<br>
                Building No./Flat No.: Khasra No. 58/15,<br>
                Pal Colony, Village Rithala,Delhi<br>
                New Delhi - 110085
            </td>
            <td width="50%" style="text-align: right; font-size: 14px;">
                <strong>Manager:</strong> {{ $managerLabel }}<br>
                <strong>Customer:</strong> {{ $customerName }}<br>
                Party Code: {{ $party_code }}<br>
                {{ $address->address ?? '' }}<br>
                {{ $address->address_2 ?? '' }}<br>
                Pincode: {{ $address->postal_code ?? '' }}<br>
            </td>
        </tr>
    </table>

    <!-- Due / Overdue info block -->
    <table width="100%" border="0" cellpadding="10" cellspacing="0"
           class="no-border" style="margin-top: 10px;">
        <tr>
            <td width="50%" style="text-align: center; font-size: 14px;">
                <strong>Overdue Balance</strong><br>
                <span class="muted">&#8377; {{ number_format($overdueAmount, 2) }}</span>
            </td>
            <td width="50%" style="text-align: center; font-size: 14px;">
                <strong>Due Amount</strong><br>
                <span class="muted">&#8377; {{ number_format($dueAmount, 2) }}</span>
            </td>
        </tr>
    </table>

    <!-- Invoices Table (per customer) -->
    <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="text-align:center;">Invoice No</th>
                <th style="text-align:center;">Invoice Amount</th>
                <th style="text-align:center;">Remaining Amount</th>
                <th style="text-align:center;">Cash Discount %</th>
                <th style="text-align:center;">Cash Discount Amount</th>
                <th style="text-align:center;">Last Payment Date</th>
                <th style="text-align:center;">Payable Amount</th>
            </tr>
        </thead>

        <tbody>
            @php
                $totalInvoice   = 0;
                $totalReward    = 0;
                $totalRemain    = 0;
                $runningPayable = $previousDue; // previous due row
            @endphp

            {{-- Fixed row directly under header --}}
            <tr>
                <td style="text-align:center;">
                    <strong>Previous Due Amount</strong>
                </td>
                <td class="right">—</td>
                <td class="right">&#8377; {{ number_format($previousDue, 2) }}</td>
                <td class="right">—</td>
                <td class="right">—</td>
                <td style="text-align:center;">—</td>
                <td class="right"><strong>&#8377; {{ number_format($runningPayable, 2) }}</strong></td>
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

                    $runningPayable += $remain;
                @endphp
                <tr>
                    <td style="text-align:center; line-height:1.2;">
                        <strong>{{ strtoupper($r['invoice_no']) }}</strong><br>
                        ({{ \Carbon\Carbon::parse($r['invoice_date'])->format('d-m-Y') }})
                    </td>
                    <td class="right">&#8377; {{ number_format($invAmt, 2) }}</td>
                    <td class="right">&#8377; {{ number_format($remain, 2) }}</td>
                    <td class="right">{{ number_format($perc, 2) }}%</td>
                    <td class="right">&#8377; {{ number_format($rewAmt, 2) }}</td>
                    <td style="text-align:center;">
                        {{ \Carbon\Carbon::parse($r['last_payment_date'])->format('d-m-Y') }}
                    </td>
                    <td class="right"><strong>&#8377; {{ number_format($runningPayable, 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">No Invoices Found</td>
                </tr>
            @endforelse

            {{-- Totals row --}}
            <tr>
                <td style="text-align:center;"><strong>Total</strong></td>
                <td class="right"><strong>&#8377; {{ number_format($totalInvoice, 2) }}</strong></td>
                <td class="right"><strong>&#8377; {{ number_format($totalRemain, 2) }}</strong></td>
                <td></td>
                <td class="right"><strong>&#8377; {{ number_format($totalReward, 2) }}</strong></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- Summary: Total Payable & Total Cash Discount --}}
    @php
        $finalPayable      = $runningPayable;   // prev due + all remainings
        $totalCashDiscount = $totalReward;
    @endphp

    <table width="100%" border="0" cellpadding="10" cellspacing="0"
           class="no-border" style="margin: 14px 0;">
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
    <table width="100%" border="0" cellpadding="10" cellspacing="0"
           class="no-border" style="margin-top: 20px;">
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
                <img src="https://mazingbusiness.com/public/assets/img/barcode.png"
                     alt="Scan QR Code" style="width: 100px; height: 100px;">
                <br><span style="font-size: 12px; color:#333;">
                    Scan the barcode with any UPI app to pay.
                </span>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <table width="100%" border="0" cellpadding="0" cellspacing="0" class="no-border">
        <tr bgcolor="#174e84">
            <td style="height: 40px; text-align: center; color: #fff;
                       font-family: Arial; font-weight: bold;">
                ACE TOOLS PVT LTD - REWARDS
            </td>
        </tr>
    </table>

    {{-- Page break between customers --}}
    @if($custIndex < $totalCustomers)
        <div class="page-break"></div>
    @endif

@endforeach

</body>
</html>