<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RewardsExport implements FromArray, WithHeadings, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        // Return the processed data directly, as it already has the necessary fields
        return $this->data;
    }

    public function headings(): array
    {
        return [            
            'Party Code',
            'Company Name',
            'Invoice No',
            'Rewards From',
            'Rewards',
        ];
    }

    // Add styles for the header row
    public function styles(Worksheet $sheet)
    {
        $styles = [
            1 => ['font' => ['bold' => true]], // Apply bold style to the first row (header)
        ];

        // Calculate the total row index
        $totalRowIndex = count($this->data) + 1; // Total row is after all data rows

        // Apply bold style to the Due Amount and Overdue Amount cells in the total row
        $styles[$totalRowIndex] = [
            'font' => [
                'bold' => true,
            ],
        ];

        return $styles;
    }
}
