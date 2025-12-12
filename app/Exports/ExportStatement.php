<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportStatement implements FromArray, WithHeadings, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Party Name',
            'Party Code',
            'Phone',
            'Manager',
            'Warehouse',
            'City',
            'Due Amount',
            'Overdue Amount',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [
            1 => ['font' => ['bold' => true]], // Bold headers
        ];

        // Get the last row index (Total row)
        $totalRowIndex = count($this->data);

        if ($totalRowIndex > 1) {
            $styles[$totalRowIndex] = [
                'font' => [
                    'bold' => true,
                ],
            ];
        }

        return $styles;
    }
}
