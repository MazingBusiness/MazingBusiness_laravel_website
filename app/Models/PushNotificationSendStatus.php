<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PushNotificationSendStatus extends Model
{
    use HasFactory;

    // (optional) if your table name isn't the pluralized default
    protected $table = 'push_notification_send_status';

    // No $fillable — allow mass assignment of all attributes
    protected $guarded = [];

    // (optional) handy casts if you store JSON or datetimes
    protected $casts = [
        'payload'      => 'array',
        'ran_at'       => 'datetime',
        'next_run_at'  => 'datetime',
    ];
}
