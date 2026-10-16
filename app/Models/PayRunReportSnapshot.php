<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayRunReportSnapshot extends Model
{
    protected $fillable = ['weekly_payout_batch_id', 'stage', 'payload', 'created_by'];
    protected $casts = ['payload' => 'array'];
}
