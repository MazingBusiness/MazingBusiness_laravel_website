<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41ProductStock extends Model
{
    protected $table = 'manager_41_product_stocks';

    protected $primaryKey = 'id';
    public $incrementing  = true;
    protected $keyType     = 'int'; // BIGINT OK
    public $timestamps     = true;

    protected $fillable = [
        // note: `group` is a reserved word in SQL; Eloquent will quote column names in queries
        'part_no', 'name', 'group', 'category', 'closing_stock', 'list_price', 'godown',
    ];

    /* ---------------- Relationships ---------------- */

    // godown column stores Warehouse NAME
    public function godownDetails()
    {
        return $this->belongsTo(Warehouse::class, 'godown', 'name');
    }

    // link by part_no -> products.part_no
    public function productDetails()
    {
        return $this->belongsTo(Product::class, 'part_no', 'part_no');
    }

    /* ---------- Manager-41 specific mappings (same method names) ---------- */

    // Opening stock -> Manager41OpeningStock
    public function openingProductStock()
    {
        return $this->hasOne(Manager41OpeningStock::class, 'part_no', 'part_no');
    }

    public function openingProductStockQtyForWarehouse($warehouseId)
    {
        return $this->hasOne(Manager41OpeningStock::class, 'part_no', 'part_no')
            ->where('warehouse_id', $warehouseId);
    }

    // Purchases -> Manager41PurchaseInvoiceDetail
    public function purchaseQty()
    {
        return $this->hasMany(Manager41PurchaseInvoiceDetail::class, 'part_no', 'part_no');
    }

    public function purchaseQtyForWarehouse($warehouseId)
    {
        return $this->purchaseQty()->whereHas('purchaseInvoice', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        });
    }

    // Debit Notes in 41: credit notes are stored in Manager41PurchaseInvoice (purchase_invoice_type != 'seller')
    // We keep the same method name but filter purchase details to only credit-note type rows.
    public function debitNoteQty()
    {
        return $this->hasMany(Manager41PurchaseInvoiceDetail::class, 'part_no', 'part_no')
            ->whereHas('purchaseInvoice', function ($q) {
                $q->where('purchase_invoice_type', '!=', 'seller'); // credit/return types
            });
    }

    public function debitNoteQtyForWarehouse($warehouseId)
    {
        return $this->debitNoteQty()->whereHas('purchaseInvoice', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        });
    }

    // Sales via challans (41) -> products.id link
    public function saleQty()
    {
        return $this->hasManyThrough(
            Manager41ChallanDetail::class,
            Product::class,
            'part_no',     // products.part_no
            'product_id',  // manager_41_challan_details.product_id
            'part_no',     // manager_41_product_stocks.part_no
            'id'           // products.id
        );
    }

    public function saleQtyForWarehouse($warehouseId)
    {
        return $this->saleQty()->whereHas('challan', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        });
    }
}
