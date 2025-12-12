<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 12px;
        }
        .container {
            width: 100%;
            margin: auto;
            border: 1px solid #000;
        }
        h2 {
            margin: 5px 0;
            font-size: 18px;
            color: #174e84;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 0px;
        }
        th, td {
            padding: 5px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .header-table td {
            padding: 3px;
        }
        .header {
            font-size: 14px;
            font-weight: bold;
        }
        .highlight {
            color: #174e84;
            font-weight: bold;
        }
        .footer {
           
            color: white;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            margin-top: 15px;
        }
        .product-table {
            margin-top: 15px;
            border: 1px solid #ddd;
        }
        .product-table th, .product-table td {
            border: 1px solid #ddd;
        }
        .compact td {
            padding: 3px;
        }
        .invoice-details td {
            border: none;
        }
        .header-image img {
            width: 100%;
            max-height: 80px;
            object-fit: contain;
        }
    </style>
</head>
<body>
     {{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
    <div class="container">
        <!-- Header Section with Image -->
        <table class="header-image" style="border: none; margin-bottom: 5px;">
            <tr>
                <td style="border: none; text-align: center; padding: 0;">
                    <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" alt="Header Image" />
                </td>
            </tr>
        </table>

        <!-- Business Info Section -->
        <table class="header-table compact">
            <tr style="border-bottom: none;border-top: none;">
                <td style="width: 100%;" style="border-bottom: none;border-top: none;">
                    <span class="highlight">GSTIN:</span> {{ $branchDetails['gstin'] }}
                </td>
            </tr>
            <tr>
                <td colspan="1" class="header" style="text-align: center;">
                    {{ $branchDetails['company_name'] }}<br>
                    {{ $branchDetails['address_1'] }}{{ $branchDetails['address_2'] ? ', ' . $branchDetails['address_2'] : '' }}{{ $branchDetails['address_3'] ? ', ' . $branchDetails['address_3'] : '' }}<br>
                    {{ $branchDetails['city'] }}, {{ $branchDetails['state'] }} - {{ $branchDetails['postal_code'] }}<br>
                    Tel.: {{ $branchDetails['phone'] }} | Email: {{ $branchDetails['email'] }}
                </td>
            </tr>
        </table>

        <!-- Invoice Details Section -->
        <table class="header-table invoice-details">
            <tr>
                <td style="width: 30%; vertical-align: top; border-right: 1px solid grey;">
                    <table style="width: 100%;">
                        <tr><td>Invoice No.</td><td>: {{ decrypt($invoice_no) }}</td></tr>
                        <tr><td>Dated</td><td>: {{ \Carbon\Carbon::parse($invoiceDate)->format('d-m-Y') }}</td></tr>
                        <tr><td>Place of Supply</td><td>: {{ $placeOfSupply }}</td></tr>
                        <!-- <tr><td>Reverse Charge</td><td>: N</td></tr> -->
                    </table>
                </td>
                <td style="width: 20%; vertical-align: top; border-right: 1px solid grey;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 50%; vertical-align: top;">
                                <h4 class="highlight" style="margin-bottom: 5px;">Billed To:</h4>
                                {{ ucwords(strtolower($billingDetails->company_name)) }}<br>
                                {{ ucwords(strtolower($billingDetails->address)) }}<br>
                                @if($billingDetails->address_2)
                                    {{ ucwords(strtolower($billingDetails->address_2)) }}<br>
                                @endif
                                {{ ucwords(strtolower($billingDetails->city)) }} - {{ $billingDetails->postal_code }}<br>
                                <span class="highlight">GSTIN / UIN:</span> {{ $billingDetails->gstin }}
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr><td>LR Date.</td><td>: {{$logisticsDetails->lr_date}}</td></tr>
                        <tr><td>Trans. name / Lr No.</td><td>: <a style="text-decoration: none;color:#074e86;" target="_blank" href="{{$logisticsDetails->attachment}}">{{$logisticsDetails->lr_no}}</a></td></tr>
                        <tr><td>No. of boxes</td><td>: {{$logisticsDetails->no_of_boxes}}</td></tr>
                        <tr><td>Sale Person</td><td>: {{$manager_phone}}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Product Table -->
        <table style="margin-top:0px;" class="product-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Description of Goods</th>
                    <th>HSN Code</th>
                    <th>Qty.</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Gross Amount</th>
                    <th>Tax Amount</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalQty = 0;
                    $totalGross = 0;
                    $totalTax = 0;
                    $grandTotal = 0;
                @endphp
                @foreach($billsData as $index => $bill)
                @php
                    $grossAmount = $bill->rate * $bill->billed_qty;
                    $taxAmount = $grossAmount * 0.18; // Assuming 18% tax
                    $totalAmount = $grossAmount + $taxAmount;
                    $totalQty += $bill->billed_qty;
                    $totalGross += $grossAmount;
                    $totalTax += $taxAmount;
                    $grandTotal += $totalAmount;

                    $product = DB::table('products')
                        ->where('id', $bill->product_id)
                        ->select('slug')
                        ->first();
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><a style="text-decoration: none; color:#074e86;" href="{{ route('product', ['slug' => $product->slug]) }}">{{ $bill->item_name }}</a></td>
                    <td>{{ $bill->hsn }}</td>
                    <td>{{ $bill->billed_qty }}</td>
                    <td>Pcs.</td>
                    <td>₹{{ number_format($bill->rate, 2) }}</td>
                    <td>₹{{ number_format($grossAmount, 2) }}</td>
                    <td>₹{{ number_format($taxAmount, 2) }}</td>
                    <td>₹{{ number_format($totalAmount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: bold;">Subtotal:</td>
                    <td style="font-weight: bold;">{{ $totalQty }}</td>
                    <td>Pcs</td>
                    <td></td>
                    <td style="font-weight: bold;">₹{{ number_format($totalGross, 2) }}</td>
                    <td style="font-weight: bold;">₹{{ number_format($totalTax, 2) }}</td>
                    <td style="font-weight: bold;">₹{{ number_format($grandTotal, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="9" style="height: 100px; border: none;"></td>
                </tr>
            </tfoot>
        </table>


        <!-- Subtotal Section -->
        <table class="subtotal-section">
            <tr>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">Gross amount:</td>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">₹{{ number_format($totalGross, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">Tax amount:</td>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">₹{{ number_format($totalTax, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">Roundoff:</td>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;">{{ round($totalInvoiceAmount - $grandTotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;"><strong>Grand Total:</strong></td>
                <td colspan="2" style="border: 1px solid #ddd; text-align: right;"><strong>₹{{ number_format($totalInvoiceAmount) }}</strong></td>
            </tr>
            <tr>
                <td colspan="3" style="border: 1px solid #ddd; padding: 5px; text-align: left;">
                    <strong>Amount Chargeable (in words):</strong> INR {{ ucwords(\NumberFormatter::create('en_IN', \NumberFormatter::SPELLOUT)->format($totalInvoiceAmount)) }} Only
                </td>
            </tr>
        </table>

        <!-- Red Marked Section -->
        <div class="red-section">
            <table style="width: 100%; border-collapse: collapse; border-spacing: 0; border: none;border-top: 1px solid grey;" >
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 10px; ">
                        <strong>Due Amount:</strong> 
                        @if($billingDetails->due_amount >= 0)
                            ₹{{$billingDetails->due_amount}} ({{$billingDetails->dueDrOrCr}})
                        @endif
                        <br>
                        <strong>Overdue Amount:</strong> ₹{{$billingDetails->overdue_amount}} ({{$billingDetails->overdueDrOrCr}})<br>
                        <strong>Contact:</strong> +91-6287859750<br>
                        <strong>Declaration:</strong> We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.
                    </td>
                    <td style="width: 50%; vertical-align: top; padding-left: 10px; ">
                        <table style="width: 100%; border-collapse: collapse; border-spacing: 0; border: none;">
                            <tr>
                                <td style="width: 70%; vertical-align: top; border: none;">
                                    <strong>Bank Details:</strong><br>
                                    Bank Name: ICICI Bank<br>
                                    A/C Name: ACE TOOLS PRIVATE LIMITED<br>
                                    A/c No: 235605001202<br>
                                    Branch: NAJAFGARH ROAD, NEW DELHI<br>
                                    IFS Code: ICIC0002356<br>
                                </td>
                                <td style="width: 30%; text-align: right; vertical-align: top; border: none;">
                                    <img src="https://mazingbusiness.com/public/assets/img/barcode.png" 
                                         alt="Scan QR Code" 
                                         style="width: 100px; height: 100px; border: none; padding: 0; margin: 0;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <div style="margin-left: 5px;" class="important-note">
                <strong>Important Note:</strong><br>
                • Please make payment directly to our above-stated bank account. Avoid cash payments to individuals.<br>
                • Goods return should be directly sent to our head office.
            </div>
        </div>

      
    </div>
      <!-- Footer -->
        <!-- Footer Section -->
<div class="footer" style="text-align: center; margin-top: 10px;">
  
    <div class="footer-images" style="display: flex; justify-content: center; align-items: center; margin-top: 5px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a1.jpg" alt="Image 1" style="max-width: 100px; margin-right: 10px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a2.jpg" alt="Image 2" style="max-width: 100px; margin-right: 10px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a3.jpg" alt="Image 3" style="max-width: 100px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a4.jpg" alt="Image 3" style="max-width: 100px;">
    </div>
</div>
 {{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
</body>
</html>
