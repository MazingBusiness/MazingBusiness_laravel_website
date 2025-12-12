<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\DebitNoteInvoice;
use App\Models\DebitNoteInvoiceDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DebitNoteInvoiceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
        $invoices = DebitNoteInvoice::where('id', $this->id)->get();

        foreach ($invoices as $invoice) {
            $details = DebitNoteInvoiceDetail::where('debit_note_invoice_id', $invoice->id)->get();

            $sellerInfo = $invoice->seller_info;
            $sellerName = $sellerInfo['seller_name'] ?? '';
            $sellerInvoiceNo = $invoice->invoice_no;
            $sellerInvoiceDate = $invoice->invoice_date;
            $debitNoteNo = $invoice->debit_note_no;

            foreach ($details as $detail) {
                $product = Product::where('part_no', $detail->part_no)->first();

                $basePrice = $detail->price ?? 0;
                $taxPercent = $detail->tax ?? 0;

                // ✅ Final price including tax
                $purchasePrice = $basePrice + ($basePrice * $taxPercent / 100);

                $productName = $product->name ?? 'N/A';
                $hsncode = $detail->hsncode ?? 'N/A';
                $qty = $detail->qty ?? 0;
                $subtotal = $qty * $purchasePrice;

                $this->total += $subtotal;

                $this->items[] = [
                    $debitNoteNo,
                    $detail->debit_note_order_no ?? '-',
                    $sellerName,
                    $sellerInvoiceNo,
                    $sellerInvoiceDate,
                    $detail->part_no,
                    $hsncode,
                    $productName,
                    $qty,
                    number_format($purchasePrice, 2),
                    number_format($subtotal, 2),
                ];
            }
        }

        // Add total row
        $this->items[] = [
            '', '', '', '', '', '', '', '', '', 'Total:', number_format($this->total, 2)
        ];

        return collect($this->items);
    }

    public function headings(): array
    {
        return [
            'Debit Note No',
            'Debit Note Order No',
            'Seller Name',
            'Invoice No',
            'Invoice Date',
            'Part No',
            'HSN Code',
            'Product Name',
            'Quantity',
            'Price (Incl. Tax)',
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
