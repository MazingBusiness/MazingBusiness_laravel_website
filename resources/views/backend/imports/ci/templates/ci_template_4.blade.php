{{-- resources/views/.../ci_template_4.blade.php --}}
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
            font-size: 10px;
            margin: 0;
            padding: 0;
            line-height: 1.15;
        }

        .invoice-wrapper { width: 100%; }

        /* Outer border */
        .invoice-outer {
            width: 90%;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 2px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
        }

        .invoice-table tr th:first-child,
        .invoice-table tr td:first-child {
            border-left: none;
        }
        .invoice-table tr th:last-child,
        .invoice-table tr td:last-child {
            border-right: none;
        }

        /* Top header rows – no inner borders */
        .top-section-row td {
            border: none !important;
            padding: 1px 3px;
        }

        .company-main {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .company-sub {
            font-size: 9px;
            text-align: center;
        }

        .title-cell {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .label { font-weight: bold; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        .nowrap { white-space: nowrap; }

        .item-row td { text-align: center; }
        .item-row .desc-cell { text-align: left; }

        .bank-cell,
        .stamp-cell {
            text-align: center;
            vertical-align: top;
            font-size: 9px;
            font-weight: bold;
        }

        .bank-title {
            margin-top: 10px;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .bank-details {
            font-size: 8px;
            line-height: 1.2;
            text-align: left;
            font-weight: normal;
        }

        .stamp-label {
            margin-top: 10px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .address-block {
            margin-left: 15px;
            font-size: 9px;
            line-height: 1.05;
            margin-top: 2px;
        }
        .address-line { display: block; }

        .value-underline {
            /* currently not used, kept for future */
            display: inline-block;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
            min-width: 120px;
        }

        .page-break { page-break-after: always; }
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

        $invoiceNo   = $ci->supplier_invoice_no ?: ('CI-' . ($bl->bl_no ?? $bl->id));
        $invoiceDate = $ci->supplier_invoice_date
            ? \Carbon\Carbon::parse($ci->supplier_invoice_date)->format('d/m/Y')
            : now()->format('d/m/Y');

        $totalQty   = (float) ($totals['qty']   ?? 0);
        $totalValue = (float) ($totals['value'] ?? 0);

        $importerName = optional($company)->company_name ?? 'Importer Name';

        $importerAddressLine1 = trim($company->address_1 ?? '');
        $importerAddressLine2 = trim($company->address_2 ?? '');
        $importerAddressLine3 = trim(
            ($company->city ?? '') .
            (isset($company->state)   ? ', '.$company->state   : '') .
            (isset($company->country) ? ', '.$company->country : '') .
            (isset($company->pincode) ? ' '.$company->pincode  : '')
        );

        $gstin = $company->gstin ?? $company->gst_no ?? '';
        $pan   = $company->pan ?? $company->pan_no ?? $company->iec_no ?? '';

        /** @var \App\Models\SupplierBankAccount|null $bank */
        $bank = $supplier->defaultBankAccount ?? ($supplier->bankAccounts->first() ?? null);

        $fromCity      = strtoupper($bl->port_of_loading ?? 'NINGBO');
        $originCountry = strtoupper($bl->country_of_origin ?? ($supplier->country ?? 'CHINA'));
        $fromDisplay   = $fromCity;

        $toCity    = strtoupper($bl->port_of_discharge ?? 'NHAVA SHEVA');
        $toCountry = strtoupper($company->country ?? 'INDIA');

        $transportDetails = $bl->transport_details ?? $bl->shipment_mode ?? 'Sea';
        $originOfGoods    = $originCountry ?: 'CHINA';

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

        $rowCount = $rows->count();
        $maxRows  = 10;   // fixed 10 rows
    @endphp

    <div class="invoice-wrapper">
        <div class="invoice-outer">
            <table class="invoice-table">

                {{-- COMPANY NAME --}}
                <tr class="top-section-row">
                    <td colspan="8">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- COMPANY ADDRESS --}}
                <tr class="top-section-row">
                    <td colspan="8">
                        <div class="company-sub">
                            @php
                                $supplierLine = trim(
                                    ($supplier->address ?? '') . ', ' .
                                    ($supplier->city ?? '')   . ', ' .
                                    ($supplier->country ?? '')
                                );
                            @endphp
                            {{ $supplierLine }}
                        </div>
                    </td>
                </tr>

                {{-- COMMERCIAL INVOICE TITLE --}}
                <tr class="top-section-row">
                    <td colspan="8"
                        class="title-cell"
                        style="border-bottom:1px solid #000 !important;">
                        COMMERCIAL INVOICE
                    </td>
                </tr>

                {{-- TO + INVOICE NO / DATE --}}
                <tr class="top-section-row">
                    <td colspan="4" class="text-left">
                        <span class="label">To:</span>
                        <div class="address-block">
                            <span class="address-line"><strong>{{ $importerName }}</strong></span>
                            @if($importerAddressLine1)
                                <span class="address-line">{{ $importerAddressLine1 }}</span>
                            @endif
                            @if($importerAddressLine2)
                                <span class="address-line">{{ $importerAddressLine2 }}</span>
                            @endif
                            @if($importerAddressLine3)
                                <span class="address-line">{{ $importerAddressLine3 }}</span>
                            @endif

                            @if($gstin || $pan)
                                <span class="address-line">
                                    @if($gstin)
                                        GSTIN: {{ $gstin }}
                                    @endif
                                    @if($gstin && $pan) &nbsp;&nbsp; @endif
                                    @if($pan)
                                        PAN/IEC: {{ $pan }}
                                    @endif
                                </span>
                            @endif
                        </div>
                    </td>
                    <td colspan="4" class="text-right" style="font-size:9px;">
                        <div class="nowrap">
                            <span class="label">INVOICE NO:</span>
                            <span style="margin-left:4px;">
                                {{ $invoiceNo }}
                            </span>
                        </div>
                        <div class="nowrap" style="margin-top:1px;">
                            <span class="label">INVOICE DATE:</span>
                            <span style="margin-left:4px;">
                                {{ $invoiceDate }}
                            </span>
                        </div>
                    </td>
                </tr>

                {{-- small gap --}}
                <tr class="top-section-row">
                    <td colspan="8" style="height:4px;"></td>
                </tr>

                {{-- FROM / TRANSPORT DETAILS --}}
                <tr class="top-section-row">
                    <td class="text-left" style="width:8%;"><span class="label">From:</span></td>
                    <td colspan="3" class="text-left" style="width:42%;">
                        {{ $fromDisplay }}
                    </td>
                    <td class="text-left" style="width:20%;">
                        <span class="label">Transport details:</span>
                    </td>
                    <td colspan="3" class="text-left" style="width:30%;">
                        {{ $transportDetails }}
                    </td>
                </tr>

                {{-- TO / ORIGIN OF GOODS --}}
                <tr class="top-section-row">
                    <td class="text-left"><span class="label">To:</span></td>
                    <td colspan="3" class="text-left">
                        {{ $toCity }}, {{ $toCountry }}
                    </td>
                    <td class="text-left">
                        <span class="label">Origin of Goods:</span>
                    </td>
                    <td colspan="3" class="text-left">
                        {{ $originOfGoods }}
                    </td>
                </tr>

                {{-- ITEMS HEADER --}}
                <tr>
                    <th style="width:6%;"  class="text-center">S.no</th>
                    <th style="width:44%;" class="text-center" colspan="3">Description of Goods</th>
                    <th style="width:10%;" class="text-center">Quantity</th>
                    <th style="width:15%;" class="text-center">Unit price (USD)</th>
                    <th style="width:15%;" class="text-center" colspan="2">Amount (USD)</th>
                </tr>

                {{-- FOB / FROM ROW under Unit price & Amount --}}
                <tr>
                    <td></td>
                    <td colspan="3"></td>
                    <td></td>
                    <td colspan="3" class="text-center">
                        FOB {{ $fromDisplay }}
                    </td>
                </tr>

                {{-- DATA ITEM ROWS --}}
                @php $sno = 1; @endphp
                @foreach($rows as $row)
                    @php
                        $qty    = (float) ($row->item_quantity     ?? 0);
                        $price  = (float) ($row->item_dollar_price ?? 0);
                        $amount = (float) ($row->value_total       ?? ($qty * $price));
                    @endphp
                    <tr class="item-row">
                        <td>{{ $sno++ }}</td>
                        <td class="desc-cell" colspan="3">{{ $row->item_print_name }}</td>
                        <td>{{ $qty    ? number_format($qty,   0) : '' }}</td>
                        <td>{{ $price  ? number_format($price, 2) : '' }}</td>
                        <td colspan="2">{{ $amount ? number_format($amount, 2) : '' }}</td>
                    </tr>
                @endforeach

                {{-- BLANK ROWS TILL 10 --}}
                @for($i = $rowCount + 1; $i <= $maxRows; $i++)
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
                    <td colspan="4" class="text-center"><strong>TOTAL</strong></td>
                    <td class="text-center">
                        <strong>{{ $totalQty ? number_format($totalQty, 0) : '' }}</strong>
                    </td>
                    <td></td>
                    <td colspan="2" class="text-center">
                        <strong>{{ $totalValue ? number_format($totalValue, 2) : '' }}</strong>
                    </td>
                </tr>

                {{-- BANK DETAILS + STAMPS --}}
                <tr style="height: 90px;">
                    {{-- Bank details: approx 50% width --}}
                    <td colspan="4" class="bank-cell" style="width:50%;">
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

                    {{-- Supplier stamp --}}
                    <td colspan="2" class="stamp-cell" style="width:25%;">
                        <div class="stamp-label">SUPPLIER STAMP</div>
                        @if($supplierStampPath)
                            <img src="{{ $supplierStampPath }}"
                                 alt="Supplier Stamp"
                                 style="max-width:120px; max-height:50px; display:block; margin:0 auto;">
                        @endif
                    </td>

                    {{-- Buyer stamp --}}
                    <td colspan="2" class="stamp-cell" style="width:25%;">
                        <div class="stamp-label">BUYER STAMP</div>
                        @if($buyerStampPath)
                            <img src="{{ $buyerStampPath }}"
                                 alt="Buyer Stamp"
                                 style="max-width:120px; max-height:50px; display:block; margin:0 auto;">
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
