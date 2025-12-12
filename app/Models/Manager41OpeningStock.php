<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41OpeningStock extends Model
{
    protected $table = 'manager_41_opening_stocks';

    protected $fillable = [
        'part_no',
        'name',
        'group',        // note: SQL needs backticks for this column name
        'category',
        'closing_stock',
        'list_price',
        'godown',
        'warehouse_id',
    ];

    // If you want timestamps on the table, uncomment:
    // public $timestamps = true;

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
