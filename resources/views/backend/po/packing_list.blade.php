<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('Packing List') }}</title>
    <style media="all">
        @page {
            margin: 0;
            padding: 0;
        }

        body {
            font-size: 0.875rem;
            font-family: '{{ $font_family }}';
            direction: {{ $direction }};
            text-align: {{ $text_align }};
            padding: 0;
            margin: 0;
        }

        .table-container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .table-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #074E86;
            position: relative;
        }

        table tbody tr {
            border: 1px solid grey;
        }

        .table-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .table-info div {
            font-size: 1rem;
            color: #074E86;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
        }

        table th,
        table td {
            padding: 15px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        table th {
            background-color: #074E86;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table tbody tr:nth-of-type(even) {
            background-color: #f2f2f2;
        }

        table tbody tr:hover {
            background-color: #e2e6ea;
        }

        table input[type="text"] {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 150px;
            box-sizing: border-box;
            font-size: 0.875rem;
            color: #495057;
        }

        .bank-details-section {
            background-color: #f2f2f2; /* Light grey background */
            padding: 2px; /* Reduced padding */
            border: 1px solid #eceff4; /* Light border */
            border-radius: 5px;
        }

        .bank-details-section td {
            vertical-align: top;
        }

        .bank-details-section strong {
            font-size: 1rem;
            color: #333;
        }

        .bank-details-section span {
            display: block;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="table-container">
        <div class="table-title">
            {{ translate('Packing List') }}
        </div>

        <div class="table-info">
            <div><strong>{{ translate('Purchase Order No:') }}</strong> {{$order->purchase_order_no}} </div>
            <div><strong>{{ translate('Date:') }}</strong> {{$order->date}}</div>
            <div><strong>{{ translate('Seller:') }}</strong> {{$seller_name}}</div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="8%">{{ translate('Age') }}</th>
                    <th width="10%">{{ translate('Order No.') }} {{ translate('(Order Date)') }}</th>
                    <th width="10%">{{ translate('Part No.') }}</th>
                    <th width="10%">{{ translate('Image') }}</th>
                    <th width="20%">{{ translate('Product Name') }}</th>
                    <th width="10%">{{ translate('Qty') }}</th>
                    <th width="10%">{{ translate('Dispatch Qty') }}</th>
                </tr>
            </thead>
            <tbody>
    @foreach($productInfo as $key => $item)
        @php
            // Fetch the product details using the part_no
            $product = \DB::table('products')->where('part_no', $item->part_no)->first();
        @endphp
        <tr>
            <td>{{ $key + 1 }}</td>
            <td>{{ $item->age }}</td>
            <td>{{ $item->order_no }}</td>
            <td>{{ $item->part_no }}</td>
            <td>
                <img style="width:50px;height:50px;" class="img-fit lazyload mx-auto" 
                     src="{{ uploaded_asset(optional($product)->thumbnail_img) }}" 
                     alt="Product Image"
                     onerror="this.onerror=null;this.src='{{ static_asset("assets/img/placeholder.jpg") }}';">
            </td>
            <td>{{ $item->product_name ?? 'Unknown' }}</td>
            <td>{{ $item->qty }}</td>
            <td>
                <input type="text" name="dispatch_qty[{{ $item->part_no }}]" value="{{ old('dispatch_qty.'.$item->part_no, 0) }}" />
            </td>
        </tr>
    @endforeach
</tbody>
        </table>
    </div>

    <div style="padding: 0 0.8rem;">
    <table class="bank-details-section" style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="width: 50%; padding: 0; vertical-align: top;"> <!-- Removed padding -->
                <div style="padding: 10px;">
                    <strong>{{ translate('Invoice No:') }}</strong> ______________________________________________<br><br>
                    <strong>{{ translate('Invoice Date:') }}</strong> ______________________________________________<br><br>
                    <strong>{{ translate('Company Billing:') }}</strong> ___________________________________________
                </div>
            </td>
            <td style="width: 50%; text-align: right; padding: 0; vertical-align: top;"> <!-- Removed padding -->
                <div style="padding: 10px;">
                    _______________________________________________<br><br>
                    <strong>{{ translate('Verified By:') }}</strong>
                </div>
            </td>
        </tr>
    </table>
</div>


</body>
</html>
