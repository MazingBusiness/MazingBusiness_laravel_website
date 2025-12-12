<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('Purchase Order') }}</title>
    <style>
        @page { margin: 0; padding: 0; }
        body {
            font-size: 0.875rem;
            font-family: {{ $font_family }};
            direction: {{ $direction }};
            text-align: {{ $text_align }};
            margin: 0;
            padding: 0;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; }

        .header, .footer { background: #074E86; color: white; padding: 1rem; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .border-bottom th, .border-bottom td {
            border-bottom: 1px solid #eceff4;
        }
        .content-section { padding: 1rem; }
        .white-box { background: #eceff4; padding: 1rem; }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .qr-code {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            display: block;
        }

        .rounded-box {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <table>
            <tr>
                <td style="width: 50%;">
                    <h2 style="margin: 0;">ACE TOOLS PRIVATE LIMITED</h2>
                    <small>
                        PLOT NO. 220/219 KH NO. 85/2, RITHALA ROAD, <br>
                        New Delhi – 110085 <br>
                        GSTIN: 07ABACA4198B1ZX
                    </small>
                </td>
                <td class="text-center" style="width: 50%;">
                    <img src="{{ $logo }}" height="30" style="margin-bottom: 10px;"><br>
                    <img src="https://admin.mazingbusiness.com/assets/pdf/mazing_business_qr.jpg" alt="QR" class="qr-code">
                </td>
            </tr>
        </table>
    </div>

    <!-- TITLE -->
    <div class="text-center" style="font-size: 1.5rem; padding: 10px; font-weight: bold;">
        {{ translate('Purchase Order') }}
    </div>

    <!-- SELLER + ORDER DETAILS -->
    <div class="content-section">
        <table>
            <tr>
                <td style="width: 60%;">
                    <strong>{{ translate('Seller Info:') }}</strong><br><br>
                    {{ $sellerInfo['seller_name'] }}<br>
                    {{ $sellerInfo['seller_address'] }}<br>
                    {{ translate('Phone') }}: {{ $sellerInfo['seller_phone'] }}<br>
                    {{ translate('GSTIN') }}: {{ $sellerInfo['seller_gstin'] }}
                </td>
                <td class="white-box text-left" style="width: 40%;">
                    <table>
                        <tr>
                            <td><strong>{{ translate('Purchase Order No.') }}</strong></td>
                            <td>{{ $order->purchase_order_no }}</td>
                        </tr>
                        <tr>
                            <td>{{ translate('Order Date') }}</td>
                            <td>{{ date('d/m/Y', strtotime($order->date)) }}</td>
                        </tr>
                        <tr>
                            <td>{{ translate('Amount') }}</td>
                            <td>₹ {{ number_format(collect($productInfo)->sum('subtotal'), 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- PRODUCT TABLE -->
    <div class="content-section">
        <table class="border-bottom">
            <thead style="background: #eceff4;">
                <tr>
                    <th class="text-left">{{ translate('No.') }}</th>
                    <th class="text-left">{{ translate('Part No.') }}</th>
                    <th class="text-left">{{ translate('Product Name') }}</th>
                    <th class="text-left">{{ translate('Image') }}</th>
                    <th class="text-right">{{ translate('Qty') }}</th>
                    <th class="text-right">{{ translate('Subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($productInfo as $index => $item)
                    @php
                        $product = \App\Models\Product::where('part_no', $item['part_no'])->first();
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['part_no'] }}</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td>
                            @if($product && $product->thumbnail_img)
                                <img src="{{ uploaded_asset($product->thumbnail_img) }}" class="product-image" alt="Image">
                            @else
                                <img src="{{ static_asset('assets/img/placeholder.jpg') }}" class="product-image" alt="Image">
                            @endif
                        </td>
                        <td class="text-right">{{ $item['qty'] }}</td>
                        <td class="text-right">{{ number_format($item['subtotal'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- TOTAL -->
    <div class="content-section">
        <table>
            <tr>
                <td style="width: 70%;"></td>
                <td style="width: 30%;">
                    <table>
                        <tr class="border-bottom">
                            <th class="text-left">{{ translate('Grand Total') }}</th>
                            <td class="text-right">
                                ₹ {{ number_format(collect($productInfo)->sum('subtotal'), 2) }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
