{{-- resources/views/.../ci_template_1.blade.php --}}
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
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
        }

        /* Rows jahan inner border nahi chahiye (company header, TO row, invoice no/date etc.) */
        .no-border td {
            border: none !important;
        }

        /* Invoice no / date rows – gap kam */
        .tight-header td {
            padding-top: 1px;
            padding-bottom: 1px;
        }

        /* Force single line */
        .nowrap {
            white-space: nowrap;
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
        }

        .label { font-weight: bold; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }

        /* Product table rows */
        .item-row td {
            text-align: center;
        }
        .item-row .desc-cell {
            text-align: left;
        }

        /* Stamp cells */
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
            font-size: 10px;
        }
        .stamp-sub {
            display: block;
            margin-top: 8px;
            font-weight: normal;
            font-size: 9px;
        }

        .bottom-inner-table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Inner bottom table – NO bottom border, to avoid double line */
        .bottom-inner-table td {
            border-top: 1px solid #000;
            border-right: 1px solid #000;
            border-left: 1px solid #000;
            border-bottom: 0;
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
            margin-bottom: 3px;
            font-size: 9px;
        }

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

        $importerAddressLine = trim(
            ($company->address_1 ?? '') . ', ' .
            ($company->address_2 ?? '') . ', ' .
            ($company->city ?? '') . ', ' .
            ($company->state ?? '') . ', ' .
            ($company->country ?? '') . ', ' .
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

        $beneficiaryName    = $bank->beneficiary_name    ?? $supplier->supplier_name;
        $beneficiaryAddress = $bank->beneficiary_address ?? $supplier->address;
        $bankName           = $bank->account_bank_name   ?? '';
        $bankAddress        = $bank->account_bank_address ?? '';
        $accountNumber      = $bank->account_number      ?? '';
        $swiftCode          = $bank->account_swift_code  ?? '';
        $interBank          = $bank->intermediary_bank_name  ?? '';
        $interSwift         = $bank->intermediary_swift_code ?? '';

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
            <table class="invoice-table" >

                {{-- COMPANY NAME --}}
                <tr class="no-border">
                    <td colspan="5">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- COMPANY ADDRESS --}}
                <tr class="no-border">
                    <td colspan="5">
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

                {{-- INVOICE NO / DATE – right side, tight, single line --}}
                <tr class="no-border tight-header">
                    <td colspan="3"></td>
                    <td colspan="2" class="text-right nowrap">
                        <span class="label">INVOICE NO.:</span>
                        {{ $invoiceNo }}
                    </td>
                </tr>
                <tr class="no-border tight-header">
                    <td colspan="3"></td>
                    <td colspan="2" class="text-right nowrap">
                        <span class="label">DATE:</span>
                        {{ $invoiceDate }}
                    </td>
                </tr>

                {{-- TITLE --}}
                <tr class="no-border">
                    <td colspan="5" class="title-cell">
                        COMMERCIAL INVOICE
                    </td>
                </tr>

                {{-- TO (centered) / FROM-TO-BY --}}
                <tr class="no-border">
                    <td style="width:10%; text-align:center;" class="label">TO:</td>
                    <td colspan="2" class="text-center">
                        <strong>{{ $importerName }}</strong><br>
                        {{ $importerAddressLine }}<br>
                        @if($gstin)
                            GST: {{ $gstin }}
                        @endif
                        @if($gstin && $pan) &nbsp;&nbsp; @endif
                        @if($pan)
                            PAN: {{ $pan }}
                        @endif
                    </td>
                    <td colspan="2" class="text-left">
                        <div><span class="label">FROM:</span> {{ $fromDisplay }}</div>
                        <div>
                            <span class="label">TO:</span> {{ $toCity }}, {{ $toCountry }}
                            &nbsp;&nbsp;<span class="label">BY:</span> SEA
                        </div>
                    </td>
                </tr>

                {{-- TABLE HEADER --}}
                <tr >
                    <th style="width:8%;"  class="text-center">S.no</th>
                    <th style="width:48%;" class="text-center">SPECIFICATION</th>
                    <th style="width:12%;" class="text-center">QTY</th>
                    <th style="width:15%;" class="text-center">UNIT PRICE</th>
                    <th style="width:17%;" class="text-center">AMOUNT</th>
                </tr>

                {{-- ITEMS --}}
                @php
                    $sno      = 1;
                    $rowCount = $rows->count();
                @endphp
                @foreach($rows as $row)
                    @php
                        /** @var \App\Models\CiSummary $row */
                        $qty    = (float) $row->item_quantity;
                        $price  = (float) $row->item_dollar_price;
                        $amount = (float) ($row->value_total ?? ($qty * $price));
                    @endphp
                    <tr class="item-row">
                        <td>{{ $sno++ }}</td>
                        <td class="desc-cell">{{ $row->item_print_name }}</td>
                        <td>{{ number_format($qty, 0) }}</td>
                        <td>{{ number_format($price, 2) }}</td>
                        <td>{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach

                {{-- BLANK ROWS to keep table height uniform --}}
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
                    <td colspan="2" class="text-center"><strong>TOTAL:</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalQty, 0) }}</strong></td>
                    <td></td>
                    <td class="text-center"><strong>{{ number_format($totalValue, 2) }}</strong></td>
                </tr>

                {{-- BANK DETAILS + STAMPS --}}
                <tr>
                    <td colspan="5" style="padding:0;">
                        <table class="bottom-inner-table">
                            <tr>
                                {{-- BANK DETAILS --}}
                                <td class="bank-details" style="width:45%; border: none;">
                                    <div class="bank-details-heading">BANK DETAILS</div>
                                    @if($bank)
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

                                {{-- SUPPLIER STAMP --}}
                                <td class="stamp-cell" style="width:27%; border-top: none;">
                                    <div class="stamp-label">SUPPLIER STAMP</div>
                                    @if($supplierStampPath)
                                        <img src="{{ $supplierStampPath }}"
                                             alt="Supplier Stamp"
                                             style="max-width:100px;height:auto;display:block;margin:0 auto;">
                                    @endif
                                    <span class="stamp-sub"></span>
                                </td>

                                {{-- BUYER STAMP --}}
                                <td class="stamp-cell" style="width:28%; border-right: none; border-top: none;">
                                    <div class="stamp-label">BUYER STAMP</div>
                                    @if($buyerStampPath)
                                        <img src="{{ $buyerStampPath }}"
                                             alt="Buyer Stamp"
                                             style="max-width:100px;height:auto;display:block;margin:0 auto;">
                                    @endif
                                    <span class="stamp-sub"></span>
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
