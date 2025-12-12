<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ExternalPurchaseOrder implements ToCollection, WithHeadingRow
{
    protected $tableName;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Debugging step to confirm available keys
            if (!$row->has('orderqty')) {
                dd("Available keys in row:", $row->keys());
            }

            // Skip rows where 'order_no' or 'part_no' is null or empty
            if (empty($row['order_no']) || empty($row['part_no'])) {
                continue;
            }

            // Check if the record has 'delete_status' == 1
            $existingRecord = DB::table($this->tableName)
                ->where('order_no', $row['order_no'])
                ->where('part_no', $row['part_no'])
                ->first();

            if ($existingRecord && $existingRecord->delete_status == 1) {
                // Skip this row if delete_status == 1
                continue;
            }

            // Convert Excel serial date to Carbon date format
            $orderDate = $this->transformDate($row['order_date']);

            // Convert order_qty and closing_qty to integers, handling non-numeric values
            $orderQty = intval($row['orderqty']); // Ensure column name matches Excel
            $closingQty = intval($row['closing_qty']);

            // Calculate 'to_be_ordered' as an integer
            $toBeOrdered = $orderQty - $closingQty;

            // Use updateOrInsert to either update the existing record or insert a new one
            DB::table($this->tableName)->updateOrInsert(
                // Conditions to check for an existing record
                ['order_no' => $row['order_no'], 'part_no' => $row['part_no']],
                
                // Data to insert or update
                [
                    'branch' => $row['branch'],
                    'order_date' => $orderDate,
                    'party' => $row['party'],
                    'item' => $row['item_name'],
                    'order_qty' => $orderQty,
                    'closing_qty' => $closingQty,
                    'to_be_ordered' => $toBeOrdered,
                    'age' => intval($row['age']), // Ensure 'age' is also an integer
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Convert Excel serial date to Carbon date
     * 
     * @param mixed $excelDate
     * @return string
     */
    private function transformDate($excelDate)
    {
        if (is_numeric($excelDate)) {
            // Excel stores dates as number of days since 1900-01-01
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate))->format('Y-m-d');
        }
        
        // If it's already in a date format, return it directly
        return Carbon::parse($excelDate)->format('Y-m-d');
    }
}
