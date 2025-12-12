<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatementExport implements FromArray, WithHeadings, WithStyles
{
    protected $data;
    protected $headings;

    /**
     * @param array $data     Array of associative rows (already prepared)
     * @param array $headings Array of headings (dynamic last column if any)
     */
    public function __construct(array $data, array $headings)
    {
        $this->data     = $data;
        $this->headings = $headings;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [
            1 => ['font' => ['bold' => true]], // header row bold
        ];

        // Bold the TOTAL row (last data row)
        $totalRowIndex = count($this->data) + 1; // +1 for the header row
        if ($totalRowIndex > 1) {
            $styles[$totalRowIndex] = [
                'font' => ['bold' => true],
            ];
        }

        return $styles;
    }
}
