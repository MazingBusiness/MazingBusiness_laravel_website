<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;

class FinalPurchaseExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $purchaseNo;

    // Constructor to accept the purchase_no
    public function __construct($purchaseNo)
    {
        $this->purchaseNo = $purchaseNo;
    }

    public function collection()
    {
        $data = DB::table('final_purchase')
            ->where('final_purchase.purchase_no', $this->purchaseNo)
            ->join('final_purchase_order', 'final_purchase.purchase_order_no', '=', 'final_purchase_order.purchase_order_no')
            ->select(
                'final_purchase.purchase_no',
                'final_purchase.purchase_order_no',
                'final_purchase.seller_info',  // Fetch seller_info JSON field
                'final_purchase.seller_invoice_no',
                'final_purchase.seller_invoice_date',
                'final_purchase.product_info'
            )
            ->orderBy('final_purchase.created_at', 'desc')
            ->get();

        // Process the data to filter out products with qty == 0
        $filteredData = $data->map(function ($item) {
            $productInfo = json_decode($item->product_info, true);

            // Filter out products where qty is 0
            $filteredProductInfo = array_filter($productInfo, function ($product) {
                return $product['qty'] != 0;
            });

            // Encode the filtered product info back to JSON
            $item->product_info = json_encode(array_values($filteredProductInfo));

            return $item;
        });

        return $filteredData;
    }

    public function headings(): array
    {
        return [
            'Purchase No',
            'Purchase Order No',
            'Seller Name',
            'Seller Invoice No',
            'Seller Invoice Date',
            'Part No',
            'HSN Code',
            'Product Name',
            'Quantity',
            'Purchase Price',
            'Subtotal',
        ];
    }

    public function map($purchase): array
    {
        $sellerInfo = json_decode($purchase->seller_info, true);  // Decode the seller_info JSON data
        $sellerName = $sellerInfo['seller_name'] ?? 'Unknown';    // Extract seller_name with a default value if not found

        $productInfo = json_decode($purchase->product_info, true);  // Decode the product_info JSON data
        $mappedData = [];
        $total = 0;

        foreach ($productInfo as $product) {
            // Fetch product details including purchase_price, hsncode, and product_name from the products table using part_no
            $productDetails = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->select('purchase_price', 'hsncode', 'name as product_name')
                ->first();

            $purchasePrice = $productDetails->purchase_price ?? 0;  // Default to 0 if not found
            $hsncode = $productDetails->hsncode ?? 'N/A';
            $productName = $productDetails->product_name ?? 'N/A';
            $subtotal = $product['qty'] * $purchasePrice;
            $total += $subtotal;

            $mappedData[] = [
                $purchase->purchase_no,
                $purchase->purchase_order_no,
                $sellerName,
                $purchase->seller_invoice_no,
                $purchase->seller_invoice_date,
                $product['part_no'],
                $hsncode,
                $productName,
                $product['qty'],
                $purchasePrice,
                number_format($subtotal, 2),
            ];
        }

        // Optionally, add a total row at the end of each group
        $mappedData[] = [
            '', '', '', '', '', '', '', '', 'Total:', number_format($total, 2)
        ];

        return $mappedData;
    }

    public function styles(Worksheet $sheet)
    {
        // Make the headings bold
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        // Make the total row bold
        $rowCount = $sheet->getHighestRow(); // Get the last row number
        $sheet->getStyle("I$rowCount:K$rowCount")->getFont()->setBold(true);

        return [];
    }
}
