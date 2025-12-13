<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadingListItem extends Model
{
    protected $table = 'loading_list_items';
    public $timestamps = true;
    protected $fillable = [
        'loading_list_id',
        'import_po_item_id',
        'product_id',
        'line_no',
        'item_name',
        'crd_date',
        'po_no_display',
        'quantity_in_po_cached',
        'quantity_utilised',
        'unit_of_measure',
        'cnt',
        'gross_weight_total_kg',
        'cbm_total',
        'supplier_id',
        'supplier_name_display',
        'inv_value_rmb',
        'inv_value_usd',
        'advance_rmb',
        'advance_usd',
        'balance_rmb',
        'balance_usd',
        'remarks',
    ];

    // ------------ Relations ------------

    public function loadingList()
    {
        return $this->belongsTo(LoadingList::class, 'loading_list_id');
    }

    public function poItem()
    {
        return $this->belongsTo(ImportPoItem::class, 'import_po_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
