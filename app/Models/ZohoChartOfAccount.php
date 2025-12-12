<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoChartOfAccount extends Model
{
    protected $table = 'zoho_chart_of_accounts';

    protected $fillable = [
        'account_id',
        'account_name',
        'account_code',
        'account_type',
        'description',
        'is_user_created',
        'is_system_account',
        'is_active',
        'can_show_in_ze',
        'parent_account_id',
        'parent_account_name',
        'depth',
        'has_attachment',
        'is_child_present',
        'child_count',
        'created_time',
        'last_modified_time',
        'is_standalone_account',
        'documents',
    ];

    protected $casts = [
        'documents' => 'array',
        'created_time' => 'datetime',
        'last_modified_time' => 'datetime',
    ];
}

?>