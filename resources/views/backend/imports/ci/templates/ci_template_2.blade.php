{{-- resources/views/.../ci_template_2.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Commercial Invoice</title>
    <style>
        @page {
            margin: 10mm 10mm;
        }

        body {
            font-family: "Times New Roman", "DejaVu Serif", serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .invoice-wrapper {
            width: 100%;
        }

        /* Outer thick border – same as PL-2 */
        .invoice-outer {
            width: 90%;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 2px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            border: none; /* table level border off – only cell borders */
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
        }

        /* Edge pe double line na aaye – outer box hi dikhe */
        .invoice-table tr th:first-child,
        .invoice-table tr td:first-child {
            border-left: none;
        }
        .invoice-table tr th:last-child,
        .invoice-table tr td:last-child {
            border-right: none;
        }

        /* Top / header rows – no inner borders */
        .no-border-all td {
            border: none !important;
        }

        /* COMPANY ADDRESS row – sirf bottom line (like PL-2) */
        .bottom-border-only td {
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            border-bottom: 1px solid #000 !important;
        }

        /* TO / INVOICE row – bilkul border nahi chahiye */
        .to-invoice-row td {
            border: none !important;
        }

        /* compact padding */
        .tight-header td {
            padding-top: 1px;
            padding-bottom: 1px;
        }

        .nowrap { white-space: nowrap; }

        .company-main {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .company-sub {
            font-size: 10px;
            text-align: center;
        }

        .title-cell {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .label { font-weight: bold; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        .item-row td { text-align: center; }
        .item-row .desc-cell { text-align: left; }

        .bank-cell,
        .stamp-cell {
            text-align: center;
            vertical-align: top;
            font-size: 10px;
            font-weight: bold;
        }

        .bank-title {
            margin-top: 20px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .bank-details {
            font-size: 9px;
            line-height: 1.25;
            text-align: left;
            font-weight: normal;
        }

        .stamp-label {
            margin-top: 20px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@php
    $company     = $company ?? $bl->importCompany;
    $uploadsBase = env('UPLOADS_BASE_URL', 'https://mazingbusiness.com/public');
@endphp

@foreach($invoices as $index => $invoice)
    @php
        /** @var \App\Models\CiDetail $ci */
        $ci       = $invoice->ci;
        $supplier = $invoice->supplier;
        $rows     = $invoice->rows;
        $totals   = $invoice->totals ?? [];

        // Invoice number/date – CI prefix
        $invoiceNo   = $ci->supplier_invoice_no ?: ('CI-' . ($bl->bl_no ?? $bl->id));
        $invoiceDate = $ci->supplier_invoice_date
            ? \Carbon\Carbon::parse($ci->supplier_invoice_date)->format('d/m/Y')
            : now()->format('d/m/Y');

        $totalQty   = (float) ($totals['qty']   ?? 0);
        $totalValue = (float) ($totals['value'] ?? 0);

        $importerName = optional($company)->company_name ?? 'Importer Name';

        $importerAddressLine1 = trim(($company->address_1 ?? ''));
        $importerAddressLine2 = trim(($company->address_2 ?? ''));
        $importerAddressLine3 = trim(
            ($company->city ?? '') . ', ' .
            ($company->state ?? '') . ', ' .
            ($company->country ?? '') . ' ' .
            ($company->pincode ?? '')
        );

        $gstin = $company->gstin ?? $company->gst_no ?? '';
        $pan   = $company->pan ?? $company->pan_no ?? $company->iec_no ?? '';

        /** @var \App\Models\SupplierBankAccount|null $bank */
        $bank = $supplier->defaultBankAccount ?? ($supplier->bankAccounts->first() ?? null);

        $fromCity      = strtoupper($bl->port_of_loading ?? 'SHANGHAI');
        $originCountry = strtoupper($bl->country_of_origin ?? ($supplier->country ?? 'CHINA'));
        $fromDisplay   = $originCountry ? ($fromCity . ', ' . $originCountry) : $fromCity;

        $toCity    = strtoupper($bl->port_of_discharge ?? 'NEW DELHI');
        $toCountry = strtoupper($company->country ?? 'INDIA');

        $beneficiaryName    = $bank->beneficiary_name    ?? $supplier->supplier_name ?? '';
        $beneficiaryAddress = $bank->beneficiary_address ?? $supplier->address       ?? '';
        $bankName           = $bank->account_bank_name   ?? '';
        $bankAddress        = $bank->account_bank_address ?? '';
        $accountNumber      = $bank->account_number      ?? '';
        $swiftCode          = $bank->account_swift_code  ?? '';

        $supplierStampPath = $supplier->stamp
            ? rtrim($uploadsBase, '/') . '/' . ltrim($supplier->stamp, '/')
            : null;

        $buyerStampPath = $company->buyer_stamp ?? null;
        if ($buyerStampPath && !preg_match('#^https?://#i', $buyerStampPath)) {
            $buyerStampPath = rtrim($uploadsBase, '/') . '/' . ltrim($buyerStampPath, '/');
        }

        // For fixed 10 rows
        $rowCount = $rows->count();
    @endphp

    <div class="invoice-wrapper">
        <div class="invoice-outer">
            <table class="invoice-table">

                {{-- COMPANY NAME – no inner borders --}}
                <tr class="no-border-all">
                    <td colspan="8">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- COMPANY ADDRESS – no inner borders, bottom only --}}
                <tr class="no-border-all bottom-border-only">
                    <td colspan="8">
                        <div class="company-sub">
                            @php
                                $supplierLine = trim(
                                    ($supplier->address ?? '') . ', ' .
                                    ($supplier->city ?? '') . ', ' .
                                    ($supplier->country ?? '')
                                );
                            @endphp
                            {{ $supplierLine }}
                        </div>
                    </td>
                </tr>

                {{-- COMMERCIAL INVOICE – only outer box ka effect --}}
                <tr style="border:none;">
                    <td style="border:none;" colspan="8" class="title-cell">
                        COMMERCIAL INVOICE
                    </td>
                </tr>

                {{-- TO / INVOICE NO + DATE – no borders at all (same feel as PL-2) --}}
                <tr class="tight-header to-invoice-row">
                    <td colspan="5" class="text-left">
                        <span class="label">To:</span><br>
                        <span style="margin-left: 20px;"><strong>{{ $importerName }}</strong></span><br>
                        @if($importerAddressLine1)
                            <span style="margin-left: 20px;">{{ $importerAddressLine1 }}</span><br>
                        @endif
                        @if($importerAddressLine2)
                            <span style="margin-left: 20px;">{{ $importerAddressLine2 }}</span><br>
                        @endif
                        @if(trim($importerAddressLine3) !== '')
                            <span style="margin-left: 20px;">{{ $importerAddressLine3 }}</span><br>
                        @endif
                        @if($gstin || $pan)
                            <span style="margin-left: 20px;">
                                @if($gstin)
                                    GST: {{ $gstin }}
                                @endif
                                @if($gstin && $pan) &nbsp;&nbsp; @endif
                                @if($pan)
                                    PAN/IEC: {{ $pan }}
                                @endif
                            </span>
                        @endif
                    </td>
                    <td colspan="3" class="text-right">
                        <div class="nowrap">
                            <span class="label">INVOICE NO:</span>
                            {{ $invoiceNo }}
                        </div>
                        <div class="nowrap" style="margin-top: 2px;">
                            <span class="label">INVOICE DATE:</span>
                            {{ $invoiceDate }}
                        </div>
                    </td>
                </tr>

                {{-- FROM / TO / BY – no borders at all --}}
                <tr class="no-border-all">
                    <td colspan="5" class="text-left">
                        <span class="label">FROM:</span>
                        {{ $fromDisplay }}
                    </td>
                    <td colspan="3" class="text-right">
                        <span class="label">TO:</span>
                        {{ $toCity }}, {{ $toCountry }}
                        &nbsp;&nbsp;<span class="label">BY:</span> SEA
                    </td>
                </tr>

                {{-- HEADER ROW (5 logical columns mapped onto 8 physical columns) --}}
                <tr class="items-header-row">
                    <th style="width:6%;"  class="text-center">S.no</th>
                    <th style="width:48%;" class="text-center" colspan="3">SPECIFICATION</th>
                    <th style="width:12%;" class="text-center">QTY</th>
                    <th style="width:15%;" class="text-center">UNIT PRICE</th>
                    <th style="width:17%;" class="text-center" colspan="2">AMOUNT</th>
                </tr>

                {{-- DATA ITEM ROWS --}}
                @php
                    $sno = 1;
                @endphp
                @foreach($rows as $row)
                    @php
                        $qty    = (float) ($row->item_quantity ?? 0);
                        $price  = (float) ($row->item_dollar_price ?? 0);
                        $amount = (float) ($row->value_total ?? ($qty * $price));
                    @endphp
                    <tr class="item-row">
                        <td>{{ $sno++ }}</td>
                        <td class="desc-cell" colspan="3">{{ $row->item_print_name }}</td>
                        <td>{{ $qty ? number_format($qty, 0) : '' }}</td>
                        <td>{{ $price ? number_format($price, 2) : '' }}</td>
                        <td colspan="2">{{ $amount ? number_format($amount, 2) : '' }}</td>
                    </tr>
                @endforeach

                {{-- BLANK ROWS TILL 10 (same structure / colspans) --}}
                @for($i = $rowCount + 1; $i <= 10; $i++)
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td class="desc-cell" colspan="3">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td colspan="2">&nbsp;</td>
                    </tr>
                @endfor

                {{-- TOTAL ROW --}}
                <tr>
                    <td colspan="4" class="text-center"><strong>TOTAL:</strong></td>
                    <td class="text-center">
                        <strong>{{ $totalQty ? number_format($totalQty, 0) : '' }}</strong>
                    </td>
                    <td></td>
                    <td colspan="2" class="text-center">
                        <strong>{{ $totalValue ? number_format($totalValue, 2) : '' }}</strong>
                    </td>
                </tr>

                {{-- BANK DETAILS + STAMPS – same layout style as PL-2 --}}
                <tr style="height: 120px;">
                    <td colspan="3" class="bank-cell">
                        <div class="bank-title">BANK DETAILS</div>
                        @if($bank)
                            <div class="bank-details">
                                <strong>Beneficiary:</strong> {{ $beneficiaryName }}<br>
                                @if($beneficiaryAddress)
                                    <strong>Address:</strong> {{ $beneficiaryAddress }}<br>
                                @endif
                                @if($bankName)
                                    <strong>Bank:</strong> {{ $bankName }}<br>
                                @endif
                                @if($bankAddress)
                                    <strong>Bank Address:</strong> {{ $bankAddress }}<br>
                                @endif
                                @if($accountNumber)
                                    <strong>A/C No.:</strong> {{ $accountNumber }}<br>
                                @endif
                                @if($swiftCode)
                                    <strong>SWIFT:</strong> {{ $swiftCode }}<br>
                                @endif
                            </div>
                        @else
                            <div class="bank-details">
                                (No bank details configured for this supplier)
                            </div>
                        @endif
                    </td>

                    <td colspan="3" class="stamp-cell">
                        <div class="stamp-label">SUPPLIER STAMP</div>
                        @if($supplierStampPath)
                            <img src="{{ $supplierStampPath }}"
                                 alt="Supplier Stamp"
                                 style="max-width:130px; max-height:60px; display:block; margin:0 auto;">
                        @endif
                    </td>

                    <td colspan="2" class="stamp-cell">
                        <div class="stamp-label">BUYER STAMP</div>
                        @if($buyerStampPath)
                            <img src="{{ $buyerStampPath }}"
                                 alt="Buyer Stamp"
                                 style="max-width:130px; max-height:60px; display:block; margin:0 auto;">
                        @endif
                    </td>
                </tr>

            </table>
        </div>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
