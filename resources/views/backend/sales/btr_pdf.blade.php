<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BTR PDF</title>
    <style>
        /* same CSS as you provided earlier */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 12px;
        }
        .container {
            width: 95%;
            margin: 10px auto;
            border: 1px solid #000;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }
        .header-image img {
            max-width: 100%;
            height: auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-top: 10px;
        }
        .customer-info p, .order-date p {
            margin: 0;
            line-height: 1.4;
        }
        .highlight {
            color: #174e84;
        }
        h4 {
            margin-top: 16px;
            font-size: 14px;
            color: #174e84;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 6px;
            text-align: center;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            color: #174e84;
        }
        .footer {
            background-color: #174e84;
            color: white;
            text-align: center;
            padding: 6px;
            font-size: 10px;
            margin-top: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-image">
            <img src="{{ base_path('public/assets/img/pdfHeader.png') }}" alt="Header Image" />
        </div>

        <div class="header">
            <div class="customer-info">
                <p><span class="highlight">Party Name:</span> {{ $invoice->shipping_address->company_name ?? 'N/A' }}</p>
                <p><span class="highlight">Invoice No:</span> {{ $invoice->invoice_no }}</p>
                <p><span class="highlight">Warehouse:</span> {{ $invoice->warehouse->name ?? 'N/A' }}</p>
            </div>
            <div class="order-date">
                <p><span class="highlight">Date:</span> {{ \Carbon\Carbon::parse($invoice->created_at)->format('d-m-Y') }}</p>
            </div>
        </div>

        <h4>BTR Details</h4>
        <table>
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Product Name</th>
                    <th>Part No</th>
                    <th>Qty</th>
                    <!-- <th>Rate</th> -->
                    <!-- <th>GST</th> -->
                    <!-- <th>Total</th> -->
                    <th>Customer Name</th>
                    <th>Order No</th>
                    <!-- <th>Challan No</th> -->
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->invoice_products as $key => $prod)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $prod->item_name }}</td>
                        <td>{{ $prod->part_no }}</td>
                        <td>{{ $prod->billed_qty }}</td>
                        <!-- <td>₹{{ number_format($prod->rate, 2) }}</td> -->
                        <!-- <td>{{ $prod->gst }}%</td> -->
                        <!-- <td>₹{{ number_format($prod->billed_amt, 2) }}</td> -->
                        <td>{{ $prod->to_company_name ?? 'N/A' }}</td>
                        <td>{{ $prod->sale_order_no ?? 'N/A' }}</td>
                        <!-- <td>{{ $prod->challan_no ?? 'N/A' }}</td> -->
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">ACE Tools PVT. LTD.</div>
    </div>
</body>
</html>
