<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; color: #333; }
        .container { width: 100%; margin: auto; border: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px; border: 1px solid #ddd; vertical-align: top; }
        .header-table td, .footer td { border: none; }
        .header-image img { width: 100%; max-height: 80px; }
        .highlight { color: #174e84; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    {{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
<div class="container">

    
    

    <!-- Header Image -->
    <table class="header-image">
        <tr><td><img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" alt="Header" /></td></tr>
    </table>

    <table style="width:100%;">
        <tr>
            <td style="width:70%; vertical-align: middle;">
                <strong>GSTIN:</strong> {{ $branchDetails['gstin'] }}
            </td>
            <td style="width:30%; text-align: right; vertical-align: middle;">
                @if (!empty($eway['irn_no']) && $eway['irn_no'] !== '-')
                    <a href="{{ route('admin.download.ewaybill', $invoice['id']) }}"
                       target="_blank"
                       style="
                           font-size: 13px;
                           background-color: #174e84;
                           color: white;
                           padding: 4px 10px;
                           border-radius: 4px;
                           text-decoration: none;
                           display: inline-block;
                       ">
                        Download E-Way Bill
                    </a>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" class="highlight" style="text-align:center; padding-top: 10px;">
                {{ $branchDetails['company_name'] }}<br>
                {{ $branchDetails['address_1'] }}{{ $branchDetails['address_2'] ? ', ' . $branchDetails['address_2'] : '' }}{{ $branchDetails['address_3'] ? ', ' . $branchDetails['address_3'] : '' }}<br>
                {{ $branchDetails['city'] }}, {{ $branchDetails['state'] }} - {{ $branchDetails['postal_code'] }}<br>
                Tel.: {{ $branchDetails['phone'] }} | Email: {{ $branchDetails['email'] }}
            </td>
        </tr>
    </table>

    <!-- Invoice & Billing -->
    <table style="width: 100%;">
        <tr>
            <!-- Left Column -->
            <td width="30%">
                <strong>Invoice No:</strong> {{ $invoice['invoice_no'] ?? '' }}<br>
                <strong>Dated:</strong> {{ \Carbon\Carbon::parse($invoice['created_at'])->format('d-m-Y') }}<br>
                <strong>Place of Supply:</strong> {{ $shipping->state->name ?? 'N/A' }}
            </td>

            <!-- Middle Column: Billed To -->
            <td width="35%">
                <strong>Billed To:</strong><br>
                {{ $shipping->company_name ?? 'N/A' }}<br>
                {{ $shipping->address ?? '' }} {{ $shipping->address_2 ?? '' }}<br>
                {{ $shipping->city ?? '' }} - {{ $shipping->postal_code ?? '' }}<br>
                <strong>GSTIN:</strong> {{ $shipping->gstin ?? 'N/A' }}
            </td>

            <!-- Right Column: Transport Info -->
            <td width="35%">
                <strong>LR Number:</strong> {{ $logistic->lr_no ?? 'N/A' }}<br>
                <strong>LR Date:</strong> {{ $logistic->lr_date ?? 'N/A' }}<br>
                <strong>Transport:</strong> {{ $logistic->transport_name ?? 'N/A' }}<br>
                <strong>No. of Boxes:</strong> {{ $logistic->no_of_boxes ?? 'N/A' }}<br>
                <strong>Sales Person:</strong> {{ $shipping->phone ?? 'N/A' }}
            </td>
        </tr>
    </table>

    <!-- Product Listing -->
    @php 
        $total = 0;
        $totalQty = 0;
        $grossTotal = 0;
        $totalCGST = 0;
        $totalSGST = 0;
        $totalIGST = 0;
    @endphp

    <table style="width: 100%; border-collapse: collapse;" border="1">
        <thead>
            <tr>
                <th>S.N.</th>
                <th>Description of Goods</th>
                <th>HSN Code</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>GST %</th>
                <th>Price</th>
                <th>Gross Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice['invoice_products'] as $key => $item)
                @php
                    $grossTotal += $item['gross_amt'];
                    $totalQty += $item['billed_qty'];
                    $taxAmount = ($item['cgst_amt'] ?? 0) + ($item['sgst_amt'] ?? 0) + ($item['igst_amt'] ?? 0);
                    $totalCGST += ($item['cgst_amt'] ?? 0);
                    $totalSGST += ($item['sgst_amt'] ?? 0);
                    $totalIGST += ($item['igst_amt'] ?? 0);
                    $lineTotal = $item['billed_amt'];
                    $total += $lineTotal;
                @endphp
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['hsn_no'] }}</td>
                    <td>{{ $item['billed_qty'] }}</td>
                    <td>Pcs</td>
                    <td>{{ number_format($item['gst'], 2) }}%</td>
                    <td>₹{{ number_format($item['price'], 2) }}</td>
                    <td>₹{{ number_format($item['gross_amt'], 2) }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                <td><strong>{{ $totalQty }}</strong></td>
                <td>Pcs</td>
                <td></td>
                <td></td>
                <td><strong>₹{{ number_format($grossTotal, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- White space below the table -->
    <div style="height: 50px;"></div>

    <!-- Summary Breakdown Table -->
    @php
        // Final invoice-level totals from DB (authoritative)
        $grandTotal = $invoice->grand_total ?? $invoice->invoice_products->sum('billed_amt');
        $totalCGST  = $invoice->total_cgst ?? 0;
        $totalSGST  = $invoice->total_sgst ?? 0;
        $totalIGST  = $invoice->total_igst ?? 0;
        $taxAmount  = $totalCGST + $totalSGST + $totalIGST;

        // ✅ NEW: sum of convenience fee from details (column: conveince_fees)
        $convenienceFee = (float) ($invoice->invoice_products->sum('conveince_fees') ?? 0);

        // ✅ NEW: gross amount EXCLUDING convenience fee
        // (So that: Gross + Taxes + Convenience Fee + Roundoff = Grand Total)
        // $grossAmount = $grandTotal - $taxAmount - $convenienceFee;

        // roundoff to reconcile to rounded grand total
        $roundoff = round($grandTotal) - $grandTotal;
    @endphp

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        @if($invoice->rewards_discount > 0)
        <tr>
            <td style="text-align: right;">Reward Discount ({{ $invoice->rewards_from }}):</td>
            <td style="text-align: right;">₹{{ number_format($invoice->rewards_discount, 2) }}</td>
        </tr>
        @endif

        <tr>
            <td style="text-align: right;">Gross amount:</td>
            <td style="text-align: right;">₹{{ number_format($grossTotal, 2) }}</td>
        </tr>
        <tr>
            <td style="text-align: right;">Packing and Forwarding:</td>
            <td style="text-align: right;">₹{{ number_format($convenienceFee / 1.18, 2) }}</td>
        </tr>
        @if($totalCGST > 0)
        <tr>
            <td style="text-align: right;">CGST:</td>
            <td style="text-align: right;">₹{{ number_format($totalCGST, 2) }}</td>
        </tr>
        @endif

        @if($totalSGST > 0)
        <tr>
            <td style="text-align: right;">SGST:</td>
            <td style="text-align: right;">₹{{ number_format($totalSGST, 2) }}</td>
        </tr>
        @endif

        {{-- ✅ NEW: Convenience Fee row (shown above Roundoff) --}}
       
        

        @if($totalIGST > 0)
        <tr>
            <td style="text-align: right;">IGST:</td>
            <td style="text-align: right;">₹{{ number_format($totalIGST, 2) }}</td>
        </tr>
        @endif

        
       

        <tr>
            <td style="text-align: right;">Roundoff:</td>
            <td style="text-align: right;">₹{{ number_format($roundoff, 2) }}</td>
        </tr>

        <tr>
            <td style="text-align: right; font-weight: bold;">Grand Total:</td>
            <td style="text-align: right; font-weight: bold;">₹{{ number_format(round($grandTotal), 2) }}</td>
        </tr>
    </table>

    <p style="margin-top: 10px;padding-left:5px;">
        <strong>Amount Chargeable (in words):</strong>
        INR {{ ucwords(\NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format(round($grandTotal))) }} Only
    </p>

    <!-- Final Section -->
    <table style="width: 100%; border-top: 1px solid #999; margin-top: 10px;">
        <tr>
            <!-- Left Column -->
            <td style="width: 50%; vertical-align: top; padding: 8px;">
                <p style="margin: 0;">
                    <strong>Due Amount:</strong> ₹{{ number_format($shipping->due_amount ?? 0, 2) }} ({{ $shipping->dueDrOrCr ?? 'Dr' }})<br>
                    <strong>Overdue Amount:</strong> ₹{{ number_format($shipping->overdue_amount ?? 0, 2) }} ({{ $shipping->overdueDrOrCr ?? 'Dr' }})<br>
                    <strong>Contact:</strong> {{ $shipping->phone ?? '-' }}<br>
                    <strong>Terms & Conditions:</strong><br>
                    <p style="font-size:10px; line-height: 1.2;"><b>Jurisdiction:</b> All disputes are subject to the exclusive jurisdiction of the courts of Delhi only.</p>
                    <p style="font-size:10px; line-height: 1.2;"><b>Transit Liability:</b> We are not liable for any damage or loss of goods during transit. Our responsibility ceases upon dispatch. Proof of dispatch will be provided upon request.</p>
                    <p style="font-size:10px; line-height: 1.2;"><b>Returns & Exchanges:</b> Returns or exchanges are strictly subject to prior written approval and are applicable only for select, genuine issues. No return or exchange will be processed without formal consent.</p>
                    <p style="font-size:10px; line-height: 1.2;"><b>Payments:</b> All payments must be made as per the agreed terms. Delays beyond the due date may attract interest or penalty charges as per our company policy. Credit days if any will be calculated from the date of invoice only.</p>
                </p>
            </td>

            <!-- Right Column -->
            <td style="width: 50%; vertical-align: top; padding: 8px;">
                <table style="width: 100%;">
                    <tr>
                        <!-- Bank details -->
                        <td style="width: 65%; vertical-align: top;">
                            <strong>Bank Details:</strong><br>
                            Bank Name: ICICI Bank<br>
                            A/C Name: ACE TOOLS PRIVATE LIMITED<br>
                            A/c No: 235605001202<br>
                            Branch: NAJAFGARH ROAD, NEW DELHI<br>
                            IFS Code: ICIC0002356
                        </td>

                        <!-- QR Image -->
                        <td style="width: 35%; text-align: right;">
                            @php
                                $qrPath = public_path('assets/img/barcode.png');
                                $qrImage = base64_encode(file_get_contents($qrPath));
                            @endphp

                            <img src="data:image/png;base64,{{ $qrImage }}" 
                                 alt="Scan QR Code" 
                                 style="width: 90px; height: 90px;">
                        </td>
                    </tr>
                    <!-- ✅ Signature Row -->
                    <tr>
                        <td colspan="2" style="text-align: right; padding-top: 10px;">
                            <div style="margin-top: 5px;">Ace Tools Private Limited</div>
                            <div style="display: inline-block; text-align: center; margin-top: 10px;">
                                <img src="https://mazingbusiness.com/public/invoice_image/header_bg.png" 
                                     alt="Authorized Signatory" 
                                     style="max-height: 80px; display: block; margin: 0 auto;">
                                <div style="border-top: 1px solid gray; width: 200px; margin: 5px auto 0;"></div>
                                <div style="margin-top: 5px;">Authorized Signatory</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer Image Strip -->
    <div class="footer" style="text-align: center; margin-top: 20px;">
        <div class="footer-images" style="display: flex; justify-content: center; align-items: center; gap: 10px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a1.jpg" alt="Image 1" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a2.jpg" alt="Image 2" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a3.jpg" alt="Image 3" style="max-width: 100px;">
            <img src="https://mazingbusiness.com/public/invoice_image/a4.jpg" alt="Image 4" style="max-width: 100px;">
        </div>
    </div>

   
    

</div>

 {{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
</body>
</html>
