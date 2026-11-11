<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A monthly target for everyone, one business or one channel. */
class Goal extends Model
{
    public const METRICS = [
        'whatnot_net' => 'Whatnot net',
        'gross' => 'Gross sales',
        'shows' => 'Shows streamed',
        'new_buyers' => 'New buyers',
    ];

    public const SCOPES = ['all' => 'Everyone', 'business' => 'Business', 'channel' => 'Channel'];

    protected $fillable = ['month', 'scope', 'scope_id', 'metric', 'target', 'created_by'];

    protected $casts = ['month' => 'date', 'target' => 'decimal:2', 'scope_id' => 'integer'];

    public function isMoney(): bool
    {
        return in_array($this->metric, ['whatnot_net', 'gross'], true);
    }
}
