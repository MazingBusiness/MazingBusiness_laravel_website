<?php

namespace App\Exports;

use App\Models\Manager41Challan;
use App\Models\Manager41PurchaseInvoice;
use App\Models\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Manager41BusyExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    protected array $rows = [];
    protected array $challanIds = [];
    protected array $purchaseInvoiceIds = [];

    public function __construct()
    {
        $this->rows = $this->buildData();
    }

    // expose IDs so controller can mark exported=1
    public function getChallanIds(): array
    {
        return $this->challanIds;
    }
    public function getPurchaseInvoiceIds(): array
    {
        return $this->purchaseInvoiceIds;
    }

    public function array(): array
    {
        return $this->rows;
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
        return [ 1 => ['font' => ['bold' => true]] ];
    }

    protected function buildData(): array
    {
        $rows = [];

        // -------------------- SALES (Challans) --------------------
        $challans = Manager41Challan::query()
            ->where('busy_exported', 0)
            ->with(['challan_details.product', 'address', 'order_warehouse']) // address -> party data; order_warehouse -> series
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($challans as $ch) {
            if (!$ch->challan_details->count()) {
                continue;
            }
            $this->challanIds[] = $ch->id;

            $series  = optional($ch->order_warehouse)->name ?? '';   // VCH Series
            $vchDate = optional($ch->created_at)->format('Y/m/d');
            $vchType = 'Sales';
            $vchNo   = $ch->challan_no;

            // Party (from address() relation)
            $partyCode = optional($ch->address)->acc_code ?? '';
            $partyName = optional($ch->address)->company_name ?? '';
            $mcName    = $series;

            $firstRow = true;

            foreach ($ch->challan_details as $d) {
                // safe part_no resolution:
                $partNo = $this->resolvePartNoFromChallanDetail($d);

                $qty   = (float) ($d->quantity ?? 0);
                $rate  = (float) ($d->rate ?? 0);
                $amt   = (float) ($d->final_amount ?? ($qty * $rate));

                $rows[] = [
                    $firstRow ? $series  : '',
                    $firstRow ? $vchDate : '',
                    $firstRow ? $vchType : '',
                    $firstRow ? $vchNo   : '',
                    $firstRow ? $partyCode : '',
                    $firstRow ? $partyName : '',
                    $firstRow ? $mcName    : '',
                    (string) $partNo,
                    $qty,
                    'Pcs',
                    round($rate, 0),
                    0,
                    $amt,
                ];
                $firstRow = false;
            }
        }

        // -------------------- PURCHASES --------------------
        $purchases = Manager41PurchaseInvoice::query()
            ->where('busy_exported', 0)
            ->with(['purchaseInvoiceDetails', 'address.state', 'warehouse'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($purchases as $inv) {
            if (!$inv->purchaseInvoiceDetails->count()) {
                continue;
            }
            $this->purchaseInvoiceIds[] = $inv->id;

            $series  = optional($inv->warehouse)->name ?? '';
            $vchDate = optional($inv->created_at)->format('Y/m/d');
            $vchType = $inv->purchase_invoice_type === 'customer' ? 'Credit Note' : 'Purchase';
            $vchNo   = $inv->purchase_no;

            // Party (seller vs customer)
            $partyName = '';
            $partyCode = '';
            if ($inv->purchase_invoice_type === 'seller') {
                // seller_id must be present on manager_41_purchase_invoices
                $shop = Shop::where('seller_id', $inv->seller_id)->first();
                $partyName = $shop->name ?? 'N/A';
                $partyCode = $shop->seller_busy_code ?? '';
            } else {
                $partyName = optional($inv->address)->company_name ?? '';
                $partyCode = optional($inv->address)->acc_code ?? '';
            }
            $mcName = $series;

            $firstRow = true;

            foreach ($inv->purchaseInvoiceDetails as $d) {
                $partNo = $d->part_no; // manager_41_purchase_invoice_details.part_no (you already save it)
                $qty    = (float) $d->qty;
                $price  = (float) $d->price;
                $tax    = (float) $d->tax;

                // Busy expects tax-inclusive price in your current convention:
                $taxedPrice = $price + ($price * $tax / 100);
                $amount     = $taxedPrice * $qty;

                $rows[] = [
                    $firstRow ? $series  : '',
                    $firstRow ? $vchDate : '',
                    $firstRow ? $vchType : '',
                    $firstRow ? $vchNo   : '',
                    $firstRow ? $partyCode : '',
                    $firstRow ? $partyName : '',
                    $firstRow ? $mcName    : '',
                    ($inv->purchase_invoice_type === 'customer' && $inv->purchase_order_no === 'Service Entry')
                        ? 'Discount'
                        : (string) $partNo,
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

    /**
     * Resolve part_no from Manager41ChallanDetail robustly.
     */
    protected function resolvePartNoFromChallanDetail($detail): ?string
    {
        // 1) if detail has part_no column itself
        if (!empty($detail->part_no)) {
            return (string) $detail->part_no;
        }

        // 2) if product relation is loaded (could be model or collection)
        if ($detail->relationLoaded('product')) {
            $rel = $detail->getRelation('product');
            $prod = $rel instanceof \Illuminate\Support\Collection ? $rel->first() : $rel;
            if ($prod && !empty($prod->part_no)) {
                return (string) $prod->part_no;
            }
        }

        // 3) fallback by product_id
        if (!empty($detail->product_id)) {
            return (string) \App\Models\Product::where('id', $detail->product_id)->value('part_no');
        }

        return null;
    }
}
