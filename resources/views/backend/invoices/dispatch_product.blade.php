<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Data</title>
    <style>
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
        h3 {
            margin: 8px 0;
            font-size: 16px;
            color: #174e84;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            padding: 6px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #174e84;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .customer-info p, .order-date p {
            margin: 0;
            line-height: 1.4;
        }
        .highlight {
            color: #174e84;
            font-weight: bold;
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
        .product-table th, .product-table td {
            text-align: center;
        }
        .header-image img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header-image">
            <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" alt="Header Image" />
        </div>

        <div class="header">
            <div class="customer-info">
                <p><span class="highlight">Customer Name:</span> {{ $userDetails->company_name }}</p>
                <p><span class="highlight">Order Code:</span> {{ $order->code }}</p>
            </div>
            <div class="order-date">
                <p><span class="highlight">Order Date:</span> {{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d') }}</p>
            </div>
        </div>

        <!-- Dispatch Data Table -->
        <h3>Dispatched Items</h3>
        <table class="product-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Part No.</th>
                    <th>Item Name</th>
                   
                    <th>Billed Qty</th>
                    <th>Rate</th>
                    <th>Sub Total</th>
                </tr>
            </thead>
            <tbody>
               @foreach ($dispatchData as $key => $data)
    <tr>
        <td>{{ $key + 1 }}</td>
        <td>{{ $data['part_no'] }}</td>
        <td>
            @if ($data['slug'])
                <a href="{{ route('product', ['slug' => $data['slug']]) }}" target="_blank" style="text-decoration: none; color: #074e86;">
                    {{ $data['item_name'] }}
                </a>
            @else
                {{ $data['item_name'] }}
            @endif
        </td>
        <td>{{ (int)$data['billed_qty'] }}</td>
        <td>₹{{ number_format($data['rate'], 2) }}</td>
        <td>₹{{ number_format($data['bill_amount'], 2) }}</td>
    </tr>
@endforeach
            </tbody>
        </table>

        <!-- Footer Section -->
        <div class="footer">
            ACE Tools PVT. LTD.
        </div>
    </div>
</body>
</html>
