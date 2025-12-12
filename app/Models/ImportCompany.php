<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportCompany extends Model
{
    public $timestamps = true;
    protected $table = 'import_companies';

    protected $fillable = [
        'company_name',
        'address_1',
        'address_2',
        'city',
        'pincode',
        'state',
        'country',
        'gstin',
        'iec_no',
        'email',
        'phone',
        'buyer_stamp',
    ];

    public function blDetails()
    {
        return $this->hasMany(BlDetail::class, 'import_company_id');
    }
    public function ciDetails()
    {
        return $this->hasMany(CiDetail::class, 'import_company_id');
    }
}
