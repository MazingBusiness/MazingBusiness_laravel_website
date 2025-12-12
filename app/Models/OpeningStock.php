<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningStock extends Model
{
    protected $table = 'opening_stocks';

    protected $fillable = [
        'part_no',
        'name',
        'group',
        'category',
        'closing_stock',
        'list_price',
        'godown',
        'warehouse_id',
    ];

    // If you want timestamps, uncomment the following line
    // public $timestamps = true;

    // Relationships (optional - example if you have Warehouse model)
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
