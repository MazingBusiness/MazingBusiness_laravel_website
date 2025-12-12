<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiSummary extends Model
{
    protected $table = 'ci_summary';

    public $timestamps = true;

    protected $fillable = [
        'ci_id',
        'supplier_id',
        'item_print_name',
        'item_quantity',
        'item_dollar_price',
        'summary_type',          // 'supplier', 'total', 'bl', 'diff'
        'cartons_total',
        'weight_total',
        'cbm_total',
        'value_total',
        'import_photo_id',
    ];

    protected $casts = [
        'item_quantity'  => 'float',
        'item_dollar_price' => 'float',
        'cartons_total'  => 'float',
        'weight_total'   => 'float',
        'cbm_total'      => 'float',
        'value_total'    => 'float',
    ];

    public function ci()
    {
        return $this->belongsTo(CiDetail::class, 'ci_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
