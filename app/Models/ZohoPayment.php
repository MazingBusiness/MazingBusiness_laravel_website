<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoPayment extends Model
{
    protected $fillable = ['payment_link_url', 'payment_link_id', 'expires_at','payable_amount','description','user_id','party_code','email','phone','send_by_id','payment_status'];
}

?>