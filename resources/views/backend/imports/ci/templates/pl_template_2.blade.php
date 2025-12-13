{{-- resources/views/.../pl_template_2.blade.php --}}
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

        .pl-wrapper {
            width: 100%;
        }

        /* Outer thick border */
        .pl-outer {
            width: 90%;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 2px;
        }

        .pl-table {
            width: 100%;
            border-collapse: collapse;
            border: none; /* table level border off – only cell borders */
        }

        .pl-table th,
        .pl-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
        }

        /* Edge pe double line na aaye – outer box hi dikhe */
        .pl-table tr th:first-child,
        .pl-table tr td:first-child {
            border-left: none;
        }
        .pl-table tr th:last-child,
        .pl-table tr td:last-child {
            border-right: none;
        }

        /* Top / header rows – no inner borders */
        .no-border-all td {
            border: none !important;
        }

        /* PACKING LIST row – only bottom line */
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

        // SAME LOGIC AS packing_list_pdf
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
        $importerAddressLine1 = trim(($company->address_1 ?? ''));
        $importerAddressLine2 = trim(($company->address_2 ?? ''));
        $importerAddressLine3 = trim(
            ($company->city ?? '') . ', ' .
            ($company->state ?? '') . ', ' .
            ($company->country ?? '') . ' ' .
            ($company->pincode ?? '')
        );

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

        $supplierStampPath = $supplier->stamp
            ? rtrim($uploadsBase, '/') . '/' . ltrim($supplier->stamp, '/')
            : null;

        $buyerStampPath = $company->buyer_stamp ?? null;
        if ($buyerStampPath && !preg_match('#^https?://#i', $buyerStampPath)) {
            $buyerStampPath = rtrim($uploadsBase, '/') . '/' . ltrim($buyerStampPath, '/');
        }

        // For fixed 10 rows
        $rowCount = $rows->count();

        $gstin = $company->gstin ?? $company->gst_no ?? '';
        $pan   = $company->pan ?? $company->pan_no ?? $company->iec_no ?? '';
    @endphp

    <div class="pl-wrapper">
        <div class="pl-outer">
            <table class="pl-table">

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

                {{-- PACKING LIST – only outer box ka effect --}}
                <tr style="border:none;">
                    <td style="border:none;" colspan="8" class="title-cell">
                        PACKING LIST
                    </td>
                </tr>

                {{-- TO / INVOICE NO + DATE – no borders at all --}}
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

                {{-- FROM / TO PORTS – no borders at all --}}
                <tr class="no-border-all">
                    <td colspan="5" class="text-left">
                        <span class="label">FROM:</span>
                        {{ $fromDisplay }}
                    </td>
                    <td colspan="3" class="text-right">
                        <span class="label">TO:</span>
                        {{ $toCity }}, {{ $toCountry }}
                    </td>
                </tr>

                {{-- HEADER ROW --}}
                <tr class="items-header-row">
                    <th style="width:6%;"  class="text-center">No</th>
                    <th style="width:34%;" class="text-center">Description of Goods</th>
                    <th style="width:12%;" class="text-center">MODEL NO.</th>
                    <th style="width:8%;"  class="text-center">CTNS</th>
                    <th style="width:10%;" class="text-center">QTY(PCS)</th>
                    <th style="width:10%;" class="text-center">G.W (KG)</th>
                    <th style="width:10%;" class="text-center">N.W (KG)</th>
                    <th style="width:10%;" class="text-center">CBM(M3)</th>
                </tr>

                {{-- DATA ITEM ROWS --}}
                @php $sno = 1; @endphp
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

                {{-- BLANK ROWS TILL 10 --}}
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

                {{-- BANK DETAILS + STAMPS --}}
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
