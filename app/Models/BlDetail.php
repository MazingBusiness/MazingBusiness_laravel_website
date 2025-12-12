<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlDetail extends Model
{
    public $timestamps = true;
    protected $table = 'bl_details';

    protected $fillable = [
        'import_company_id',
        'supplier_id',
        'bl_no',
        'ob_date',
        'vessel_name',
        'no_of_packages',
        'gross_weight',
        'net_weight',
        'gross_cbm',
        'port_of_loading',
        'place_of_delivery',
        'pdf_path',
        'bill_of_entry_pdf',
    ];

    protected $casts = [
        'ob_date'      => 'date',
        'gross_weight' => 'float',
        'net_weight'   => 'float',
        'gross_cbm'    => 'float',
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
        return $this->hasMany(BlItemDetail::class, 'bl_id');
    }

    public function cbDetails()
    {
        return $this->hasMany(CbDetail::class, 'bl_id');
    }

    public function cbSummaries()
    {
        return $this->hasMany(CbSummary::class, 'bl_id');
    }
}
