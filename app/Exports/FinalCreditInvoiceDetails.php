<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Models\Address;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinalCreditInvoiceDetails implements FromCollection, WithHeadings, WithMapping, WithStyles
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

            // Get customer name from addresses table
            $customer = Address::find($invoice->addresses_id);
            $customerName = $customer->company_name ?? 'Unknown';

            $sellerInvoiceNo = $invoice->seller_invoice_no;
            $sellerInvoiceDate = $invoice->seller_invoice_date;
            $purchaseNo = $invoice->purchase_no;

            foreach ($details as $detail) {
                $product = Product::where('part_no', $detail->part_no)->first();

                $baseRate = $detail->price ?? 0;
                $rateWithTax = round($baseRate * 1.18, 2);
                $qty = $detail->qty ?? 0;
                $subtotal = round($qty * $rateWithTax, 2);

                $this->total += $subtotal;

                $this->items[] = [
                    $purchaseNo,
                    $detail->purchase_order_no,
                    $customerName,
                    $sellerInvoiceNo,
                    $sellerInvoiceDate,
                    $detail->part_no,
                    $product->hsncode ?? 'N/A',
                    $product->name ?? 'N/A',
                    $qty,
                    $rateWithTax,
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
            'Customer Name',
            'Seller Invoice No',
            'Seller Invoice Date',
            'Part No',
            'HSN Code',
            'Product Name',
            'Quantity',
            'Rate (Incl. 18% Tax)',
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
