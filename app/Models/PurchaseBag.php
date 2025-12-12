<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseBag extends Model
{
    use HasFactory;

    protected $table = 'purchase_bags';
    protected $primaryKey = 'id';
    public $timestamps = true;   

    protected $fillable = [
        'branch',
        'order_date',
        'order_no',
        'sub_order_details_id',
        'part_no',
        'party',
        'item',
        'order_qty',
        'closing_qty',
        'to_be_ordered',
        'age',
        'delete_status'
    ];
}
