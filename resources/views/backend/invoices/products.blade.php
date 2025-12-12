<!DOCTYPE html>
<html>
<head>
    <title>Product List</title>
    <style>
        /* General PDF Styling */
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h2 {
            margin: 0;
            font-size: 20px;
        }

        .header .subtitle {
            font-size: 14px;
            color: #555;
        }

        .company-info {
            margin-bottom: 20px;
        }
        
        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            position: fixed;
            left: 0;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 12px;
            color: #555;
        }

        /* Styling for product images */
        .product-thumbnail {
            width: 50px;
            height: auto;
        }

        .product-name {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <h2>Product List</h2>
        <p class="subtitle">Generated on {{ date('d/m/Y') }}</p>
    </div>

    <!-- Optional company information (if needed) -->
    <div class="company-info">
        <p><strong>Ace Tools Pvt Ltd</strong></p>
        <p>Address: 123, Industrial Zone, Mumbai</p>
        <p>Email: info@ace-tools.com | Phone: +91 1234567890</p>
    </div>

    <!-- Products Table -->
    <table>
        <thead>
            <tr>
                <th>Part No</th>
                <th>Product Name</th>
                <th>Category Group</th>
                <th>Category</th>
                <th>Min Qty</th>
                <th>MRP</th>
                <th>Image</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $product)
            <tr>
                <td>{{ $product->part_no }}</td>
                <td class="product-name">{{ $product->name }}</td>
                <td>{{ $product->group_name }}</td>
                <td>{{ $product->category_name }}</td>
                <td>{{ $product->min_qty }}</td>
                <td>₹{{ number_format($product->mrp, 2) }}</td>
                <td>
                    @if($product->thumbnail_img)
                        <img src="{{ public_path('storage/'.$product->thumbnail_img) }}" class="product-thumbnail">
                    @else
                        N/A
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Footer Section -->
    <div class="footer">
        Page <span class="pagenum"></span>
        <br>
        <span>Ace Tools Pvt Ltd | www.mazingbusiness.com</span>
    </div>

    <script type="text/php">
        if ( isset($pdf) ) { 
            $pdf->page_script('
                if ($PAGE_COUNT > 1) {
                    $font = $fontMetrics->get_font("Arial, Helvetica, sans-serif", "normal");
                    $size = 12;
                    $pageText = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
                    $y = 15;
                    $x = 520;
                    $pdf->text($x, $y, $pageText, $font, $size);
                } 
            ');
        }
    </script>

</body>
</html>
