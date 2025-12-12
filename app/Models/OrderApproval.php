<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderApproval extends Model
{
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}