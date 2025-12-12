<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoTax extends Model
{
    protected $table = 'zoho_taxes';

    protected $fillable = [
        'tax_id',
        'tax_name',
        'tax_percentage',
        'tax_type',
        'tax_specific_type',
        'tax_authority_id',
        'tax_authority_name',
        'output_tax_account_name',
        'tax_account_id',
        'tax_specification',
        'is_inactive',
        'is_default_tax',
        'is_editable',
        'status',
        'start_date',
        'end_date',
        'last_modified_time',
    ];

    public $timestamps = true;
}
