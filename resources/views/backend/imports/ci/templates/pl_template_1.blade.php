{{-- resources/views/.../pl_template_1.blade.php --}}
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

        /* Outer border (same as ci_template_1) */
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

        /* Product / item table rows */
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

        // 🔹 SAME TOTALS LOGIC AS OLD PL
        $totals = $invoice->totals ?? [];

        $totalCtns = (float) ($totals['cartons'] ?? 0);
        $totalQty  = (float) ($totals['qty']     ?? 0);
        $totalGw   = (float) ($totals['wt']      ?? 0);
        $totalCbm  = (float) ($totals['cbm']     ?? 0);
        $totalNw   = $totalGw * 0.75;

        // Packing list ke liye PL- prefix
        $invoiceNo   = $ci->supplier_invoice_no ?: ('PL-' . ($bl->bl_no ?? $bl->id));
        $invoiceDate = $ci->supplier_invoice_date
            ? \Carbon\Carbon::parse($ci->supplier_invoice_date)->format('d/m/Y')
            : now()->format('d/m/Y');

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

        $fromCity      = strtoupper($bl->port_of_loading ?? 'NINGBO');
        $originCountry = strtoupper($bl->country_of_origin ?? ($supplier->country ?? 'CHINA'));
        $fromDisplay   = $fromCity . ', ' . $originCountry;

        $toCity    = strtoupper($bl->port_of_discharge ?? 'NHAVA SHEVA');
        $toCountry = strtoupper($company->country ?? 'INDIA');

        /** @var \App\Models\SupplierBankAccount|null $bank */
        $bank = $supplier->defaultBankAccount ?? ($supplier->bankAccounts->first() ?? null);

        $beneficiaryName    = $bank->beneficiary_name    ?? $supplier->supplier_name ?? '';
        $beneficiaryAddress = $bank->beneficiary_address ?? $supplier->address       ?? '';
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
            <table class="invoice-table">

                {{-- COMPANY NAME (same style as CI) --}}
                <tr class="no-border">
                    <td colspan="8">
                        <div class="company-main">
                            {{ optional($supplier)->supplier_name ?? 'SUPPLIER NAME' }}
                        </div>
                    </td>
                </tr>

                {{-- COMPANY ADDRESS (no inner borders) --}}
                <tr class="no-border">
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

                {{-- INVOICE NO / DATE – right, tight (same pattern as CI) --}}
                <tr class="no-border tight-header">
                    <td colspan="5"></td>
                    <td colspan="3" class="text-right nowrap">
                        <span class="label">INVOICE NO.:</span>
                        {{ $invoiceNo }}
                    </td>
                </tr>
                <tr class="no-border tight-header">
                    <td colspan="5"></td>
                    <td colspan="3" class="text-right nowrap">
                        <span class="label">DATE:</span>
                        {{ $invoiceDate }}
                    </td>
                </tr>

                {{-- TITLE --}}
                <tr class="no-border">
                    <td colspan="8" class="title-cell">
                        PACKING LIST
                    </td>
                </tr>

                {{-- TO (centered) / FROM-TO-BY SEA (same layout as CI) --}}
                <tr class="no-border">
                    <td style="width:10%; text-align:center;" class="label">TO:</td>
                    <td colspan="4" class="text-center">
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
                    <td colspan="3" class="text-left">
                        <div><span class="label">FROM:</span> {{ $fromDisplay }}</div>
                        <div>
                            <span class="label">TO:</span> {{ $toCity }}, {{ $toCountry }}
                            &nbsp;&nbsp;<span class="label">BY:</span> SEA
                        </div>
                    </td>
                </tr>

                {{-- HEADER ROW (packing list columns) --}}
                <tr>
                    <th style="width:6%;"  class="text-center">No</th>
                    <th style="width:34%;" class="text-center">Description of Goods</th>
                    <th style="width:12%;" class="text-center">MODEL NO.</th>
                    <th style="width:8%;"  class="text-center">CTNS</th>
                    <th style="width:10%;" class="text-center">QTY(PCS)</th>
                    <th style="width:10%;" class="text-center">G.W (KG)</th>
                    <th style="width:10%;" class="text-center">N.W (KG)</th>
                    <th style="width:10%;" class="text-center">CBM(M3)</th>
                </tr>

                {{-- ITEM ROWS (same data logic as old PL) --}}
                @php
                    $sno      = 1;
                    $rowCount = $rows->count();
                @endphp
                @foreach($rows as $row)
                    @php
                        $desc  = $row->item_print_name ?? $row->item_name ?? '';
                        $model = $row->item_model_no   ?? $row->part_no ?? '';

                        $ctns = (float) ($row->cartons_total ?? 0);
                        $qty  = (float) ($row->item_quantity ?? 0);
                        $gw   = (float) ($row->weight_total  ?? 0);
                        $nw   = $gw * 0.75;
                        $cbm  = (float) ($row->cbm_total     ?? 0);
                    @endphp
                    <tr class="item-row">
                        <td>{{ $sno++ }}</td>
                        <td class="desc-cell">{{ $desc }}</td>
                        <td>{{ $model }}</td>
                        <td>{{ $ctns ? number_format($ctns, 0) : '' }}</td>
                        <td>{{ $qty  ? number_format($qty, 0)  : '' }}</td>
                        <td>{{ $gw   ? number_format($gw, 2)   : '' }}</td>
                        <td>{{ $nw   ? number_format($nw, 2)   : '' }}</td>
                        <td>{{ $cbm  ? number_format($cbm, 3)  : '' }}</td>
                    </tr>
                @endforeach

                {{-- BLANK ROWS to maintain height (optional, like CI; adjust count if you want) --}}
                @for($i = $rowCount + 1; $i <= 10; $i++)
                    <tr class="item-row">
                        <td>&nbsp;</td>
                        <td class="desc-cell">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor

                {{-- TOTAL ROW --}}
                <tr>
                    <td colspan="3" class="text-center"><strong>TOTAL:</strong></td>
                    <td class="text-center"><strong>{{ $totalCtns ? number_format($totalCtns, 0) : '' }}</strong></td>
                    <td class="text-center"><strong>{{ $totalQty  ? number_format($totalQty, 0)  : '' }}</strong></td>
                    <td class="text-center"><strong>{{ $totalGw   ? number_format($totalGw, 2)   : '' }}</strong></td>
                    <td class="text-center"><strong>{{ $totalNw   ? number_format($totalNw, 2)   : '' }}</strong></td>
                    <td class="text-center"><strong>{{ $totalCbm  ? number_format($totalCbm, 3)  : '' }}</strong></td>
                </tr>

                {{-- BANK DETAILS + STAMPS (same structure as CI – no double border) --}}
                <tr>
                    <td colspan="8" style="padding:0;">
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
