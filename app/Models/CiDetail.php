<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiDetail extends Model
{
    public $timestamps = true;
    protected $table = 'ci_details';

    protected $fillable = [
        'import_company_id',
        'supplier_id',
        'bl_id',
        'supplier_invoice_no',
        'supplier_invoice_date',

        'no_of_packages',
        'gross_weight',
        'net_weight',
        'gross_cbm',

        'pdf_path',
    ];

    protected $casts = [
        'no_of_packages'       => 'integer',
        'gross_weight'         => 'float',
        'net_weight'           => 'float',
        'gross_cbm'            => 'float',
        'supplier_invoice_date'=> 'date',
    ];

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
        return $this->hasMany(CiItemDetail::class, 'ci_id');
    }

    public function summaries()
    {
        return $this->hasMany(CiSummary::class, 'ci_id');
    }
    
    public function bl()
    {
        return $this->belongsTo(BlDetail::class, 'bl_id');
    }
}
