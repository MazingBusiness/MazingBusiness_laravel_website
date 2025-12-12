<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PurchaseOrderExport implements FromArray, WithHeadings, WithTitle
{
    protected $po;
    protected $sellerInfo;

    public function __construct($po)
    {
        $this->po = $po;

        // Decode seller_info JSON safely
        if (is_string($po->seller_info)) {
            $decoded = json_decode($po->seller_info, true);
            $this->sellerInfo = is_array($decoded) ? $decoded : [];
        } elseif (is_array($po->seller_info)) {
            $this->sellerInfo = $po->seller_info;
        } else {
            $this->sellerInfo = [];
        }
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->po->details as $index => $detail) {
            $rows[] = [
                'Warehouse'        => $index === 0 ? ($this->po->warehouse->name ?? '') : '',
                'PO No'            => $index === 0 ? $this->po->purchase_order_no : '',
                'PO Date'          => $index === 0 ? $this->po->date : '',
                'Seller Name'      => $index === 0 ? ($this->sellerInfo['seller_name'] ?? '-') : '',
                'Part No'          => $detail->part_no,
                'Product Name'     => $detail->product->name ?? '',
                'Pending Quantity' => $detail->pending ?? 0,
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Warehouse', 'PO No', 'PO Date', 'Seller Name', 'Part No', 'Product Name', 'Pending Quantity'];
    }

    public function title(): string
    {
        return 'Supply Orders';
    }
}

