<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProductApi extends Model {
  protected $table = 'products_api';

  protected $fillable = [
    'part_no', 'name', 'group', 'category', 'closing_stock', 'list_price', 'godown'
  ];

  public function godownDetails()
  {
      return $this->belongsTo(Warehouse::class, 'godown', 'name');
  }

  public function productDetails()
  {
      return $this->belongsTo(Product::class, 'part_no', 'part_no');
  }

  public function openingProductStock()
  {
      return $this->hasOne(OpeningStock::class, 'part_no', 'part_no');
  }

    public function purchaseQty()
    {
        return $this->hasMany(PurchaseInvoiceDetail::class, 'part_no', 'part_no');
    }

    public function debitNoteQty()
    {
        return $this->hasMany(DebitNoteInvoiceDetail::class, 'part_no', 'part_no');
    }

    public function saleQty()
    {
        return $this->hasManyThrough(
            ChallanDetail::class,
            Product::class,
            'part_no',        // products.part_no
            'product_id',     // challan_details.product_id
            'part_no',        // products_api.part_no
            'id'              // products.id
        );
    }

    public function saleQtyForWarehouse($warehouseId)
    {
        return $this->hasManyThrough(
            ChallanDetail::class,
            Product::class,
            'part_no',        // foreign key on products
            'product_id',     // foreign key on challan_details
            'part_no',        // local key on products_api
            'id'              // local key on products
        )
        ->whereHas('challan', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        });
    }
    
    public function purchaseQtyForWarehouse($warehouseId)
    {
        return $this->purchaseQty()
            ->whereHas('purchaseInvoice', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
    }

    public function debitNoteQtyForWarehouse($warehouseId)
    {
        return $this->debitNoteQty()
            ->with('debitNoteInvoice') // eager load related debit note invoice data
            ->whereHas('debitNoteInvoice', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
    }
    
    public function openingProductStockQtyForWarehouse($warehouseId)
      {
          return $this->hasOne(OpeningStock::class, 'part_no', 'part_no')
          ->where('warehouse_id', $warehouseId);
      }


  
}
