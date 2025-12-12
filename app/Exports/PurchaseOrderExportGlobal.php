<?php 

namespace App\Exports;

use App\Models\MakePurchaseOrder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchaseOrderExportGlobal implements FromArray, WithHeadings, WithTitle
{
    protected $data = [];

    public function __construct()
    {
        $orders = MakePurchaseOrder::with(['details.product', 'warehouse'])
            ->where('is_closed', 0)
            ->where('force_closed', 0)
            ->get();

        foreach ($orders as $po) {
            // Parse seller info safely
            if (is_string($po->seller_info)) {
                $decoded = json_decode($po->seller_info, true);
                $sellerInfo = is_array($decoded) ? $decoded : [];
            } elseif (is_array($po->seller_info)) {
                $sellerInfo = $po->seller_info;
            } else {
                $sellerInfo = [];
            }

            // Only include details where pending > 0
            $filteredDetails = $po->details->filter(fn($detail) => ($detail->pending ?? 0) > 0)->values();

            foreach ($filteredDetails as $index => $detail) {
                $this->data[] = [
                    'Warehouse'        => $index === 0 ? ($po->warehouse->name ?? '') : '',
                    'PO No'            => $index === 0 ? $po->purchase_order_no : '',
                    'PO Date'          => $index === 0 ? $po->date : '',
                    'Seller Name'      => $index === 0 ? ($sellerInfo['seller_name'] ?? '-') : '',
                    'Part No'          => $detail->part_no,
                    'Product Name'     => $detail->product->name ?? '',
                    'Pending Quantity' => $detail->pending ?? 0,
                ];
            }
        }
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return ['Warehouse', 'PO No', 'PO Date', 'Seller Name', 'Part No', 'Product Name', 'Pending Quantity'];
    }

    public function title(): string
    {
        return 'All Supply Orders with Pending Qty';
    }
}



?>