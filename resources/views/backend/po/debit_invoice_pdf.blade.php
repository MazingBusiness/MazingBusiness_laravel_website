<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->purchase_no }} - Purchase Invoice</title>
    <style>
        @page {
            margin: 10px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        .page-border {
            border: 1.5px solid #000;
            padding: 15px;
            min-height: 96vh;
            box-sizing: border-box;
            position: relative;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-image img {
            width: 100%;
            max-height: 90px;
            object-fit: contain;
        }

        .highlight {
            color: #174e84;
            font-weight: bold;
        }

        .company-header-block {
            text-align: center;
            font-size: 13px;
            margin-top: 10px;
            padding: 5px 15px;
        }

        .company-header-block .highlight {
            font-size: 12px;
        }

        .top-info-section {
            border: 1px solid #ccc;
            margin-top: 10px;
            font-size: 12px;
        }

        .top-info-section td {
            padding: 6px;
            vertical-align: top;
            border: 1px solid #ccc;
        }

        .product-table {
            font-size: 10.5px;
            margin-top: 10px;
        }

        .product-table th, .product-table td {
            border: 1px solid #ccc;
            padding: 3px 4px;
            line-height: 1.2;
        }

        .product-table th {
            background: #f1f1f1;
        }

        .bank-details {
            font-size: 11px;
            line-height: 1.5;
        }

        .qr img {
            height: 100px;
        }

        .footer {
            color: #fff;
            background: #174e84;
            text-align: center;
            padding: 10px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 20px;
        }

        .info-table td {
            padding: 5px;
            vertical-align: top;
        }

        .footer-logos {
            margin-top: 15px;
            text-align: center;
        }

        .footer-logos img {
            max-height: 50px;
            margin: 0 20px;
        }
    </style>
</head>
<body>

<div class="page-border">
    <!-- Header Image -->
    <div class="header-image">
        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" alt="Header Image">
    </div>

    <!-- Company Info -->
    <div class="company-header-block">
        <span class="highlight">GSTIN:</span> 07ABACA4198B1ZX<br>
        <strong>ACE TOOLS PVT LTD</strong><br>
        <strong>Khasra No. 58/15, Pal Colony, Village Rithala</strong><br>
        <strong>New Delhi, Delhi - 110085</strong><br>
        <strong>Tel.: 011-470323910 | Email: acetools505@gmail.com</strong>
    </div>

    <!-- Invoice & Buyer Info -->
    <table class="top-info-section">
        <tr>
            <td width="50%">
                <strong>Purchase No.</strong>: {{ $invoice->debit_note_no }}<br>
                <strong>Invoice No.</strong>: {{ $invoice->seller_invoice_no }}<br>
                <strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->seller_invoice_date)->format('d/m/Y') }}<br>
                <strong>Debit Order No.:</strong> {{ $invoice->debit_note_number }}
            </td>
            <td width="50%">
                <span class="highlight">From:</span><br>
                {{ $sellerInfo['seller_name'] ?? '' }}<br>
                {{ $sellerInfo['seller_address'] ?? '' }}<br>
                Phone: {{ $sellerInfo['seller_phone'] ?? '' }}<br>
                GSTIN: {{ $sellerInfo['seller_gstin'] ?? '-' }}
            </td>
        </tr>
    </table>

    <!-- Product Table -->
    <table class="product-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Order No.</th>
                <th>PO No.</th>
                <th>Part No</th>
                <th>Product Name</th>
                <th>HSN</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($productInfo as $index => $item)
                @php $total += $item->subtotal; @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->order_no ?? '-' }}</td>
                    <td>{{ $item->debit_note_order_no ?? '-' }}</td>
                    <td>{{ $item->part_no }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->hsncode ?? '-' }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>₹{{ number_format($item->rate, 2) }}</td>
                    <td>₹{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="8" style="text-align: right; font-weight: bold;">Total</td>
                <td style="font-weight: bold;">₹{{ number_format($total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Bank Details -->
    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-top: 20px;">
        <tr>
            <td style="width: 50%; border: 1px solid #ccc; font-size: 12px; line-height: 1.5; padding: 8px;">
                <strong>Bank Details:</strong><br>
                A/C Name: ACE TOOLS PRIVATE LIMITED<br>
                A/C No: 235605001202<br>
                IFSC Code: ICIC0002356<br>
                Bank Name: ICICI Bank<br>
                Branch: NAJAFGARH ROAD, NEW DELHI
            </td>
            <td style="width: 50%; border: 1px solid #ccc; text-align: right; padding: 8px;">
                <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="QR Code" style="width: 100px; height: 100px;"><br>
                <small>Scan to pay</small>
            </td>
        </tr>
    </table>


    <!-- Footer -->
    <div class="footer">
        ACE TOOLS PVT LTD - PURCHASE INVOICE
    </div>

    <!-- Footer Logos -->
    <div style="text-align: center; margin-top: 20px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a1.jpg" alt="OPEL Logo" style="height: 45px; margin: 0 10px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a2.jpg" alt="MAZING Logo" style="height: 45px; margin: 0 10px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a3.jpg" alt="CREST Logo" style="height: 45px; margin: 0 10px;">
        <img src="https://mazingbusiness.com/public/invoice_image/a4.jpg" alt="OPEL SELECT Logo" style="height: 45px; margin: 0 10px;">
    </div>

  </div>

</body>
</html>
