<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CronJobRunTime extends Model
{
    use HasFactory;

    // (optional) if your table name isn't the pluralized default
    protected $table = 'cron_job_run_time';

    // No $fillable — allow mass assignment of all attributes
    protected $guarded = [];
}
