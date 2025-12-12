<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'mobile_no', 'phone_no', 'logo', 'transit_time', 'gstin', 'free_shipping', 'status', 'warehouse_id', 'all_india', 'delivery_states','zoho_transporter_id'
    ];

    public function carrier_ranges(){
    	return $this->hasMany(CarrierRange::class);
    }
    
    public function carrier_range_prices(){
    	return $this->hasMany(CarrierRangePrice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
