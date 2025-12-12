<?php

namespace App\Exports;

use App\Models\SubOrderDetail;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PendingOrdersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $search;
    protected $user;
    protected $isSuperAdmin;

    // Order prefix mapping for warehouses
    protected $warehousePrefixes = [
        1 => 'KOL',
        2 => 'DEL',
        6 => 'MUM',
    ];

    /**
     * @param  string|null      $search
     * @param  \App\Models\User $user
     * @param  bool             $isSuperAdmin
     */
    public function __construct($search, $user, $isSuperAdmin)
    {
        $this->search       = $search;
        $this->user         = $user;
        $this->isSuperAdmin = $isSuperAdmin;
    }

    /**
     * Query used to fetch pending orders for export
     */
    public function query()
    {
        $q = SubOrderDetail::query()
            ->selectRaw("
                sub_order_details.*,
                sub_orders.created_at       AS order_date,
                sub_orders.order_no         AS order_no,
                warehouses.name             AS warehouse_name,
                users.company_name          AS customer_name,
                products.part_no            AS part_no,
                products.name               AS product_name,
                shops.name                  AS seller_name,
                (
                    sub_order_details.approved_quantity - (
                        IFNULL(sub_order_details.pre_closed, 0) +
                        IFNULL(sub_order_details.reallocated, 0) +
                        IFNULL(sub_order_details.challan_qty, 0)
                    )
                )                            AS pending_qty,
                DATEDIFF(CURDATE(), sub_orders.created_at) AS days_pending
            ")
            ->join('sub_orders', 'sub_orders.id', '=', 'sub_order_details.sub_order_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'sub_orders.warehouse_id')
            ->leftJoin('users', 'users.id', '=', 'sub_orders.user_id')
            ->leftJoin('products', 'products.id', '=', 'sub_order_details.product_id')
            ->leftJoin('shops', 'shops.seller_id', '=', 'products.seller_id')
            ->where('sub_order_details.pre_closed_status', '0')
            ->where(function ($q) {
                $q->whereColumn('sub_order_details.challan_qty', '<', 'sub_order_details.approved_quantity')
                  ->orWhereNull('sub_order_details.challan_qty');
            })
            ->whereRaw('sub_order_details.approved_quantity - (IFNULL(sub_order_details.pre_closed, 0) + IFNULL(sub_order_details.reallocated, 0) + IFNULL(sub_order_details.challan_qty, 0)) > 0')
            ->where('sub_orders.order_no', '!=', '');

        // Search filter (same as listing)
        if (!empty($this->search)) {
            $s = $this->search;

            $q->where(function ($q) use ($s) {
                $q->where('sub_orders.order_no', 'like', '%' . $s . '%')
                  ->orWhere('products.part_no', 'like', '%' . $s . '%')
                  ->orWhere('products.name', 'like', '%' . $s . '%')
                  ->orWhere('users.company_name', 'like', '%' . $s . '%');
            });
        }

        // Warehouse filter if NOT super admin
        if (!$this->isSuperAdmin) {
            $prefix = $this->warehousePrefixes[$this->user->warehouse_id] ?? null;

            if ($prefix) {
                $q->where('sub_orders.order_no', 'LIKE', "%{$prefix}%");
            } else {
                // invalid warehouse → no rows
                $q->whereRaw('1 = 0');
            }
        }

        // Sort by order date (ASC), then order no (ASC)
        return $q
            ->orderBy('sub_orders.created_at', 'ASC')
            ->orderBy('sub_orders.order_no', 'ASC');
    }

    /**
     * Excel headings
     */
    public function headings(): array
    {
        return [
            'Order Date',
            'Order No',
            'Warehouse Name',
            'Customer',
            'Part Number',
            'Item Name',
            'Approved Rate',
            'Pending Quantity',
            'Seller Name',
            'Number of Days',
        ];
    }

    /**
     * Map each row to Excel columns
     */
    public function map($row): array
    {
        return [
            $row->order_date
                ? Carbon::parse($row->order_date)->format('d-m-Y H:i')
                : '',
            $row->order_no,
            $row->warehouse_name,
            $row->customer_name,
            $row->part_no,
            $row->product_name,
            $row->approved_rate,
            $row->pending_qty,
            $row->seller_name,
            $row->days_pending,
        ];
    }
}
