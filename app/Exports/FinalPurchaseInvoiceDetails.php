<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinalPurchaseInvoiceDetails implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $id;
    protected $items = [];
    protected $total = 0;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function collection()
    {
        $invoices = PurchaseInvoice::where('id', $this->id)->get();

        foreach ($invoices as $invoice) {
            $details = PurchaseInvoiceDetail::where('purchase_invoice_no', $invoice->purchase_no)->get();

            $sellerInfo = $invoice->seller_info; // Already cast as array in model
            $sellerName = $sellerInfo['seller_name'] ?? '';
            $sellerInvoiceNo = $invoice->seller_invoice_no;
            $sellerInvoiceDate = $invoice->seller_invoice_date;
            $purchaseNo = $invoice->purchase_no;

            foreach ($details as $detail) {
                $product = Product::where('part_no', $detail->part_no)->first();

                $purchasePrice = $product->purchase_price ?? 0;
                $productName = $product->name ?? 'N/A';
                $hsncode = $product->hsncode ?? 'N/A';
                $qty = $detail->qty ?? 0;
                $subtotal = $qty * $purchasePrice;

                $this->total += $subtotal;

                $this->items[] = [
                    $purchaseNo,
                    $detail->purchase_order_no,
                    $sellerName,
                    $sellerInvoiceNo,
                    $sellerInvoiceDate,
                    $detail->part_no,
                    $hsncode,
                    $productName,
                    $qty,
                    $purchasePrice,
                    number_format($subtotal, 2),
                ];
            }
        }

        // Add Total row
        $this->items[] = [
            '', '', '', '', '', '', '', '', '', 'Total:', number_format($this->total, 2)
        ];

        return collect($this->items);
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
            'Subtotal'
        ];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        $rowCount = count($this->items) + 1; // +1 for headings

        // Bold heading row
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        // Bold total row
        $sheet->getStyle("J$rowCount:K$rowCount")->getFont()->setBold(true);

        return [];
    }
}
