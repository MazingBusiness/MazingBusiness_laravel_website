<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Packing List</title>
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

        /* Outer dark border */
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

        .item-row td {
            text-align: center;
        }
        .item-row .desc-cell {
            text-align: left;
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
        .bank-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bank-details-table td {
            border: none;
            padding: 2px 0;
        }
        .bank-label {
            width: 120px;
            font-weight: bold;
            vertical-align: top;
        }
        .bank-value {
            width: calc(100% - 120px);
            vertical-align: top;
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

        .stamp-cell img {
            display: block;
            margin: 0 auto;
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

        $invoiceNo   = $ci->supplier_invoice_no ?: ('PL-' . ($bl->bl_no ?? $bl->id));
        $invoiceDate = $ci->supplier_invoice_date
            ? \Carbon\Carbon::parse($ci->supplier_invoice_date)->format('d/m/Y')
            : now()->format('d/m/Y');

        $totalQty      = (float) ($totals['qty']     ?? 0);
        $totalCartons  = (float) ($totals['cartons'] ?? 0);
        $totalGrossWt  = (float) ($totals['wt']      ?? 0);
        $totalCbm      = (float) ($totals['cbm']     ?? 0);
        $totalNetWt    = $totalGrossWt * 0.75;

        $importerName = optional($company)->company_name ?? 'Importer Name';

        $importerAddressLine = trim(
            ($company->address_1 ?? '') . ' ' .
            ($company->address_2 ?? '') . ' ' .
            ($company->city ?? '')      . ' ' .
            ($company->state ?? '')     . ' ' .
            ($company->country ?? '')   . ' ' .
            ($company->pincode ?? '')
        );

        $gstin = $company->gstin ?? $company->gst_no ?? '';
        $pan   = $company->pan ?? $company->pan_no ?? $company->iec_no ?? '';

        $fromCity      = strtoupper($bl->port_of_loading ?? 'NINGBO');
        $originCountry = strtoupper($bl->country_of_origin ?? ($supplier->country ?? ''));
        $fromDisplay   = $originCountry ? ($fromCity . ', ' . $originCountry) : $fromCity;

        /** @var \App\Models\SupplierBankAccount|null $bank */
        $bank = $supplier->defaultBankAccount ?? ($supplier->bankAccounts->first() ?? null);

        $beneficiaryName    = $bank->beneficiary_name    ?? $supplier->supplier_name;
        $beneficiaryAddress = $bank->beneficiary_address ?? $supplier->address;
        $bankName           = $bank->account_bank_name   ?? '';
        $bankAddress        = $bank->account_bank_address ?? '';
        $accountNumber      = $bank->account_number      ?? '';
        $swiftCode          = $bank->account_swift_code  ?? '';
        $interBank          = $bank->intermediary_bank_name  ?? '';
        $interSwift         = $bank->intermediary_swift_code ?? '';

        // Dynamic stamps
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
                {{-- SUPPLIER NAME --}}
                <tr>
                    <td colspan="7">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- SUPPLIER ADDRESS --}}
                <tr>
                    <td colspan="7">
                        <div class="company-sub">
                            @php
                                $supplierLine = trim(
                                    ($supplier->address ?? '') . ' ' .
                                    ($supplier->city ?? '')    . ' ' .
                                    ($supplier->country ?? '')
                                );
                            @endphp
                            {{ $supplierLine }}
                        </div>
                    </td>
                </tr>

                {{-- TITLE: PACKING LIST --}}
                <tr>
                    <td colspan="7" class="title-cell">
                        PACKING LIST
                    </td>
                </tr>

                {{-- TO / INVOICE NO --}}
                <tr>
                    <td style="width:12%;" class="label">TO,</td>
                    <td class="text-center" colspan="4">{{ $importerName }}</td>
                    <td style="width:16%;" class="label">INVOICE NO.:</td>
                    <td>{{ $invoiceNo }}</td>
                </tr>

                {{-- BUYER ADDRESS / DATE --}}
                <tr>
                    <td colspan="5" class="text-center">{{ $importerAddressLine }}</td>
                    <td class="label">Date</td>
                    <td>{{ $invoiceDate }}</td>
                </tr>

                {{-- GSTIN + PAN --}}
                <tr>
                    <td colspan="5" class="text-center">
                        @if($gstin) GSTIN: {{ $gstin }} @endif
                        @if($gstin && $pan) &nbsp;&nbsp; @endif
                        @if($pan) PAN: {{ $pan }} @endif
                    </td>
                    <td></td>
                    <td></td>
                </tr>

                {{-- TERMS / FROM --}}
                <tr>
                    <td class="label">TERMS:</td>
                    <td colspan="4">FOB {{ $fromDisplay }}</td>
                    <td></td>
                    <td></td>
                </tr>

                {{-- HEADER ROW --}}
                <tr>
                    <th style="width:5%;"  class="text-center">S NO</th>
                    <th style="width:38%;" class="text-center">Descriptions</th>
                    <th style="width:12%;" class="text-center">Qty (pcs)</th>
                    <th style="width:12%;" class="text-center">Carton</th>
                    <th style="width:11%;" class="text-center">T G.W</th>
                    <th style="width:11%;" class="text-center">T N.W</th>
                    <th style="width:11%;" class="text-center">T CBM</th>
                </tr>

                {{-- ITEMS (max 10 rows; numbering sirf filled rows pe) --}}
                @php
                    $sno      = 1;
                    $rowCount = $rows->count();
                @endphp

                @foreach($rows as $row)
                @php
                    /** @var \App\Models\CiSummary $row */
                    $qty     = (float) $row->item_quantity;
                    $cartons = (float) $row->cartons_total;
                    $gross   = (float) $row->weight_total;
                    $net     = $gross * 0.75;
                    $cbm     = (float) $row->cbm_total;

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
                    <td>{{ number_format($cartons, 0) }}</td>
                    <td>{{ number_format($gross, 2) }}</td>
                    <td>{{ number_format($net, 2) }}</td>
                    <td>{{ number_format($cbm, 3) }}</td>
                </tr>
                @endforeach

                {{-- PAD BLANK ROWS TILL 10 (S NO bhi blank) --}}
                @for($i = $rowCount + 1; $i <= 10; $i++)
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td class="desc-cell">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor

                {{-- TOTAL --}}
                <tr>
                    <td colspan="2" class="text-center"><strong>Total</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalQty, 0) }}</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalCartons, 0) }}</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalGrossWt, 2) }}</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalNetWt, 2) }}</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalCbm, 3) }}</strong></td>
                </tr>

                {{-- BOTTOM: 3 BLOCKS (BANK + SUPPLIER STAMP + BUYER STAMP) --}}
                <tr>
                    <td colspan="7" class="bottom-row-wrapper">
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
