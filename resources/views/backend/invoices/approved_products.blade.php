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
        .new-label {
            color: red;
            font-weight: bold;
        }
        .unavailable {
            background-color: #f9dcdc;
            color: #ff0000;
            font-weight: bold;
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
                <p><span class="highlight">Customer Name:</span> {{$userDetails->company_name}}</p>
                <p><span class="highlight">Order Code:</span> {{$order->code}}</p>
            </div>
            <div class="order-date">
                <p><span class="highlight">Order Date:</span> {{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d') }}
</p>
            </div>
        </div>

        <!-- Available Items Table -->
        <h3>Approved Items</h3>
        <table class="product-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Product Name</th>
                    <th>Part No.</th>
                    <th>Order Qty</th>
                    <th>Approved Qty</th>
                    <th>Rate</th>
                    <th>Sub Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($approvedProducts as $key => $product)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>
                        <a href="{{ route('product', ['slug' => $product->slug]) }}" target="_blank" style="text-decoration: none; color: #074e86;">
                            {{ $product->product_name }}
                        </a>
                        @if ($product->is_new)
                            <span class="new-label">(New)</span>
                        @endif
                    </td>
                    <td>{{ $product->part_no ?? '-' }}</td>
                    <td>{{ $product->order_qty ?? '0' }}</td>
                    <td>{{ $product->approved_qty }}</td>
                    <td>₹{{ number_format($product->rate, 2) }}</td>
                    <td>₹{{ number_format($product->bill_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Unavailable Items Table -->
        @if ($unavailableItems->isNotEmpty())
        <h3>Unavailable Items in stock</h3>
        <table class="product-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Product Name</th>
                    <th>Part No.</th>
                    <th>Quantity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unavailableItems as $key => $item)
                <tr class="unavailable">
                    <td>{{ $key + 1 }}</td>
                    <td>
                        <a href="{{ route('product', ['slug' => $item->slug]) }}" target="_blank" style="text-decoration: none; color: #074e86;">
                            {{ $item->product_name }}
                        </a>
                       
                    </td>
                    <td>{{ $item->part_no ?? '-' }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>Not Available</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Footer Section -->
        <div class="footer">
            ACE Tools PVT. LTD.
        </div>
    </div>
</body>
</html>
