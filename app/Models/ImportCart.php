<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportCart extends Model
{
    protected $table = 'import_carts';

    protected $fillable = [
        'bl_detail_id',
        'import_company_id',
        'product_id',
        'quantity',
        'dollar_price',
        'import_print_name',
        'weight_per_carton',
        'cbm_per_carton',
        'quantity_per_carton',
        'supplier_id',
        'supplier_invoice_no',
        'supplier_invoice_date',
        'terms', // 👈 NEW
        
        'total_no_of_packages',
        'total_weight',
        'total_cbm',
        'import_photo_id',
    ];

    public function blDetail()
    {
        return $this->belongsTo(BlDetail::class, 'bl_detail_id');
    }

    public function importCompany()
    {
        return $this->belongsTo(ImportCompany::class, 'import_company_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
