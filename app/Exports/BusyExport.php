<?php 

namespace App\Exports;

use App\Models\InvoiceOrder;
use App\Models\PurchaseInvoice;
use App\Models\DebitNoteInvoice;   // ⬅️ add
use App\Models\Shop;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BusyExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    protected $invoiceOrderIds = [];
    protected $purchaseInvoiceIds = [];
    protected $debitNoteInvoiceIds = [];   // ⬅️ add

    public function __construct()
    {
        $this->data = $this->buildData();
    }

    public function getInvoiceOrderIds()
    {
        return $this->invoiceOrderIds;
    }

    public function getPurchaseInvoiceIds()
    {
        return $this->purchaseInvoiceIds;
    }

    public function getDebitNoteInvoiceIds()        // ⬅️ add
    {
        return $this->debitNoteInvoiceIds;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'VCH Series', 'VCH Bill Date', 'VCH Type', 'VCH Bill No', 'Party Code',
            'Party Name', 'Mc Name', 'Part No', 'Qty', 'Unit', 'List Price', 'Discount', 'Amount',
        ];
    }

    public function title(): string
    {
        return 'Sheet1';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    protected function buildData(): array
    {
        $rows = [];

        // 1) Sales (InvoiceOrder)
        $invoiceOrders = InvoiceOrder::where('busy_exported', 0)
            ->with(['invoice_products', 'address.state', 'warehouse'])
            ->where('invoice_cancel_status', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($invoiceOrders as $order) {
            if (!$order->invoice_products->count()) continue;

            $this->invoiceOrderIds[] = $order->id;

            $firstRow = true;
            foreach ($order->invoice_products as $detail) {
                $rows[] = [
                    $firstRow ? ($order->warehouse->name ?? '') : '',
                    $firstRow ? optional($order->created_at)->format('Y/m/d') : '',
                    $firstRow ? 'Sales' : '',
                    $firstRow ? $order->invoice_no : '',
                    $firstRow ? ($order->address->acc_code ?? '') : '',
                    $firstRow ? ($order->address->company_name ?? '') : '',
                    $firstRow ? ($order->warehouse->name ?? '') : '',
                    $detail->part_no,
                    $detail->billed_qty,
                    'Pcs',
                    round($detail->rate, 0),
                    0,
                    $detail->billed_amt,
                ];
                $firstRow = false;
            }
        }

        // 2) Purchase (PurchaseInvoice)
        $purchaseInvoices = PurchaseInvoice::where('busy_exported', 0)
            ->with(['purchaseInvoiceDetails', 'address.state', 'warehouse'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($purchaseInvoices as $invoice) {
            if (!$invoice->purchaseInvoiceDetails->count()) continue;

            $this->purchaseInvoiceIds[] = $invoice->id;

            $firstRow = true;
            foreach ($invoice->purchaseInvoiceDetails as $detail) {
                // party
                if ($invoice->purchase_invoice_type === 'seller') {
                    $shop = Shop::where('seller_id', $invoice->seller_id)->first();
                    $partyName = $shop->name ?? 'N/A';
                    $partyCode = $shop->seller_busy_code ?? 'N/A';
                    $mcName    = $invoice->warehouse->name ?? '';
                } else {
                    $partyName = $invoice->address->company_name ?? '';
                    $partyCode = $invoice->address->acc_code ?? '';
                    $mcName    = $invoice->warehouse->name ?? '';
                }

                // tax-inclusive
                $taxedPrice = (float)$detail->price + ((float)$detail->price * (float)$detail->tax / 100);
                $amount     = $taxedPrice * (float)$detail->qty;

                $rows[] = [
                    $firstRow ? ($invoice->warehouse->name ?? '') : '',
                    $firstRow ? optional($invoice->created_at)->format('Y/m/d') : '',
                    $firstRow ? ($invoice->purchase_invoice_type === 'customer' ? 'Credit Note' : 'Purchase') : '',
                    $firstRow ? $invoice->purchase_no : '',
                    $firstRow ? $partyCode : '',
                    $firstRow ? $partyName : '',
                    $firstRow ? $mcName : '',
                    ($invoice->purchase_invoice_type === 'customer' && $invoice->purchase_order_no === 'Service Entry')
                        ? 'Discount'
                        : $detail->part_no,
                    $detail->qty,
                    'Pcs',
                    number_format($taxedPrice, 2, '.', ''),
                    0,
                    number_format($amount, 2, '.', ''),
                ];
                $firstRow = false;
            }
        }

        // 3) Debit Note (DebitNoteInvoice)  ⬅️ NEW
        $debitNotes = DebitNoteInvoice::where('busy_exported', 0)
            ->with(['debitNoteInvoiceDetails', 'address.state', 'warehouse'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($debitNotes as $dn) {
            if (!$dn->debitNoteInvoiceDetails->count()) continue;

            $this->debitNoteInvoiceIds[] = $dn->id;

            // party
            $series    = $dn->warehouse->name ?? '';
            $partyName = '';
            $partyCode = '';
            $mcName    = $series;

            if ($dn->debit_note_type === 'seller') {
                $shop      = Shop::where('seller_id', $dn->seller_id)->first();
                $partyName = $shop->name ?? ($dn->seller_info['seller_name'] ?? 'N/A');
                $partyCode = $shop->seller_busy_code ?? '';
            } else {
                $partyName = $dn->address->company_name ?? '';
                $partyCode = $dn->address->acc_code ?? '';
            }

            $firstRow = true;
            foreach ($dn->debitNoteInvoiceDetails as $detail) {
                $price      = (float)$detail->price;         // tax-exclusive
                $tax        = (float)$detail->tax;           // %
                $qty        = (float)$detail->qty;
                $taxedPrice = $price + ($price * $tax / 100);
                $amount     = $taxedPrice * $qty;

                $rows[] = [
                    $firstRow ? $series : '',
                    $firstRow ? optional($dn->created_at)->format('Y/m/d') : '',
                    $firstRow ? 'Debit Note' : '',
                    $firstRow ? $dn->debit_note_number : '',
                    $firstRow ? $partyCode : '',
                    $firstRow ? $partyName : '',
                    $firstRow ? $mcName : '',
                    $detail->part_no,
                    $qty,
                    'Pcs',
                    number_format($taxedPrice, 2, '.', ''),
                    0,
                    number_format($amount, 2, '.', ''),
                ];
                $firstRow = false;
            }
        }

        return $rows;
    }
}
