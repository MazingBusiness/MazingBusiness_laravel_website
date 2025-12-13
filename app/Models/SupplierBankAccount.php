<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBankAccount extends Model
{
    protected $table = 'supplier_bank_accounts';

    protected $fillable = [
        'supplier_id',
        'currency',
        'intermediary_bank_name',
        'intermediary_swift_code',
        'account_bank_name',
        'account_swift_code',
        'account_bank_address',
        'beneficiary_name',
        'beneficiary_address',
        'account_number',
        'is_default',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}