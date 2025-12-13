<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadingList extends Model
{
    protected $table = 'loading_lists';
    public $timestamps = true;

    protected $fillable = [
        'loading_list_no',
        'import_company_id',
        'container_type',
        'container_max_gross_weight_kg',
        'container_max_cbm',
        'container_no',
        'shipment_ref',
        'status',
        'total_cnt',
        'total_gross_weight_kg',
        'total_cbm',
        'remaining_weight_kg',
        'remaining_cbm',
        'bl_detail_id',
        'bl_no',
        'bl_date',
        'port_of_loading',
        'port_of_discharge',
        'created_by',
        'updated_by',
    ];

    // ------------ Relations ------------

    public function importCompany()
    {
        return $this->belongsTo(ImportCompany::class, 'import_company_id');
    }

    public function blDetail()
    {
        return $this->belongsTo(BlDetail::class, 'bl_detail_id');
    }

    public function items()
    {
        return $this->hasMany(LoadingListItem::class, 'loading_list_id');
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
