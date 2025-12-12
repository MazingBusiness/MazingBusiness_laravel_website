<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    public $timestamps = true;
    protected $table = 'suppliers';

    protected $fillable = [
        'supplier_name',
        'address',
        'city',
        'district',
        'country',
        'zip_code',
        'contact',
        'email',
        'bank_details',
        'stamp',
        
    ];

    public function blDetails()
    {
        return $this->hasMany(BlDetail::class, 'supplier_id');
    }

    public function ciDetails()
    {
        return $this->hasMany(CiDetail::class, 'supplier_id');
    }

    public function ciSummaries()
    {
        return $this->hasMany(CiSummary::class, 'supplier_id');
    }
    public function bankAccounts()
    {
        return $this->hasMany(SupplierBankAccount::class);
    }
    
    // optional: quick default account accessor
    public function defaultBankAccount()
    {
        return $this->hasOne(SupplierBankAccount::class)->where('is_default', 1);
    }
}
