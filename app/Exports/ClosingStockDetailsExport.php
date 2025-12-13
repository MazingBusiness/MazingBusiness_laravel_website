<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClosingStockDetailsExport implements FromArray, WithStyles
{
    protected $data;
    protected $partNumber;
    protected $itemName;

    public function __construct(array $data, $partNumber, $itemName)
    {
        $this->data = $data;
        $this->partNumber = $partNumber;
        $this->itemName = $itemName;
    }

    public function array(): array
    {
        // Define header row
        $headers = [
            'Date',
            'Voucher Type',
            'Voucher Number',
            'Party Name',
            'DrQty',
            'CrQty',
            'RunningQty'
        ];
    
        // Start with top info and headers
        $rows = [
            ['Part Number:', $this->partNumber],
            ['Item Name:', $this->itemName],
            [], // empty spacer row
            $headers
        ];
    
        // Merge your actual data rows
        return array_merge($rows, $this->data);
    }

    public function styles(Worksheet $sheet)
    {
        // Adjusting row numbers due to extra rows
        return [
            1 => ['font' => ['bold' => true]], // Part Number
            2 => ['font' => ['bold' => true]], // Item Name
            3 => ['font' => ['bold' => true]], // Header row
            count($this->data) + 4 => ['font' => ['bold' => true]], // Closing row
        ];
    }
}

