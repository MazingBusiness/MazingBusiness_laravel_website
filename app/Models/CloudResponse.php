<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudResponse extends Model
{
    use HasFactory;

    protected $table = 'cloud_responses'; // table name
    public $timestamps = true;

    protected $fillable = [
        'msg_id',
        'status',
        'timestamp',
        'callback_data',
        'recipient_id',
        'dump',
    ];
    public function rewardPoints()
    {
        return $this->belongsTo(RewardPointsOfUser::class, 'msg_id', 'msg_id');
    }
}
