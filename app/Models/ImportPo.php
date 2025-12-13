<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportPo extends Model
{
    protected $table = 'import_pos';
    public $timestamps = true;

    protected $fillable = [
        'po_no',
        'import_company_id',
        'supplier_id',
        'po_date',
        'currency_code',
        'delivery_terms',
        'payment_terms',
        'remarks',
        'status',
        'total_qty',
        'total_value_usd',
        'total_value_rmb',
        // 'created_by',
        // 'updated_by',
    ];

    // ------------ Relations ------------

    public function importCompany()
    {
        return $this->belongsTo(ImportCompany::class, 'import_company_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(ImportPoItem::class, 'import_po_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
