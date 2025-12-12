<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unavailabel Products PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0; padding: 0; color: #333;
        }
        .container {
            width: 95%;
            margin: 10px auto;
            padding: 10px;
            border: 1px solid #000;
            border-radius: 5px;
        }
        h3, h4 {
            color: #174e84;
            margin: 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: center;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #174e84;
        }
        .header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .highlight {
            color: #174e84;
            font-weight: bold;
        }
        .footer {
            background: #174e84;
            color: white;
            text-align: center;
            padding: 6px;
            font-size: 10px;
            border-radius: 5px;
            margin-top: 20px;
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
        }
        .section-divider {
            border-top: 2px dashed #aaa;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Logo / Banner -->
    <div class="header-image">
        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" alt="Header Image" />
    </div>

    <!-- Top Info -->
    <div class="header">
        <div>
            <p><span class="highlight">Customer Name:</span>&nbsp;{{ $userDetails->company_name ?? '' }}</p>
            <p><span class="highlight">Order Code:</span>&nbsp;{{ $order->code }}</p>
            <p><span class="highlight">Order Date:</span>&nbsp;{{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d') }}</p>
        </div>
        
    </div>

    <!-- Sub Order Products -->
    @foreach ($groupedSubOrders as $sub)
        <h4>Warehouse: {{ $sub['warehouse_name'] }}</h4>

        <h4>Unavailable Items</h4>
        @if (count($sub['approvedProducts']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Product Name</th>
                        <th>Part No.</th>
                        <th>Approved Qty</th>
                        <th>Pre-Closed Qty</th>
                        
                       <!--  <th>Rate</th>
                        <th>Sub Total</th> -->
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sub['approvedProducts'] as $key => $product)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>
                                <a href="{{ route('product', ['slug' => $product['slug']]) }}" target="_blank" style="text-decoration: none; color: #074e86;">
                                    {{ $product['product_name'] }}
                                </a>
                                @if ($product['is_new']) <span class="new-label">(New)</span> @endif
                            </td>
                            <td>{{ $product['part_no'] ?? '-' }}</td>
                            <td>{{ $product['approved_qty'] }}</td>
                            <td>{{ $product['pre_closed'] ?? '0' }}</td>
                            
                           {{--  <td>₹{{ number_format($product['rate'], 2) }}</td>
                            <td>₹{{ number_format($product['bill_amount'], 2) }}</td>--}}
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No Unavailable products.</p>
        @endif

        @if (count($sub['unavailableItems']) > 0)
            <h4>Unavailable Items in stock</h4>
            <table>
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
                    @foreach ($sub['unavailableItems'] as $key => $item)
                        <tr class="unavailable">
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $item['product_name'] }}</td>
                            <td>{{ $item['part_no'] ?? '-' }}</td>
                            <td>{{ $item['qty'] }}</td>
                            <td>Not Available</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-divider"></div>
    @endforeach

    <div class="footer">ACE Tools PVT. LTD.</div>
</div>
</body>
</html>
