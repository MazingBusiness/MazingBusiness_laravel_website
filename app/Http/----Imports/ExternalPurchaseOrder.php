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
            // Skip rows where 'part_no' is null or empty
            if (empty($row['part_no'])) {
                continue;
            }

            // Convert order_qty and closing_qty to integers, handling non-numeric values
            $orderQty = intval($row['order_qty']);
            $closingQty = intval($row['closing_qty']);

            // Calculate 'to_be_ordered' as an integer
            $toBeOrdered = $orderQty - $closingQty;

            // Use updateOrInsert to either update the existing record or insert a new one
            DB::table($this->tableName)->updateOrInsert(
                // Conditions to check for an existing record
                ['part_no' => $row['part_no']],
                
                // Data to insert or update
                [
                    'branch' => $row['branch'],
                    'order_date' => $row['order_date'],
                    'party' => $row['party'],
                    'item' => $row['item'],
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
}
