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

        /* Outer dark border for whole invoice */
        .invoice-outer {
            width: 90%;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 2px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
        }

        .invoice-table tr:first-child td {
            border-top-width: 1.2px;
        }
        .invoice-table tr:last-child td {
            border-bottom-width: 1.2px;
        }
        .invoice-table tr td:first-child,
        .invoice-table tr th:first-child {
            border-left-width: 1.2px;
        }
        .invoice-table tr td:last-child,
        .invoice-table tr th:last-child {
            border-right-width: 1.2px;
        }

        .company-main {
            font-size: 16px;
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
            text-decoration: underline;
        }

        .label { font-weight: bold; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        /* Product table: center all except description */
        .item-row td {
            text-align: center;
        }
        .item-row .desc-cell {
            text-align: left;
        }

        .stamp-cell {
            text-align: center;
            vertical-align: top;
            font-weight: bold;
            font-size: 11px;
            padding-top: 10px;
        }
        .stamp-label {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .stamp-sub {
            display: block;
            margin-top: 8px;
            font-weight: normal;
            font-size: 10px;
        }

        .bottom-row-wrapper {
            padding: 0;
        }
        .bottom-inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bottom-inner-table td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        .bank-details {
            font-size: 9px;
            line-height: 1.25;
            text-align: left;
        }

        .bank-details-heading {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 3px;
        }

        /* 2-column layout for bank lines */
        .bank-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bank-details-table td {
            border: none;
            padding: 1px 0;
            font-size: 9px;
        }
        .bank-details-table .bank-label {
            width: 35%;
            font-weight: bold;
            vertical-align: top;
            white-space: nowrap;
        }
        .bank-details-table .bank-value {
            width: 65%;
            vertical-align: top;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@php
    $company    = $company ?? $bl->importCompany;
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

        // Full importer address line
        $importerAddressLine = trim(
            ($company->address_1 ?? '') . ' ' .
            ($company->address_2 ?? '') . ' ' .
            ($company->city ?? '') . ' ' .
            ($company->state ?? '') . ' ' .
            ($company->country ?? '') . ' ' .
            ($company->pincode ?? '')
        );

        // GSTIN / PAN
        $gstin = $company->gstin ?? $company->gst_no ?? '';
        $pan   = $company->pan ?? $company->pan_no ?? $company->iec_no ?? '';

        /** @var \App\Models\SupplierBankAccount|null $bank */
        $bank = $supplier->defaultBankAccount ?? ($supplier->bankAccounts->first() ?? null);

        // FROM: city + comma + country of origin
        $fromCity      = strtoupper($bl->port_of_loading ?? 'NINGBO');
        $originCountry = strtoupper($bl->country_of_origin ?? ($supplier->country ?? ''));
        $fromDisplay   = $originCountry ? ($fromCity . ', ' . $originCountry) : $fromCity;

        // Bank values
        $beneficiaryName    = $bank->beneficiary_name    ?? $supplier->supplier_name;
        $beneficiaryAddress = $bank->beneficiary_address ?? $supplier->address;
        $bankName           = $bank->account_bank_name   ?? '';
        $bankAddress        = $bank->account_bank_address ?? '';
        $accountNumber      = $bank->account_number      ?? '';
        $swiftCode          = $bank->account_swift_code  ?? '';
        $interBank          = $bank->intermediary_bank_name  ?? '';
        $interSwift         = $bank->intermediary_swift_code ?? '';

        // Dynamic stamp paths
        $supplierStampPath = $supplier->stamp
            ? rtrim($uploadsBase, '/') . '/' . ltrim($supplier->stamp, '/')
            : null;

        $buyerStampPath = $company->buyer_stamp ?? null;
        if ($buyerStampPath && !preg_match('#^https?://#i', $buyerStampPath)) {
            $buyerStampPath = rtrim($uploadsBase, '/') . '/' . ltrim($buyerStampPath, '/');
        }
    @endphp

    <div class="invoice-wrapper">
        <div class="invoice-outer">
            <table class="invoice-table">
                {{-- SUPPLIER NAME (5 columns) --}}
                <tr>
                    <td colspan="5">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- SUPPLIER ADDRESS --}}
                <tr>
                    <td colspan="5">
                        <div class="company-sub">
                            @php
                                $supplierLine = trim(
                                    ($supplier->address ?? '') . ' ' .
                                    ($supplier->city ?? '') . ' ' .
                                    ($supplier->country ?? '')
                                );
                            @endphp
                            {{ $supplierLine }}
                        </div>
                    </td>
                </tr>

                {{-- TITLE --}}
                <tr>
                    <td colspan="5" class="title-cell">
                        COMMERCIAL INVOICE
                    </td>
                </tr>

                {{-- TO / INVOICE NO (still 5 columns) --}}
                <tr>
                    <td style="width:12%;" class="label">TO,</td>
                    <td colspan="2" class="text-center">{{ $importerName }}</td>
                    <td style="width:20%;" class="label">INVOICE NO.:</td>
                    <td>{{ $invoiceNo }}</td>
                </tr>

                {{-- IMPORTER ADDRESS / DATE --}}
                <tr>
                    <td colspan="3" class="text-center">{{ $importerAddressLine }}</td>
                    <td class="label">Date</td>
                    <td>{{ $invoiceDate }}</td>
                </tr>

                {{-- GSTIN / PAN --}}
                <tr>
                    <td colspan="3" class="text-center">
                        @if($gstin) GSTIN: {{ $gstin }} @endif
                        @if($gstin && $pan) &nbsp;&nbsp; @endif
                        @if($pan) PAN: {{ $pan }} @endif
                    </td>
                    <td></td>
                    <td></td>
                </tr>

                {{-- TERMS (FOB + origin) --}}
                <tr>
                    <td class="label">TERMS:</td>
                    <td colspan="2">FOB {{ $fromDisplay }}</td>
                    <td></td>
                    <td></td>
                </tr>

                {{-- HEADER ROW (5 columns) --}}
                <tr>
                    <th style="width:5%;"  class="text-center">S NO</th>
                    <th style="width:47%;" class="text-center">Descriptions</th>
                    <th style="width:15%;" class="text-center">Qty (pcs)</th>
                    <th style="width:15%;" class="text-center">Unit Price (FOB USD)</th>
                    <th style="width:18%;" class="text-center">Amount (USD)</th>
                </tr>

                {{-- ITEMS (max 10 rows; numbering sirf filled rows pe) --}}
                @php
                    $sno = 1;
                    $rowCount = $rows->count();
                @endphp

                @foreach($rows as $row)
                    @php
                        /** @var \App\Models\CiSummary $row */
                        $qty    = (float) $row->item_quantity;
                        $price  = (float) $row->item_dollar_price;
                        $amount = (float) ($row->value_total ?? ($qty * $price));

                        // 🔹 NEW: image URL from import_photo_id
                        $imageUrl = null;
                        if (!empty($row->import_photo_id)) {
                            $imageUrl = uploaded_asset($row->import_photo_id);
                        }
                    @endphp
                    <tr class="item-row">
                        <td>{{ $sno++ }}</td>
                        <td class="desc-cell">
                                {{ $row->item_print_name }}
                        </td>
                        <td>{{ number_format($qty, 0) }}</td>
                        <td>{{ number_format($price, 4) }}</td>
                        <td>{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach

                {{-- BLANK ROWS TILL 10 (S NO bhi blank) --}}
                @for($i = $rowCount + 1; $i <= 10; $i++)
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td class="desc-cell">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor

                {{-- TOTAL --}}
                <tr>
                    <td colspan="2" class="text-center"><strong>Total</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalQty, 0) }}</strong></td>
                    <td></td>
                    <td class="text-center"><strong>{{ number_format($totalValue, 2) }}</strong></td>
                </tr>

                {{-- BOTTOM: BANK + SUPPLIER STAMP + BUYER STAMP --}}
                <tr>
                    <td colspan="5" class="bottom-row-wrapper">
                        <table class="bottom-inner-table">
                            <tr>
                                {{-- BANK DETAILS BLOCK --}}
                                <td class="bank-details" style="width:45%;">
                                    @if($bank)
                                        <div class="bank-details-heading">BANK DETAILS</div>

                                        <table class="bank-details-table">
                                            <tr>
                                                <td class="bank-label">BENEFICIARY:</td>
                                                <td class="bank-value">{{ $beneficiaryName }}</td>
                                            </tr>
                                            <tr>
                                                <td class="bank-label">BENEFICIARY ADDRESS:</td>
                                                <td class="bank-value">{{ $beneficiaryAddress }}</td>
                                            </tr>
                                            <tr>
                                                <td class="bank-label">BANK NAME:</td>
                                                <td class="bank-value">{{ $bankName }}</td>
                                            </tr>
                                            <tr>
                                                <td class="bank-label">BANK ADDRESS:</td>
                                                <td class="bank-value">{{ $bankAddress }}</td>
                                            </tr>
                                            <tr>
                                                <td class="bank-label">ACCOUNT NO.:</td>
                                                <td class="bank-value">{{ $accountNumber }}</td>
                                            </tr>
                                            <tr>
                                                <td class="bank-label">SWIFT CODE:</td>
                                                <td class="bank-value">{{ $swiftCode }}</td>
                                            </tr>
                                            @if($interBank || $interSwift)
                                                <tr>
                                                    <td class="bank-label">INTERMEDIARY BANK:</td>
                                                    <td class="bank-value">
                                                        {{ $interBank }}
                                                        @if($interSwift)
                                                            (SWIFT: {{ $interSwift }})
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        </table>
                                    @else
                                        <div class="bank-details-heading">BANK DETAILS</div>
                                        <table class="bank-details-table">
                                            <tr>
                                                <td class="bank-label">BANK DETAILS:</td>
                                                <td class="bank-value">
                                                    (No bank details configured for this supplier)
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                </td>

                                {{-- SUPPLIER STAMP BLOCK --}}
                                <td class="stamp-cell" style="width:27%; border-right:1px solid #000;">
                                    <div class="stamp-label">SUPPLIER STAMP</div>
                                    @if($supplierStampPath)
                                        <img
                                            src="{{ $supplierStampPath }}"
                                            alt="Supplier Stamp"
                                            style="max-width:100px; height:auto; display:block; margin:0 auto;"
                                        >
                                    @endif
                                    <div class="stamp-sub"></div>
                                </td>

                                {{-- BUYER STAMP BLOCK --}}
                                <td class="stamp-cell" style="width:28%;">
                                    <div class="stamp-label">BUYER STAMP</div>
                                    @if($buyerStampPath)
                                        <img
                                            src="{{ $buyerStampPath }}"
                                            alt="Buyer Stamp"
                                            style="max-width:100px; height:auto; display:block; margin:0 auto;"
                                        >
                                    @endif
                                    <div class="stamp-sub"></div>
                                </td>
                            </tr>
                        </table>
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
